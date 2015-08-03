<?php

class preferences extends View
{
	function __construct($user, $return)
	{
		$this->user = $user;
		$this->return = $return;
		parent::__construct();
	}

	function init($data)
	{
		$inputs = array();
		
		$settings_result = query("SELECT settings FROM `".$this->user['database']."`.account_settings WHERE group_id='".$this->user['group']."' ORDER BY id desc");
		$settings_data = fetch($settings_result);
		
		if($settings_data['settings'])
		{
			$data = json_decode($settings_data['settings']);
			$this->return['inputs'] = $data;
		}

		$this->smarty->assign('preferences',json_decode($settings_data['settings']));
        	$html  = $this->smarty->fetch('settings/settings.tpl');
        	$html .= $this->load_tooltips('settings');

        	$this->html['shipon_content'] = $html;
	}
	
	function save_settings()
	{
		foreach($_POST as $pname => $pval) {
		  if(strpos($pname,'shipon_settings') !== false) { }
		  else
		    unset($_POST[$pname]);
		}

		$data = json_encode($_POST);
		query("INSERT into `".$this->user['database']."`.account_settings(`group_id`,`settings`) VALUES (?,?)",array($this->user['group'],$data));
	}
}

?>
