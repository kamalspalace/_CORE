<?php

class manage_accounts extends View
{
	function __construct($user, $return)
	{
		$this->user = $user;
		$this->return = $return;
        parent::__construct();
	}

	function init($data)
	{
		if($this->user['type'] != 1)
			return parse_bill_xml($user, 0, 'new');

		$accounts_xml = file_get_contents($this->user['folder']."/templates/accounts.xml");
		$xml = new SimpleXMLElement($accounts_xml);

		$accounts_result = query("SELECT *
									FROM `".$this->user['database']."`.accounts
									WHERE group_id = ".$this->user['group']." AND active=1
									ORDER BY id
									LIMIT ".$data * $xml->entries_per_page.",".$xml->entries_per_page);

		$num_result = query("SELECT id FROM `".$this->user['database']."`.accounts WHERE group_id='".$this->user['group']."'");
		$num = num_rows($num_result);
		$pageCount = ceil($num / $xml->entries_per_page);

		$result_count = num_rows($accounts_result);

		for($i = 0; $i < $xml->entries_per_page; $i++)
		{
			if($i < $result_count)
			{
				$resultSet[] = fetch($accounts_result);
			}
		}

		$login_xml = file_get_contents($this->user['folder']."/templates/login.xml");
		$inputs_xml = new SimpleXMLElement($login_xml);

		$hiddenPopup .= "<div id='shipon_account_popup' class='shipon_popup' title='".text('new_account')."'>";
		
		foreach($xml->popup->children() as $block)
		{
			$hiddenPopup .= "<div class='shipon_fieldblock' style='width: ".$block['width']."'>";
			if(count($block->label))
			$block->label = text($block->label);
				
			$elements = $block->children();
			foreach($elements as $element)
			{
				$hiddenPopup .= $element->asXML();
			}
			$hiddenPopup .= "</div>";
		}
						
		
		foreach($inputs_xml->input as $input)
		{
			$hiddenPopup .= "<div class='shipon_fieldblock' style='width: 100%'>
							<label style='float: left; text-align: left; width: 75px;'>".$input['text'].":</label>
							<input type='".$input['type']."' id='".$input['name']."_new' name='".$input['name']."_mew' class='shipon_input'></input>
						</div>";
		}
		
		$hiddenPopup .= "	<div class='shipon_fieldblock' style='width: 100%'>
							<a class='dialog_action shipon_button' onclick='shipon_process_account(this)'> ".text('create')." </a>
							<span id='shipon_account_output'></span>
						</div>
					</div>";


		$hiddenPopup .= "	<div id='shipon_edit_account_popup' class='shipon_popup' title='".text('edit_account')."'>";
						
		foreach($xml->popup->children() as $block)
		{
			$hiddenPopup .= "<div class='shipon_fieldblock' style='width: ".$block['width']."'>";
			if(count($block->label))
			$block->label = text((string)$block->label).":";
				
			$elements = $block->children();
			foreach($elements as $element)
			{
				$element['name'] .= "_edit";
				$element['id'] .= "_edit";
				$hiddenPopup .= $element->asXML();
			}
			$hiddenPopup .= "</div>";
		}
		
		foreach($inputs_xml->input as $input)
		{
			$hiddenPopup .= "<div class='shipon_fieldblock' style='width: 100%'>
							<label style='float: left; text-align: left; width: 75px;'>".$input['text'].":</label>
							<input type='".$input['type']."' id='".$input['name']."_edit' name='".$input['name']."_edit' class='shipon_input'></input>
						</div>";
		}
		
		$hiddenPopup .= "	<div class='shipon_fieldblock' style='width: 100%'>
							<a class='dialog_action shipon_button' onclick='shipon_edit_account(this)'> ".text('save')." </a>
							<span id='shipon_account_output'></span>
						</div>
					</div>";

		$close = 'jQuery("#shipon_account_delete_popup").dialog("close");';

		$hiddenPopup .= "	<div id='shipon_account_delete_popup' class='shipon_popup' title='".text('delete_account')."'>
						<div class='shipon_fieldblock' style='width: 100%'>
							".text('account_delete_message')."
						</div>
						<div class='shipon_fieldblock' style='width: 100%'>
							<a class='shipon_button' id='shipon_delete_yes' onclick='shipon_delete_account()'> ".text('yes')." </a>
							<a class='shipon_button' id='shipon_delete_no' onclick='".$close."'> ".text('no')." </a>
							<input type='hidden' id='shipon_delete_address_id'></input>
							<span id='shipon_delete_output'></span>
						</div>
					</div>";

		$pagination =  $this->get_paged_footer("#manage_accounts", $xml->entries_per_page, $pageCount, $xml->page_numbers);
		$this->smarty->assign('hiddenPopup',$hiddenPopup);
		$this->smarty->assign('accounts',$resultSet);
		$this->smarty->assign('pagination',$pagination);

		$this->html['shipon_content'] = $this->smarty->fetch('accounts/accounts.tpl');;
	}

	function create_account()
	{
		if($this->user['type'] != 1)
			return 0;			

		if(strlen($_REQUEST['name']) < 2)
			$this->html['shipon_account_output'] = "Name is too short.";
		elseif(strlen($_REQUEST['username']) < 4)
			$this->html['shipon_account_output'] = "Username must be at least 4 characters (".strlen($_REQUEST['username']).").";
		elseif(strlen($_REQUEST['password']) < 6)
			$this->html['shipon_account_output'] = "Password must be at least 6 characters.";
		else
		{
			$users_result = query("SELECT id FROM `".$this->user['database']."`.accounts WHERE username=?", array($_REQUEST['username']));
			if(num_rows($users_result) < 1)
			{
				$id = query_r("INSERT INTO `".$this->user['database']."`.accounts
							   (
										group_id, name, phone, ext, email, username, password, type
							   )
							   VALUES
							   (
									'".$this->user['group']."', ?, ?, ?, ?, ?, ?, 2
							   )
							", array($_REQUEST['name'], $_REQUEST['phone'], $_REQUEST['ext'], $_REQUEST['email'], $_REQUEST['username'], md5($_REQUEST['password'])));

				query("INSERT INTO `".$this->user['database']."`.sessions
						(
							id, session_id, timestamp
						)
						VALUES
						(
							'".$id."','0','0'
						)
					");

				$this->html['shipon_account_output'] = '<script type="text/javascript">
                                                                            jQuery("#shipon_account_popup").dialog("close");
                                                                            change_view("manage_accounts");
									</script>';
			}
			else
				$this->html['shipon_account_output'] = "User already exists.";
		}
	}

	function edit_account()
	{
		if($this->user['type'] != 1)
			return 0;

		query("UPDATE `".$this->user['database']."`.accounts SET password=?, name=?, phone=?, ext=?, email=? WHERE username=?", 
			  array(md5($_REQUEST['edit_pass']), $_REQUEST['edit_name'], $_REQUEST['edit_phone'], $_REQUEST['edit_ext'], $_REQUEST['edit_email'], $_REQUEST['edit_username']));

		$this->html['shipon_delete_output'] = '<script type="text/javascript">
                                                            jQuery("#shipon_account_edit_popup").dialog("close");
                                                            change_view("manage_accounts");
							</script>';
	}

	function delete_account()
	{
		if($this->user['type'] != 1)
			return 0;

		query("UPDATE `".$this->user['database']."`.accounts SET active=0 WHERE id=?", array($_REQUEST['user_id']));

		$this->html['shipon_delete_output'] = '<script type="text/javascript">
                                                            jQuery("#shipon_account_delete_popup").dialog("close");
                                                            change_view("manage_accounts");
							</script>';
	}
}

?>
