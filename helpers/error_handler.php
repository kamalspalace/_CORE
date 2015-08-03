<?php

class ErrorHandler
{
	var $user = array();
	var $action = "";
	var $view = "";
	var $view_action = "";
	var $error_message = "";
	
	function __construct($action, $view, $view_action)
	{
		ini_set("error_reporting","E_ALL & ~E_DEPRECATED & ~E_NOTICE");
		error_reporting(E_ALL ^ E_NOTICE);
		ini_set("display_errors", "0");
		set_error_handler(array($this, "handle_error"));
		register_shutdown_function(array($this, "handle_critical_error"));
		
		$this->action = $action;
		$this->view = $view;
		$this->view_action = $view_action;
	}
	
	function init(&$user)
	{
		$this->user = $user;
	}
	
	function handle_error($error_level, $error_message, $error_file, $error_line, $error_context)
	{
		$error = "";
		switch($error_level)
		{
			case '2':
			case '512': $error = "WARNING";
			break;
			
			case '8':
			case '1024': $error = "NOTICE";
			break;
			
			default: $error = "ERROR";
		}
		
		$message = "<b>".$error."</b>: ".$error_message. " in <b>".$error_file."</b> on line <b>".$error_line."</b><br/><br/>";
		
		if(!isset($this->user['id'])) $this->user['id'] = "";
		if(!isset($this->user['name'])) $this->user['name'] = "";
			

		query("INSERT INTO
					`".$this->user['database']."`.errors
					(
						client,
						user_id,
						user_name,
						action,
						view,
						view_action,
						error_message
					)
					VALUES
					(
						'".$this->user['client']."',
						'".$this->user['id']."',
						'".$this->user['name']."',
						'".$this->action."',
						'".$this->view."',
						'".$this->view_action."',
						?
					)", array($message));
					
		$this->error_message .= $message;
	}
	
	function handle_critical_error()
	{
		$error = error_get_last();
		
		if($error['message'])
		{
			$error_type = "";
			switch($error['type'])
			{
				case '2':
				case '512': $error_type = "WARNING";
				break;
				
				case '8':
				case '1024': $error_type = "NOTICE";
				break;
				
				default: $error_type = "ERROR";
			}
			
			if($error['type'] == '1' || $error['type'] == '4' || $error['type'] == '16' || $error['type'] == '256' || $error['type'] == '4096')
				$fatal = true;
			else
				$fatal = false;
			
			$message = "<b>".$error_type."[".$error['type']."]</b>: ".$error['message']." in <b>".$error['file']."</b> on line <b>".$error['line']."</b><br/><br/>";
			
			if(!isset($this->user['id'])) $this->user['id'] = "";
			if(!isset($this->user['name'])) $this->user['name'] = "";
			
			query("INSERT INTO 
					errors
					(
						client,
						user_id,
						user_name,
						action,
						view,
						view_action,
						error_message
					)
					VALUES
					(
						'".$this->user['client']."',
						'".$this->user['id']."',
						'".$this->user['name']."',
						'".$this->action."',
						'".$this->view."',
						'".$this->view_action."',
						?
					)", array($message));
					
			$this->error_message .= $message;
			
			if($fatal)
				print json_encode(array('error' => $this->error_message));
		}
	}
	
	function handle_integration_error($error_message, $integration_request,$customFlag=null)
	{
		query("INSERT INTO 
					errors
					(
						client,
						user_id,
						user_name,
						action,
						view,
						view_action,
						error_message,
						integration_request
					)
					VALUES
					(
						'".$this->user['client']."',
						'".$this->user['id']."',
						'".$this->user['name']."',
						'".$this->action."',
						'".$this->view."',
						'".$this->view_action."',
						?,?
					)", array($error_message, $integration_request));
       
	/* 
		if (isset($customFlag)){ 
			$errorString = explode("[",$error_message);
			$this->error_message =  $errorString[0];
		} else {
			$this->error_message =  "Please come back soon. We are upgrading system.";
		}	
	*/	
		$headers = "From: " . $this->user['client'] . " Online\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

        $email = 'kpathak@jtec.ca,jjtrottier@jtec.ca';
		$message = $error_message;
	
		// mail($email, 'Socket Failure', $message, $headers);
                
					
		//$this->error_message .= $error_message;
	}
}

?>
