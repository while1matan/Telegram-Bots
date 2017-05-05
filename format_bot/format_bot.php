<?php

// -------------------------------------------
// DEFINE BOT INFORMATION
// -------------------------------------------
define("SCRIPT_BASE_URL"	, "https://while1.co.il/");
define("BOT_TOKEN"			, "*****");		// token from telegram
define("BOT_NAME"			, "w1_format_bot");
define("WEBHOOK_SECRET"		, "*****");
define("WEBHOOK_URL"		, SCRIPT_BASE_URL . basename(__FILE__) . "?secret=" . WEBHOOK_SECRET);

// -------------------------------------------
// PREVENT ACCESS WITHOUT A SECRET KEY
// -------------------------------------------
if(!isset($_GET['secret']) || $_GET['secret'] != WEBHOOK_SECRET){
	header('HTTP/1.0 403 Forbidden');
	exit();
}

// -------------------------------------------
// INITIATE BOT CLASS
// -------------------------------------------
require_once "../class.telegram.bot.php";
$bot = new TelegramBot(BOT_TOKEN , BOT_NAME);
$bot->debug(true);
$bot->setSavePath(__DIR__);

// -------------------------------------------
// SET/REMOVE WEBHOOK
// -------------------------------------------
if(isset($_GET['webhook'])){
	print_r( ($_GET['webhook'] == "remove")? $bot->unsetWebhook() : $bot->setWebhook(WEBHOOK_URL) );
	exit();
}

// -------------------------------------------
// CHECK FOR NEW UPDATE
// -------------------------------------------
if(!$bot->receiveNewUpdate()){
	exit();
}

// -------------------------------------------
// ON NEW UPDATE-TYPE: MESSAGE
// -------------------------------------------
if($bot->has("message" , $message)){	
	$bot->replyMethod("sendMessage" , [
		"text" => $message['text'],
		"parse_mode" => "Markdown",
		"disable_web_page_preview" => true
	]);
}

// -------------------------------------------
// ON NEW UPDATE-TYPE: INLINE_QUERY
// -------------------------------------------
if($bot->has("inline_query" , $query)){
	$bot->method("answerInlineQuery", [
		"inline_query_id" => $query['id'],
		"is_personal" => true,
		"results" => json_encode([
			[
				"type" => "article",
				"id" => "1",
				"title" => "(w1) Formatted Text",
				"description" => "Click here to send the result",
				"input_message_content" => [
					"message_text" => $query['query'],
					"parse_mode" => "Markdown",
					"disable_web_page_preview" => true
				]
			]
		])
	]);
}

?>