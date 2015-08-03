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
            if(find_similar_addresses($info, $address)) { 
				update_address($info, $address);
                        }        
			else { 
				create_new_address($info, $address);
                        }        
	}

	return true;
}

function find_similar_addresses(&$info, &$address)
{
        $addressObj = new address($info['database']);
	$addressObj->group_id = $info['group'];
        $addressObj->street1  = $address['street1'];
        $addressObj->city     = $address['city']; 
        $address_data         = $addressObj->Find();
	//$addresses_result = query("SELECT * FROM `".$info['database']."`.address_book WHERE street1 = '".$address['street1']."' AND city = '".$address['city']."' AND group_id = '".$info['group']."'");

	$found_similar = false;
       
        if ($address_data != 0) {
            foreach($address_data as $address_result)
            {      
                    if($address_result['name'] == $address['name'])
                    {       
                            $found_similar = true;
                            $address['id'] = $address_result['id'];
                    }
            }
        }  
	return $found_similar;
}

function format_address(&$address = NULL)
{
        if (isset($_REQUEST['province'])) {
            $_REQUEST['prov'] = $_REQUEST['province'];
        }        
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
        $add = new address($info['database']);
        $add->group_id = $info['group']; 
        $add->name = $address['name'];
        $add->contact = $address['contact'];
        $add->phone = $address['phone'];
        $add->email = $address['email'];
        $add->ext = $address['ext'];
        $add->street1 = $address['street1'];
        $add->street2 = $address['street2'];
        $add->city = $address['city'];
        $add->province = $address['province'];
        $add->country = $address['country'];
        $add->postal = $address['postal'];
        $add->Create();
}

function update_address(&$info, &$address)
{
        $add = new address($info['database'],$address['id']);
        $add->name = $address['name'];
        $add->phone = $address['phone'];
        $add->email = $address['email'];
        $add->contact = $address['contact'];
        $add->ext = $address['ext'];
        $add->street1 = $address['street1'];
        $add->street2 = $address['street2'];
        $add->city = $address['city'];
        $add->province = $address['province'];
        $add->country = $address['country'];
        $add->postal = $address['postal'];
        $add->Update();
}

function verify_address(&$error, &$address)
{
	if(!isset($address['name']) or strlen($address['name']) < 4)
		$error = "Invalid Name";
//	else if(!isset($address['phone']) or strlen($address['phone']) < 14)
//		$error = "Invalid Phone";
//	else if(!isset($address['email']) or !filter_var($address['email'], FILTER_VALIDATE_EMAIL))
//		$error = "Invalid Email";
	else if(!isset($address['street1']) or strlen($address['street1']) < 6)
		$error = "Invalid Address";
	else if(!isset($address['city']) or strlen($address['city']) < 3)
		$error = "Invalid City";
	else if(!isset($address['province']) or strlen($address['province']) < 2)
		$error = "Invalid State";
	else if(!isset($address['postal']) or strlen($address['postal']) < 5)
		$error = "Invalid Postal";
}

?>
