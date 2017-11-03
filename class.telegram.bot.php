<?php

error_reporting(-1);
header('Content-Type: text/html; charset=utf-8');

class TelegramBot {
	
	private $botToken		= "";
	private $botName		= "";
	private $apiUrl			= "https://api.telegram.org/bot";
	private $apiFilesUrl	= "https://api.telegram.org/file/bot";
	
	private $debug		= false;
	private $savePath	= "";
	private $logFile	= "telegram.bot.log";			// file to store debug log
	private $uidFile	= "telegram.bot.updateid";		// file to store last update-id
	
	private $ch			= null;
	private $ch_timeout	= 60;
	
	private $currentChatId	= null;		// chat identifier for last incoming message
	private $currentUpdate	= null;		// last update data
	
	private $cacheUpdateId	= 60*60*24;
	
	// INITIATE THIS CLASS
	public function __construct($token , $botName) {
		$this->registerErrorsHandlers();
		
		$this->botToken		= $token;
		$this->apiUrl		.= $this->botToken . '/';
		$this->apiFilesUrl	.= $this->botToken . '/';
		
		$this->botName = $botName;
		$this->logFile = "{$botName}.log";
		$this->uidFile = "{$botName}.updateid";
		
		$this->createCurl();
	}
	
	// KILL THIS CLASS
	public function __destruct(){
		$this->destroyCurl();
	}
	
	// HANDLE SCRIPT ERRORS
	private function registerErrorsHandlers(){
		set_error_handler([$this , 'error_handler']);
		register_shutdown_function([$this , 'script_shutdown']);
	}
	
	// CAPTURE ERRORS
	public function error_handler($code, $message, $file, $line){
		// error suppressed with @
		if(error_reporting() == 0 || ini_get('error_reporting') == 0) {
			return false;
		}
		$this->errorLog($code , $message , $file , $line);
	}
	
	// CAPTURE SCRIPT-SHUTDOWN EVENT
	public function script_shutdown(){
		$error = error_get_last();
		if(isset($error['type'])) {
			$this->errorLog($error['type'] , $error['message'] , $error['file'] , $error['line']);
		}
	}
	
	// SAVE ERROR DATA TO LOG FILE
	private function errorLog($code , $msg , $file , $line){
		switch ($code) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$error_level = "Notice";
				break;
			case E_WARNING:
			case E_USER_WARNING:
				$error_level = "Warning";
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$error_level = "Fatal Error";
				break;
			default:
				$error_level = "Unknown";
				break;
		}
		
