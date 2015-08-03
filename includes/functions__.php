<?php

function text($tag)
{
	$text = query("SELECT ".$_REQUEST['lang']." FROM localization WHERE tag='".$tag."' LIMIT 1");

	if(num_rows($text))
	{
		$text = fetch($text);
		return $text[$_REQUEST['lang']];
	}
	else
		return $tag;
}

function format_address_array(&$return_array, $query_result, $attribute)
{
	if(num_rows($query_result))
	{
		$address = fetch($query_result);

		$keys = array_keys($address);
		for($i = 0; $i < count($keys); $i++)
		{
			$new_key = $attribute.$keys[$i];
			$return_array[$new_key] = $address[$keys[$i]];
		}
	}
}

function handle_bill_address($info)
{
	//verify_address($error);
	//if($error) return $error;

	if(find_similar_addresses($info, $id))
		update_address($info, $id);
	else
		$id = create_new_address($info);

	return $id;
}

function autosave_address($info, &$address = NULL)
{
	format_address($address);

	verify_address($error, $address);
	if($error) return $error;
	
	$save_type = isset($address['save_type']) ? $address['save_type'] : 0;
	switch($save_type)
	{
		case 'new':
			if(find_similar_addresses($info, $address))
				return "Similar address found.";
			else
				create_new_address($info, $address);
		break;

		case 'edit':
			update_address($info, $address);
		break;
		
		default:
			if(find_similar_addresses($info, $address))
				update_address($info, $address);
			else
				create_new_address($info, $address);
	}

	return true;
}

function find_similar_addresses(&$info, &$address)
{
	$addresses_result = query("SELECT * FROM `".$info['database']."`.address_book WHERE street1 = '".$address['street1']."' AND city = '".$address['city']."' AND group_id = '".$info['group']."'");
	
	$found_similar = false;
	while($address_data = fetch($addresses_result))
	{
		if($address_data['name'] == $address['name'])
		{
			$found_similar = true;
			$address['id'] = $address_data['id'];
		}
	}

	return $found_similar;
}

function format_address(&$address = NULL)
{
	if($address == NULL)
	{
		$address = array();
		$address['name'] = $_REQUEST['name'];
		$address['contact'] = $_REQUEST['contact'];
		$address['phone'] = $_REQUEST['phone'];
		$address['ext'] = $_REQUEST['ext'];
		$address['email'] = $_REQUEST['email'];
		$address['street1'] = $_REQUEST['street1'];
		$address['street2'] = $_REQUEST['street2'];
		$address['city'] = $_REQUEST['city'];
		$address['province'] = $_REQUEST['province'];
		$address['country'] = $_REQUEST['country'];
		$address['postal'] = $_REQUEST['postal'];
	}
	
	if(isset($_REQUEST['address_id'])) $address['id'] = $_REQUEST['address_id'];
	if(isset($_REQUEST['save_type'])) $address['save_type'] = $_REQUEST['save_type'];
}

function create_new_address(&$info, &$address)
{
	$address['id'] = query_r("INSERT INTO `".$info['database']."`.address_book
								(
										group_id,
										name,
										contact,
										phone,
										ext,
										email,
										street1,
										street2,
										city,
										province,
										country,
										postal
								)
								VALUES
								(
									'".$info['group']."',
									'".$address['name']."',
									'".$address['contact']."',
									'".$address['phone']."',
									'".$address['ext']."',
									'".$address['email']."',
									'".$address['street1']."',
									'".$address['street2']."',
									'".$address['city']."',
									'".$address['province']."',
									'".$address['country']."',
									'".$address['postal']."'
								)
							");
}

function update_address(&$info, &$address)
{
	query( "UPDATE `".$info['database']."`.address_book
			SET
				name='".$address['name']."',
				contact='".$address['contact']."',
				phone='".$address['phone']."',
				email='".$address['email']."',
				ext='".$address['ext']."',
				street1='".$address['street1']."',
				street2='".$address['street2']."',
				city='".$address['city']."',
				province='".$address['province']."',
				country='".$address['country']."',
				postal='".$address['postal']."'
			WHERE
				id='".$address['id']."'
		");
}

function verify_address(&$error, &$address)
{
	if(!isset($address['name']) or strlen($address['name']) < 4)
		$error = "Invalid Name";
	else if(!isset($address['phone']) or strlen($address['phone']) < 14)
		$error = "Invalid Phone";
	else if(!isset($address['email']) or !filter_var($address['email'], FILTER_VALIDATE_EMAIL))
		$error = "Invalid Email";
	else if(!isset($address['street1']) or strlen($address['street1']) < 6)
		$error = "Invalid Address";
	else if(!isset($address['city']) or strlen($address['city']) < 3)
		$error = "Invalid City";
	else if(!isset($address['province']) or strlen($address['province']) < 2)
		$error = "Invalid State";
	else if(!isset($address['postal']) or strlen($address['postal']) < 6)
		$error = "Invalid Postal";
}

?>