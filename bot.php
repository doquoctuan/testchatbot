<?php
include "ChatFramework/dist/ChatFramework.php";
include "ChatFramework/dist/MessageBuilder.php";
include "config.php";

header("Content-type: text/html; charset=utf-8");
mysqli_set_charset($conn, 'UTF8');

$isHubChallenge = true;
$bot = new \NorthStudio\ChatFramework($accessToken, $isHubChallenge);
$builder = new \NorthStudio\MessageBuilder();

// now we will get the sender's id
$userId = $bot->getSenderId();

$user = array(
	"name" => "",
	"mess_id" => "",
	"state" => "0",
	"joined_pair" => "0"
);

$checkUserQuery = $conn->query("SELECT * FROM `users` WHERE `mess_id` = '$userId'");
if ($checkUserQuery->num_rows == 0) {
	$userInfo = $bot->getUserData($userId);
	$userGen = $bot->getGender($userId);
	$addUserQuery = $conn->query("INSERT INTO `users` (`name`, `mess_id`, `state`) VALUES ('{$userInfo['name']}', '$userId', '0')");
	if($userGen['gender'] == 'male'){
		$conn->query("UPDATE `users` SET `gender`= 1 WHERE `mess_id` = '$userId'");
	}
	if ($addUserQuery) {
		// first message when user come to chatbot 
		$firstButton = $builder->createButton("postback", "Pairing", json_encode(array(
			"event" => "main_menu",
			"choice" => "ghep_cap"
		)));
		$menu = $builder->createButtonTemplate("Welcome to Thính Nguyễn Du! Click the button below to pair", [
		$firstButton,
	]);
			$bot->sendMessage($userId, $menu);
	} else {
		$bot->sendTextMessage($userId, "Hệ thống bận! Hãy thử lại sau");
	}
} else {
	$user = $checkUserQuery->fetch_assoc();
}

if ($bot->isPostBack) {
	$payload = json_decode($bot->getPayload(), true);
	if ($payload['event'] == "static_menu") {
		include "./events/static_menu.php";
	} else if ($payload['event'] == "main_menu") {
		include "./events/main_menu.php";
	} else {
		// invalid event
	}
} else {
	if ($user['state'] == '2') {
		$pairQuery = $conn->query("SELECT * FROM `pairs` WHERE `id` = {$user['joined_pair']}");
		if ($pairQuery && $pairQuery->num_rows == 1) {
			$pair = $pairQuery->fetch_assoc();
			$otherParticipant = $pair['p1'] == $userId ? $pair['p2'] : $pair['p1'];		
		}
	} else if ($user['state'] == '1' && $bot->getMessageText() != 'End'){
		$bot->sendTextMessage($userId, "❗ Searching, type 'End' to exit");	
	} else if ($user['state'] == '1' && $bot->getMessageText() == 'End'){
		$conn->query("DELETE FROM `pairs` WHERE `p1` = '$userId' AND `p2` = ''");
		$conn->query("UPDATE `users` SET `state`='0', `joined_pair`='0' WHERE `mess_id` = '$userId'");
		$bot->sendTextMessage($userId, "💔 You left. Type 'Pairing' to pair.");
	}
	if($bot->getMessageText() != 'End'){
		$bot->sendTextMessage($otherParticipant, $bot->getMessageText());
	} else if ($user['state'] == '2' && $bot->getMessageText() == 'End'){
		$bot->sendTextMessage($userId, "💔 You left the conversation. Type 'Pairing' to pair");
		$bot->sendTextMessage($otherParticipant, "💔 Stranger left the conversation. Type 'Pairing' to pair");
		$conn->query("UPDATE `users` SET `state`='0', `joined_pair`='0' WHERE `mess_id` = '$userId'");
		$conn->query("UPDATE `users` SET `state`='0', `joined_pair`='0' WHERE `mess_id` = '$otherParticipant'");
		$conn->query("DELETE FROM `pairs` WHERE `p1` = '$userId' AND `p2` = ''");
	} 
}
if($bot->getMessageText() == 'Pairing' || $bot->getMessageText() == 'pairing'){
	if($user['gender'] == 0){
		$bot->sendTextMessage($userId, "You are female, we will find a man for you");
		$checkingQueryKhac = $conn->query("SELECT * FROM `pairs`, `users` WHERE `p1` = '' OR `p2` = '' AND NOT (`p1` = '$userId' OR `p2` = '$userId') AND mess_id = p1 
AND gender = 1 LIMIT 1");
	} else {
		$bot->sendTextMessage($userId, "You are male, we will find a girl friend for you");
		$checkingQueryKhac = $conn->query("SELECT * FROM `pairs`, `users` WHERE `p1` = '' OR `p2` = '' AND NOT (`p1` = '$userId' OR `p2` = '$userId') AND mess_id = p1 
AND gender = 0 LIMIT 1");
	}

	if(!$checkingQueryKhac){
			$bot->sendTextMessage($userId, "Error!");
		}
		if($user['state'] == '0'){
			if($checkingQueryKhac->num_rows == 0){
				// create new pair
			if ($conn->query("INSERT INTO `pairs` (`p1`) VALUE ('$userId')")) {
				$pairId = $conn->insert_id;
				$bot->sendTextMessage($userId, "🕹 Searching.... ");
				$conn->query("UPDATE `users` SET `state`='1', `joined_pair`=$pairId WHERE `mess_id` = '$userId'");
			} else {
				// failed to create new pair
			}
			} else {
				$pair = $checkingQueryKhac->fetch_assoc();				
				$oldParticipant = $pair['p1'];
				if ($conn->query("UPDATE `pairs` SET `p1` = '$oldParticipant', `p2` = '$userId' WHERE `id` = '{$pair['id']}'")) {
				$bot->sendTextMessage($userId, "💌 Success! Let's start chatting");
				$conn->query("UPDATE `users` SET `state`='2', `joined_pair`={$pair['id']} WHERE `mess_id` = '$userId'");
				$conn->query("UPDATE `users` SET `state`='2' WHERE `mess_id` = '$oldParticipant'");
				$bot->sendTextMessage($oldParticipant, "💌 Success! Let's start chatting");
				} 		
			}
		}
}
?>