		$this->log("\nPHP> {$error_level}: {$msg} in {$file} on line {$line}\n" , true);
	}
	
	// SET/GET DEBUG MODE
	public function debug($mode = null , $file = null){		
		if(is_bool($mode)){
			$this->debug = $mode;
			
			if(is_string($file)){
				$this->logFile = $file;
			}
		}
		
		return $this->debug;
	}
	
	// SET PATH FOR LOG FILES
	public function setSavePath($path){
		if(!is_string($path)){
			return false;
		}
		
		if(!file_exists($path)){
			if(!mkdir($path , 0777 , true)){
				$this->log("*cannot create {$path} directory*");
				return false;
			}
		}
		
		if($path[ strlen($path) - 1 ] != '/'){
			$path .= '/';
		}
		
		$this->savePath = $path;
		return true;
	}
	
	// KEEP LOG
	private function log($msg , $force_log = false){
		if($this->debug || $force_log){
			$date = date("d/m/y H:i:s");
			file_put_contents($this->savePath . $this->logFile , "[{$date}] {$msg}\n" , FILE_APPEND | LOCK_EX);
		}
	}
	
	// CREATE cURL SESSION
	private function createCurl(){
		$this->ch = curl_init();

		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, $this->ch_timeout);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($this->ch, CURLOPT_HEADER, false);
		curl_setopt($this->ch, CURLOPT_FAILONERROR, true);
		curl_setopt($this->ch, CURLINFO_HEADER_OUT, false);
		curl_setopt($this->ch, CURLOPT_POST, true);
		curl_setopt($this->ch, CURLOPT_SAFE_UPLOAD, true);
	}
	
	// CLOSE cURL SESSION
	private function destroyCurl(){
		if($this->ch !== null){
			curl_close($this->ch);
			$this->ch = null;
		}
	}
	
	// SET POST FOR cURL SESSION
	private function setPost($postArr){
		$postFields = "";
		
		if(is_array($postArr) && !empty($postArr)){
			$postFields = http_build_query($postArr);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $postArr);
		}
		
		return $postFields;
	}
	
	// SEND METHOD TO TELEGRAM
	// if ok, return result-array. otherwise, false.
	public function method($method , $postArr = [] , $returnFullResponse = false){
		curl_setopt($this->ch, CURLOPT_URL, $this->apiUrl . $method);
		$postFields = $this->setPost($postArr);
		$response = curl_exec($this->ch);
		
		$this->log("/{$method} {$postFields}");
		
		if($response === false){
			$this->log("FAILED");
			
			$info = curl_getinfo($this->ch);
			$this->log(json_encode($info));
			
			return false;
		}

		$this->log($response);

		$responseArr = json_decode($response , true);
		
		if(!isset($responseArr['ok']) || $responseArr['ok'] != true || !isset($responseArr['result'])){
			return false;
		}
		
		if($returnFullResponse == true){
			return $responseArr;
		}
		
		return $responseArr['result'];
	}
	
	// RESTRICT ACCESS TO THIS PAGE WITHOUT secret GET PARAMETER
	public function restrictAccess($secret_str){
		if(!isset($_GET['secret']) || $_GET['secret'] !== $secret_str){
			header('HTTP/1.0 403 Forbidden');
			exit();
		}
	}
	
	// SET WEBHOOK
	public function setWebhook($url){
		return $this->method("setWebhook" , ["url" => $url] , true);
	}
	
	// CLEAR WEBHOOK
	public function unsetWebhook(){
		return $this->setWebhook("");
	}
	
	// CHECK IF CURRENT REQUEST IS FOR (UN)SETTING WEBHOOK
	public function handleWebhook($webhook_url){
		if(isset($_GET['webhook'])){
			print_r( ($_GET['webhook'] == "remove")? $this->unsetWebhook() : $this->setWebhook($webhook_url) );
			exit();
		}
	}
	
	// RECEIVE NEW UPDATE FROM TELEGRAM
	public function receiveNewUpdate(){
		$this->log("RECEIVING NEW UPDATE:");
		
		$newUpdate = file_get_contents('php://input');
		
		if(empty($newUpdate)){
			$this->log("ERROR: EMPTY DATA");
			return false;
		}
		
		$this->currentUpdate = json_decode($newUpdate , true);
		
		if(empty($this->currentUpdate)){
			$this->log("ERROR: BAD JSON");
			return false;
		}
		
		if(!$this->isNewUpdate($this->currentUpdate)){
			$this->log("OLD UPDATE FOUND. SKIPPING...");
			return false;
		}
		
		$this->log("\n" . print_r($this->currentUpdate, true));
		
		$this->processUpdate($this->currentUpdate);
		
		return true;
	}
	
	// CHECK IF RECEIVED UPDATE IS NEW
	// (update-id is greater than saved id)
	private function isNewUpdate(&$updateArr){		
		if(isset($updateArr['update_id'])){
			$is_new = false;
			
			$update_id = (int)$updateArr['update_id'];
			
			$this->log("CHECKING UPDATE-ID: {$update_id}");
			
			if(	file_exists($this->savePath . $this->uidFile) &&
				filemtime($this->savePath . $this->uidFile) >= time() - $this->cacheUpdateId
			){
				$last_update_id = (int)file_get_contents($this->savePath . $this->uidFile);
				$is_new = ($update_id > $last_update_id);
			}
			else {
				$is_new = true;
			}
			
			if($is_new){
				file_put_contents($this->savePath . $this->uidFile , $update_id);
				return true;
			}
		}
		
		return false;
	}
	
	// PROCESS INCOMING UPDATE
	private function processUpdate(&$updateArr){
		if(isset($updateArr['message']['chat']['id'])){
			$this->currentChatId = $updateArr['message']['chat']['id'];
		}
		else if(isset($updateArr['edited_message']['chat']['id'])){
			$this->currentChatId = $updateArr['edited_message']['chat']['id'];
		}
		else if(isset($updateArr['callback_query']['message']['chat']['id'])){
			$this->currentChatId = $updateArr['callback_query']['message']['chat']['id'];
		}
	}
	
	// CHECK IF SPECIFIC TYPE OF UPDATE IS AVAILABLE
	// if $returnUpdate is present, it'll get the update-data
	public function has($updateType , &$returnUpdate = false){
		if(isset($this->currentUpdate[ $updateType ])){
			if($returnUpdate !== false){
				$returnUpdate = $this->currentUpdate[ $updateType ];
			}
			
			return true;
		}
		
		return false;
	}
	
	// FAST REPLY *TO MESSAGES*
	// AUTOMATICALLY ADD LAST SAVED 'chat_id' as parameter
	public function replyMethod($method , $postArr = []){
		if(empty($this->currentChatId)){
			$this->log("/{$method} REPLY ERROR: CHAT-ID IS EMPTY");
			return false;
		}
		
		$postArr['chat_id'] = $this->currentChatId;
		
		return $this->method($method, $postArr);
	}
	
	// FAST REPLY TEXT MESSAGE
	public function replyText($text){
		return $this->replyMethod("sendMessage" , ["text" => $text]);
	}
	
	// FIND COMMAND IN LAST UPDATE-MESSAGE
	// COMMANDS INFORMATION : https://core.telegram.org/bots#commands
	public function findCommand(&$returnCmd = false , &$returnArgs = false){
		
		if(isset($this->currentUpdate['message']['text'])){
			$text = trim($this->currentUpdate['message']['text']);
			
			$username = strtolower('@' . $this->botName);
			$username_len = strlen($username);

			// if text starts with bot-username mention, remove it
			if(strtolower(substr($text, 0, $username_len)) == $username) {
				$text = trim(substr($text, $username_len));
			}

			// find command (starts with '/')
			if(preg_match('/^(?:\/([a-z0-9_]+)(@[a-z0-9_]+)?(?:\s+(.*))?)$/is', $text, $matches)){
				
				$command = $matches[1];
				$command_owner = (isset($matches[2]))? strtolower($matches[2]) : null;
				$command_params = (isset($matches[3]))? $matches[3] : "";
				
				// check command owner bot
				// if sent in group, it can be specified for another bot (e.g. /cmd@anotherBot)
				if(empty($command_owner) || $command_owner == $username){
					
					if($returnCmd !== false){
						$returnCmd = strtolower($command);
					}
					
					if($returnArgs !== false){
						$returnArgs = $command_params;
					}
					
					return true;
				}
			}
		}
		
		return false;
	}
	
	// PREPARE FILE FOR CURL UPLOAD
	public function prepareFile($path){
		return new CURLFile( realpath($path) );
	}
	
	// CREATE NEW KEYBOARD OBJECT
	public function newInlineKeyboard(){
		return new InlineKeyboard();
	}
}

// ----------------------------------
//	TELEGRAM INLINE KEYBOARD OBJECT
// ----------------------------------
class InlineKeyboard {
	private $markup = [];
	private $current_row = -1;
	
	public function addRow(){
		return ++$this->current_row;
	}
	
	public function addButton($text , $url = false , $callback_data = false , $switch_inline_query = false , $switch_inline_query_current_chat = false){
		$button_data = [
			"text" => $text
		];
		
		// telegram api: must use exactly one of the optional fields
        if(is_string($url) && $url !== ""){
			$button_data['url'] = $url;
		} else if (is_string($callback_data) && $callback_data !== ""){
			$button_data['callback_data'] = $callback_data;
		} else if (is_string($switch_inline_query)){
			$button_data['switch_inline_query'] = $switch_inline_query;
		} else if (is_string($switch_inline_query_current_chat)){
			$button_data['switch_inline_query_current_chat'] = $switch_inline_query_current_chat;
		}
		
		$this->markup[ $this->current_row ][] = $button_data;
		
		return $button_data;
	}
	
	public function getMarkup(){
		return $this->markup;
	}
}

?>