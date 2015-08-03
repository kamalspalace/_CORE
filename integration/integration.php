<?php

class IntegrationHandler
{
	var $send_func;
	var $integration;
	var $user;
	
	function __construct(&$user)
	{
		$integration_result = query("SELECT * FROM integration WHERE client=?",array($_REQUEST['user']));
		$this->integration = fetch($integration_result);
		$this->send_func = $this->integration['method'];
		$this->user = $user;
		
		require_once("integration/".$this->integration['type']."/".$this->integration['integration'].".php");
		require_once($this->user['folder']."/integration/".$this->integration['integration'].".php");
	}
	
	function process_request($action,&$object = NULL,&$session = NULL)
	{
		$return = array();
                
 		if(method_exists($this,$action))
		  $data = $this->$action();
		else
		  $data = "";
		
		$client_integration = new ClientIntegrationHandler($this->user, $action);
		$send = $client_integration->handle_integration($data, $return);

		if($send)
		{
			$send_func = $this->send_func;
			$integrate = new Integrate($this->user);
	        	$integrate->client        = $this->integration['client'];
	        	$integrate->proto         = $this->integration['type'];
	        	$integrate->integration   = $this->integration['integration'];
	        	$integrate->custom_link_1 = $this->integration['custom_link_1'];
	        	$integrate->custom_link_2 = $this->integration['custom_link_2'];

			if($object)
			  $integrate->object      = $object;

			if($session)
			  $integrate->session 	  = $session;

            		$integrate->$action($data);
			$result = $integrate->$send_func($this->integration);
			
			$merge = $action."_merge";
			if(method_exists($client_integration, $merge))
				$return = $client_integration->$merge($return, $result, $data);
			else
				$return = $result;
		}
		return $return;
	}

	function rate_request()
	{
		$post = $_POST;
		
		$addresses = array();
		$goods = array();
		
		foreach ($post as $key => $value)
		{
			if(strpos($key, 'shipon') === false)
			{
				unset($post[$key]);
				continue;
			}
			
			if (strpos($key, 'shipon_consignee') === 0) 
			{
				$key_array = explode('_', $key);
				$addresses['consignee'][$key_array[count($key_array) - 1]] = $value;
				unset($post[$key]);
			}
			if (strpos($key, 'shipon_shipper') === 0) 
			{
				$key_array = explode('_', $key);
				$addresses['shipper'][$key_array[count($key_array) - 1]] = $value;
				unset($post[$key]);
			}
			if(strpos($key, 'shipon_goods') === 0)
			{
				$key_array = explode('_', $key);
				$good_name_array = explode('-', $key_array[2]);
				$good = $good_name_array[0];
				$i = $good_name_array[1];
				$goods[$i][$good] = $value;
				unset($post[$key]);
			}
		}
		unset($post['shipon_freight_charges']);
		
		$bill_code_result = query("SELECT bill_to_code FROM `".$this->user['database']."`.account_groups WHERE id='".$this->user['group']."'");
		$bill_code = fetch($bill_code_result);
		$addresses['payer'] = $bill_code;
		
		$data = array_merge($addresses, $post);
		$data['goods'] = $goods;
		
		return $data;
	}
	
	function handshake()
	{
		$bill_code_result = query("SELECT bill_to_code FROM `".$this->user['database']."`.account_groups WHERE id='".$this->user['group']."'");
		$bill_code = fetch($bill_code_result);
		
		return $bill_code['bill_to_code'];
	}
	
	function tracking_list_request()
	{

		$bill_code_result = query("SELECT bill_to_code FROM `".$this->user['database']."`.account_groups WHERE id='".$this->user['group']."'");
		$bill_code = fetch($bill_code_result);
		
		$data = array();
		
		$two_weeks_ago = mktime(0,0,0,date("m"),date("d")-14,date("Y"));
		if(!isset($_REQUEST['start_date'])) $_REQUEST['start_date'] = date('m/d/Y', $two_weeks_ago);
		if(!isset($_REQUEST['end_date'])) $_REQUEST['end_date'] = date('m/d/Y');
		if(!isset($_REQUEST['search_string'])) $_REQUEST['search_string'] = "";
		if(!isset($_REQUEST['sort_by'])) $_REQUEST['sort_by'] = "ProDate";
		if(!isset($_REQUEST['sort_order'])) $_REQUEST['sort_order'] = "DESC";
		
		$data['start_date'] = $_REQUEST['start_date'];
		$data['end_date'] = $_REQUEST['end_date'];
		$data['bill_to_code'] = $bill_code['bill_to_code'];
		$data['page'] = isset($_REQUEST['data']) ? $_REQUEST['data'] : 0;
		$data['sort_by'] = $_REQUEST['sort_by'];
		$data['order_by'] = $_REQUEST['sort_order'];
		$data['entries_per_page'] = intval($xml->entries_per_page);
		$data['columns'] = $columns;
		$data['search_string'] = $_REQUEST['search_string'];

		return $data;


	}
	
	function tracking_details_request()
	{
		$data = array();
	
		$bill_code_result = query("SELECT bill_to_code FROM `".$this->user['database']."`.account_groups WHERE id='".$this->user['group']."'");
		$bill_code = fetch($bill_code_result);
		
		$data['pbnum'] = $_REQUEST['pbnum'];		
		$data['columns'] = $columns;
		return $data;
	}

	/*function order_details_request()
	{
		$data = array();	
		return $data;
	}*/
        
        function get_location_request() {
            $data['pbnum'] = $_REQUEST['pbnum'];
            return $data;
        }
}

?>
