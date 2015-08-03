<?php

class Integrate
{
	var $request;
	var $response;
	var $user;
	var $client;
	var $object;
	var $proto;
	var $smarty;
	var $session;
	var $integration;
	var $sock_timeout;
	var $custom_link_1;
	var $custom_link_2;
	var $custom_link_3;
	var $custom_link_4;


	var $response_function;

	function __construct(&$user)
	{
		$this->request = new SimpleXMLElement("<REQUEST></REQUEST>");
		$this->user = $user;
		$this->smarty = new SmartyXml($this->user['folder'],"degama");
	}

	function send_socket_request($request = '')
	{
		global $error_handler;
		
		$id = query_r("INSERT INTO 
			       integration_tx 
			       (client,protocol,integration,method) 
			       VALUES 
			       ('".$this->client."',
				'".$this->proto."',
				'".$this->integration."', 
				'send_socket_request')");

		if($this->request != "ping")
		  $this->request->addChild('trans_id', $id);

		$service_port = $this->custom_link_2;
		$address = $this->custom_link_1;
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		
		if(! empty($this->sock_timeout))
		  socket_set_option($socket,SOL_SOCKET,SO_SNDTIMEO, array('sec' => $this->sock_timeout, 'usec' => 0));
		else {
		  socket_set_option($socket,SOL_SOCKET,SO_SNDTIMEO, array('sec' => 120, 'usec' => 120));
		  socket_set_option($socket,SOL_SOCKET,SO_RCVTIMEO, array('sec' => 120, 'usec' => 120));
		}

		if ($socket === false)
		{
                        $message = "socket_create() failed: ".socket_strerror(socket_last_error());
                        $messageString = "<RESPONSE><HANDSHAKE><MESSAGE>" . $message . "</MESSAGE><STATUS>0</STATUS></HANDSHAKE></RESPONSE>";
                        $this->response = new SimpleXMLElement($messageString);
                        $error_handler->handle_integration_error($message, $this->request->asXML());
                        
                        return $this->response;
		}
		else
		{
			$result = socket_connect($socket, $address, $service_port);

			if (! $result)
			{
				$message = "socket_connect() failed ($result): ".socket_strerror(socket_last_error($socket));
				$messageString = "<RESPONSE><HANDSHAKE><MESSAGE>" . $message . "</MESSAGE><STATUS>0</STATUS></HANDSHAKE></RESPONSE>";
				$this->response = false;
				if($this->request != "ping")
				  $error_handler->handle_integration_error($message, $this->request->asXML());
				else
				  $error_handler->handle_integration_error($message, "ping");
				return false;
			}
			else
			{
				if($this->request != "ping")
				  $request = $this->request->asXML();
				else
				  $request = "ping\n\r\0";
 
				socket_write($socket, $request, strlen($request));

				$this->response = "";
				$end = false;
				do 
				{ 
					$recv = socket_read($socket, '200');
					$this->response .= mb_convert_encoding($recv,'UTF-8');
					
					if(strpos($this->response, '</RESPONSE>') > 0 || strpos($this->response, 'Pong! ') > 0)
						$end = true;
				} 
				while($end == false);

				socket_close($socket);
			}
		}

		//$this->response = substr($this->response, 0, -1);
		//$this->response = addslashes($this->response);

		//$this->response = "INTERNAL TEST ONLY! NO SOCKET CONNECTION WAS MADE!";
		
		//print(strlen($this->response).$this->response);
		
		query("INSERT INTO `".$this->user['database']."`.integration_tx (trans_id, request, response) VALUES ('".$id."',?,?)", array($request, $this->response));

		$response_function = $this->response_function;
		return $this->$response_function();
	}

    function ping(&$data)
    {
	$this->sock_timeout = 10;
	$this->response_function = "ping_response";
	$this->request = "ping";
    }

    function ping_response()
    {
      if($this->response == false)
        return false;
      else
	return true;
    }
	 
    function rate_request(&$data)
    {
	$shipment = $this->object->toArray();
	foreach($shipment as $col => $val)
	{
	  if($col == 'pup_date' || $col == 'del_date') continue;
	  if(strpos((string)$val, '&') !== FALSE || 
	     strpos((string)$val, '^') !== FALSE) 
	    $shipment[$col] = htmlentities(is_string($val) ? $this->clean_french($val) : $val);
	  else
	    $shipment[$col] = is_string($val) ? $this->clean_french($val) : $val;
	}

	$g = 0;
	foreach($shipment['goods'] as $good) {
	  foreach($good as $gcol => $gval) {
	
	  // DTMS doesn't understand the metric system so we convert to imperial
	  if($shipment['uom'] == 'metric') {
		switch($gcol) {

		  case "weight":
		    $gval = floatval($gval * 2.2);
		  break;

		  case "length":
		  case "width":
		  case "height":
		    $gval = floatval($gval * 0.393701);
		  break;
		}

	  }

          // Parse out illegal characters
          if(strpos((string)$gval, '&') !== FALSE ||
             strpos((string)$gval, '^') !== FALSE)
	    $shipment['goods'][$g][$gcol] = htmlentities(is_string($gval) ? $this->clean_french($gval) : $gval);
	  else
	    $shipment['goods'][$g][$gcol] = is_string($gval) ? $this->clean_french($gval) : $gval;
	  }
	  $g++;
	}

        $this->smarty->assign('shipment',$shipment);
        $this->smarty->assign('session', $this->session->toArray());
        $this->request = new SimpleXMLElement($this->smarty->fetch("rate_request.xml"));
	$this->response_function = "rate_response";
    }

    function rate_response()
    {
		global $error_handler;
        $search = array(chr(0), chr(1), chr(2), chr(26));
        $xml = new SimpleXMLElement(str_replace($search, '', $this->response));
		
		if($xml->NEW_ORDER->ERROR->count())
		{$error_handler->handle_integration_error($xml->NEW_ORDER->ERROR->error_message, $this->request->asXML(),1); return false;}
	

		$shipment = array();
		$shipment['pbnum'] = (string)$xml->NEW_ORDER->pbnum;
		$shipment['alpha'] = (string)strtoupper($xml->NEW_ORDER->AlphaTrackingNo);
		$shipment['bill_account'] = (string)$xml->NEW_ORDER->bill_to_code;


		if(! $this->object->ext_id)
		{
			$this->object->ext_id = $shipment['pbnum'];
			$this->object->ext_alpha = $shipment['alpha'];
			$this->object->Update();
		}

		
		$this->smarty->assign('shipment',$shipment);
		$this->request = new SimpleXMLElement($this->smarty->fetch("rate_details.xml"));
		$this->response_function = "tracking_details_response";
	        $this->send_socket_request();

		return $this->tracking_details_response();
    }	

	function add_child(&$xml, $tag, &$data, $key)
	{
		if(isset($data[$key]) && $data[$key] != '') 
		{
			if($data[$key] == 'on') $data[$key] = '1';

			if(isset($xml->$tag))
				$xml->$tag = $data[$key];
			else
				$xml->addChild($tag,$data[$key]);
		}
	}
	
	function quote_to_order(&$data)
	{
		if(gettype($data) != 'array') // POST VAR
			$this->smarty->assign('shipment',$this->object->toArray());
		else 
			$this->smarty->assign('shipment',$this->object);
    	$this->request = new SimpleXMLElement($this->smarty->fetch("quote_to_order.xml"));
    	$this->response_function = "get_qto_response";
	}

	function get_qto_response()
	{
	    $search = array(chr(0), chr(1), chr(2), chr(26));
	    $xml = new SimpleXMLElement(str_replace($search, '', $this->response));
	    $pbnum = $xml->SHIPMENT->pbnum;
	    return $pbnum;
	}
	
	function handshake($btc = '')
	{
		if($btc != '') {
			if(gettype($this->object) == 'string')
				$bill_to_code = $this->object;
			else
				$bill_to_code = $this->user['bill_to_code'];
		}
		else
			$bill_to_code = $btc;

		$this->smarty->assign('bill_to_code', $bill_to_code);
		$this->request = new SimpleXMLElement($this->smarty->fetch("handshake.xml"));
		$this->response_function = "get_handshake_response";
	}
	
	function get_handshake_response()
	{
         	$search = array(chr(0), chr(1), chr(2), chr(26));
         	$xml = new SimpleXMLElement(str_replace($search, '', $this->response));

         	return new SimpleXMLElement($xml->HANDSHAKE->asXML());
	}
	
	function tracking_list_request(&$data)
	{
		$this->smarty->assign('request',$this->object);
        	$this->request = new SimpleXMLElement($this->smarty->fetch("tracking_list.xml"));
		$this->response_function = "tracking_list_response";
	}
	
	function tracking_list_response()
	{
		global $error_handler;
		
		$search  = array(chr(0), chr(1), chr(2),'Rec-Status','ConAdd-1','Sh-BOL',chr(26));
		$replace = array('', '', '', 'RecStatus','ConAdd1','ShBOL','');
		$xml = new SimpleXMLElement(str_replace($search, $replace, $this->response));
		
//		if($xml->TRACKING_LIST->ERROR->count())
//		{$error_handler->handle_integration_error($xml->TRACKING_LIST->ERROR->error_message, $this->request->asXML()); return array('count' => 0, 'orders' => array());}
		

          if($xml->TRACKING_LIST->ERROR->count())
          {
			if(isset($this->object['resultset']))
					{
			  $return['count']  = $this->object['resultset'];
			  $return['orders'] = array();
			  return $return;
				}
			else { 
					  $return['count']  = 0;
					  $return['orders'] = array();
					  return $return;
			}
          }


		$return = array();
		$orders = $xml->TRACKING_LIST->Orders->children();
		$order_count = $xml->TRACKING_LIST->count;
		$return['count'] = $order_count;
		$return['orders'] = array();
		
		foreach($orders as $order)
		{
			if($order->getName() == 'Order')
			{
				$this_order = array();
				foreach($order->children() as $column => $value)
				{
					if($column == 'Tot')
					  $this_order[$column] = number_format(floatval($value),2,'.',''); 
					elseif($column == 'DelTime')
					  $this_order[$column] = $this->format_time_response((string)$value);
					elseif($column == 'City')
					  $this_order[$column] = str_replace('\\','',(string)$value);
					elseif($column == 'ConCity') 
					  $this_order[$column] = str_replace('\\','',(string)$value);
					else
					  $this_order[$column] = stripslashes(stripslashes((string)$value)); // this is stupid
				}
				$return['orders'][] = $this_order;
			}
		}
		return $return;
	}

	function order_details_request(&$data)
	{
        	$this->smarty->assign('bill_to_code',$this->object['bill_to_code']);
                $this->smarty->assign('pbnum',$this->object['pbnum']);
		$this->request = new SimpleXMLElement($this->smarty->fetch("tracking_details.xml"));
                $this->response_function = "order_details_response";
	}
	
	function tracking_details_request(&$data)
	{
        $this->smarty->assign('bill_to_code',$this->object['bill_to_code']);
		$this->smarty->assign('pbnum',$this->object['Pbnum']);
		$this->smarty->assign('alpha',$this->object['Alpha']);
        $this->request = new SimpleXMLElement($this->smarty->fetch("tracking_details.xml"));
		$this->response_function = "tracking_details_response";
	}

	function order_details_response()
	{
		global $error_handler;

		// Retrieve order info from DTMS
		$response = $this->tracking_details_response();

		// Prevent order numbers from being inputted sequentially
		if($response == false)
		  return false;

		$data = array();


		// DATA
		$data['ext_id']       = (string)$response['Pbnum'];
		$data['division']     = (string)$response['Division'];
		$data['service']      = (string)$response['Servlvl'];
		$data['bill_account'] = (string)$response['Code'];
		$data['reference']    = empty($response['RefNumb']) ? '' : $response['RefNumb'];
		$data['pup_date']     = (string)$response['PupDate'];
		$data['pup_time']     = ($response['paptime'] == "0") ? '' : $this->format_time_response($response['paptime']);
		$data['pup_note']     = empty($response['AppointNo']) ? '' : $response['AppointNo'];
                $data['pup_area']     = empty($response['routeCode']) ? '' : (string)$response['routeCode'];
		$data['del_date']     = ($response['DapDate'] == "0") ? '' : $response['DapDate']; 
		$data['delivered']    = $response['DelDate'];
		$data['del_note']     = empty($response['delinst']) ? '' : (string)$response['delinst'];
		$data['del_time_to']  = ($response['DelTime'] == "0") ? '' : $this->format_time_response($response['DelTime']);
		$data['del_time_from']= '';
		$data['del_area']     = empty($response['delRouteCode']) ? '' : (string)$response['delRouteCode'];
		$data['total_weight'] = empty($response['Weight']) ? '' : (string)$response['Weight'];
		$data['total_pieces'] = (string)$response['Pieces'];
		$data['skids']	      = empty($response['Skid']) ? '' : $response['Skid'];
		$data['uom_weight']   = '';
		$data['dec_value']    = number_format((string)$response['declaredValue'],2,'.','');
		$data['equipment']    = empty($response['Equipment']) ? '' : $response['Equipment']; 
		$data['temp_value']   = empty($response['Temperature']) ? '' : (string)$response['Temperature'];
		$data['temp_level']   = '';
		$data['temp_unit']    = '';
		$data['cust_broker']  = empty($response['cmbroker']) ? '' : (string)$response['cmbroker'];
		$data['dg_un']	      = empty($response['Ticket']) ? '' : $response['Ticket'];
		$data['service_ret']  = '';
		$data['cargo_scn']    = empty($response['cargoNo']) ? '' : $response['cargoNo'];
		$data['timestamp']    = $response['ProDate'];
		$data['rec_status']   = $response['RecStatus'];
		$data['signature']    = $response['Signature'];

		// SHIPPER
		$data['ship_name']    = (string)$response['ShipName'];
		$data['ship_street1'] = (string)$response['Add1'];
		$data['ship_street2'] = empty($response['Add2']) ? '' : $response['Add2'];
		$data['ship_city']    = (string)$response['City'];
		$data['ship_postal']  = (string)$response['Postal'];
		$data['ship_province']= (string)$response['Prv'];
		$data['ship_contact'] = empty($response['RefName']) ? '' : $response['RefName'];
		$data['ship_phone']   = empty($response['Tel']) ? '' : $response['Tel'];
		$data['ship_country'] = '';

		// CONSIGNEE
		$data['cons_name']    = (string)$response['ConsigName'];
		$data['cons_street1'] = (string)$response['ConAdd1'];
		$data['cons_street2'] = empty($response['ConAdd2']) ? '' : $response['ConAdd2'];
		$data['cons_city']    = (string)$response['ConCity'];
		$data['cons_postal']  = (string)$response['ConPostal'];
		$data['cons_province']= (string)$response['ConPrv'];
		$data['cons_contact'] = empty($response['ConRefName']) ? '' : $response['ConRefName'];
		$data['cons_phone']   = empty($response['Contel']) ? '' : $response['Contel'];
		$data['cons_country'] = '';

		$exists_in_sql = query("SELECT * FROM `".$this->user['database']."`.shipments WHERE sent=1 AND ext_id=?",array($data['ext_id']));
		$local_bill_to = fetch($exists_in_sql);

		if(! empty($local_bill_to))
		{
                	$data['bill_name']    = $local_bill_to['bill_name'];
                	$data['bill_street1'] = $local_bill_to['bill_street1'];
                	$data['bill_street2'] = $local_bill_to['bill_street2'];
                	$data['bill_city']    = $local_bill_to['bill_city'];
                	$data['bill_province']= $local_bill_to['bill_province'];
                	$data['bill_postal']  = $local_bill_to['bill_postal'];
                	$data['bill_contact'] = $local_bill_to['bill_contact'];
                	$data['bill_phone']   = $local_bill_to['bill_phone'];
                	$data['bill_country'] = $local_bill_to['bill_country'];
		}
		else
		{
			// Fetch debtor info
			unset($this->object);
			$this->handshake($data['bill_account']);
			$this->send_socket_request(handshake);
			$handshake = $this->get_handshake_response();	

			// BILLER aka DEBTOR
			$data['bill_name']    = (string)$handshake->Name;
			$data['bill_street1'] = (string)$handshake->Address;
			$data['bill_street2'] = empty($handshake['Addres2']) ? '' : (string)$handshake['Addres2'];
			$data['bill_city']    = (string)$handshake->City;
			$data['bill_province']= (string)$handshake->Province;
			$data['bill_postal']  = (string)$handshake->Postal;
			$data['bill_contact'] = empty($handshake->Contact) ? '' : (string)$handshake->Contact;
			$data['bill_phone']   = (string)$handshake->Phone;
			$data['bill_country'] = '';
		}


		// GOODS
		if(isset($response['RateDetails']['Rates']['Rate'][0])) {
		   $dg_set = 0;
		   $total = 0;
		   for($g = 0; $g < count($response['RateDetails']['Rates']['Rate']); $g++) {

			if($g == 0) {
			  $data['goods'][$g]['pieces']   = $response['Pieces'];
			  $data['goods'][$g]['weight']    = $response['RateDetails']['Rates']['Rate'][$g]['Weight'];
			}
			else {
			  if(is_numeric($response['RateDetails']['Rates']['Rate'][$g]['Quant']))
			    $data['goods'][$g]['pieces']    = $response['RateDetails']['Rates']['Rate'][$g]['Quant'];
			  else
			    $data['goods'][$g]['pieces']    = '';
			  $data['goods'][$g]['weight']    = '-';	
			}

			if(floatval($response['RateDetails']['Rates']['Rate'][$g]['Rate']) > 0) {
			   $data['goods'][$g]['rate'] = $response['RateDetails']['Rates']['Rate'][$g]['Rate'];
			   $data['goods'][$g]['total']  = $response['RateDetails']['Rates']['Rate'][$g]['Tot'];
			   $total += $response['RateDetails']['Rates']['Rate'][$g]['Tot'];
			}

			$search  = array('<ComDescr>','</ComDescr>');
                	$data['goods'][$g]['particulars'] = str_replace($search,'',$response['RateDetails']['Rates']['Rate'][$g]['Descr1']);

			if(empty($response['RateDetails']['Rates']['Rate'][$g]['Code'])) {
			  $data['goods'][$g]['commodity'] = 'COMMENT';
			  $data['goods'][$g]['weight']    = '-';
			}
			else {
			  $data['goods'][$g]['commodity'] = $response['RateDetails']['Rates']['Rate']['Code'];
			}

                        if($data['goods'][$g]['particulars'] == 'Weight Lbs') {
                          $data['goods'][$g]['commodity'] = 'COMMENT';
                        }

			if($response['RateDetails']['Rates']['Rate'][$g]['Code'] == 'RES')
			  $data['goods'][$g]['pieces']    = '-';
		   }
		}
		else {
		   $data['goods'][0]['commodity']   = $response['RateDetails']['ComCode'];
		   $data['goods'][0]['particulars'] = $response['RateDetails']['ComDescr'];
		   $data['goods'][0]['pieces']      = $response['Pieces'];
		   $data['goods'][0]['weight']      = $response['Weight'];
		}

		$data['tax']   = number_format((string)$response['Gstamt'],2,'.','');
		$data['fsc']   = number_format((string)$response['Surcharge'],2,'.','');
		$data['total'] = number_format((string)($total + $data['tax'] + $data['fsc']),2,'.','');

		return $data;
	}

	
	function tracking_details_response()
	{
		global $error_handler;
		
		$search = array(chr(0), chr(1), chr(2), chr(7),'Add-1','Add-2','ConAdd-1','ConAdd-2','Rec-Status','Sh-BOL','Appt-Rqd',chr(26));
		$replace = array('','','','','Add1','Add2','ConAdd1','ConAdd2','RecStatus','ShBOL','ApptRqd','');
		$xml = new SimpleXMLElement(str_replace($search, $replace, $this->response));
		$return = array();
		
		if($xml->TRACKING_DETAILS->ERROR->count())
			return false;

		// JR: do not delete. This fixes the broken XML that Degama sends us
		// and formats it so that the shows the right calculations and numbers.
		$FirstRate   = new SimpleXMLElement($xml->TRACKING_DETAILS->Order->RateDetails->asXML());
		$newRatesXml = new SimpleXMLElement("<rates></rates>");
		$newRatesXml->addChild('Rate');
		$newRatesXml->Rate->addChild('Line',0);
		$newRatesXml->Rate->addChild('Code',(string)$FirstRate->ComCode);
		$newRatesXml->Rate->addChild('Descr1',htmlspecialchars($FirstRate->ComDescr->asXML()));
		$newRatesXml->Rate->addChild('Quant',floatval($FirstRate->Quant) == '0' ? '-' : floatval($FirstRate->Quant));
		$newRatesXml->Rate->addChild('Weight',floatval($FirstRate->Weight) == '0' ? floatval($xml->TRACKING_DETAILS->Order->Weight) : floatval($FirstRate->Weight));
		$newRatesXml->Rate->addChild('Rate',number_format(floatval($FirstRate->Rate), 2, '.', ''));
		$newRatesXml->Rate->addChild('Uom',(string)$FirstRate->Uom);
		$newRatesXml->Rate->addChild('Tot',number_format(floatval($FirstRate->Tot), 2, '.',''));
		$newRatesXml->Rate->addChild('Surcharge',number_format(floatval($FirstRate->Surcharge), 2, '.', ''));
		$newTotalXml = floatval($FirstRate->Tot);
		$newSubtotal = floatval($FirstRate->Tot);
	
		$StatusLog = new SimpleXMLElement($xml->TRACKING_DETAILS->Order->StatusLog->asXML());
		unset($xml->TRACKING_DETAILS->Order->StatusLog);
 
		$newStatusLog = $xml->TRACKING_DETAILS->Order->addChild('StatusLog');

		foreach($StatusLog->Status as $status)
		{	
		  $ns = $newStatusLog->addChild('Status');
		
		  foreach($status->children() as $child)
		  {
		    if($child->getName() == 'Tm')
			$ns->addChild($child->getName(),$this->format_time_response($child));
		    else
			$ns->addChild($child->getName(),htmlspecialchars($child));
  		  }
		}

		foreach($xml->TRACKING_DETAILS->Order->RateDetails->Rates->Rate as $rate)
		{
		  $nr = $newRatesXml->addChild('Rate');

		  $newTotalXml = $newTotalXml + floatval($rate->Tot);
		  $newSubtotal = $newSubtotal + floatval($rate->Tot);

		  foreach($rate->children() as $child)
		  {
		    if($child->getName() == 'Rate' || $child->getName() == 'Tot')
		      $nr->addChild($child->getName(),number_format(floatval($child),2,'.',''));
		    elseif($child->getName() == 'Quant' && $child == '0')
		      $nr->addChild($child->getName(),'-');
		    else
		      $nr->addChild($child->getName(),$child);	
		  }

                  if($rate->Code == '' && (empty($rate->Tot) || floatval($rate->Tot) == 0))
                  {
		     $nr->Rate   = '';
		     $nr->Tot    = '';
		     $nr->Quant  = '';
		     $nr->Weight = '';
		  }
		}

		unset($xml->TRACKING_DETAILS->Order->RateDetails->Rates);
		$rates = $xml->TRACKING_DETAILS->Order->RateDetails->addChild('Rates');

		foreach($newRatesXml->Rate as $rate)
		{
		  $nr = $rates->addChild('Rate',$rate);
		  foreach($rate->children() as $child)
		    $nr->addChild($child->getName(),$child);
		}



		$newTotalXml = $newTotalXml + floatval($xml->TRACKING_DETAILS->Order->Gstamt) + floatval($FirstRate->Surcharge);

		$xml->TRACKING_DETAILS->Order->RateDetails->Surcharge = number_format(floatval($xml->TRACKING_DETAILS->Order->RateDetails->Surcharge),2,'.','');
		$xml->TRACKING_DETAILS->Order->Tot = number_format($newTotalXml,2,'.','');
		$xml->TRACKING_DETAILS->Order->RateDetails->Tot = number_format($newTotalXml,2,'.','');

		$xml->TRACKING_DETAILS->Order->RateDetails->Subtotal = number_format($newSubtotal + floatVal($xml->TRACKING_DETAILS->Order->RateDetails->Surcharge),2,'.','');		
		
		$xml->TRACKING_DETAILS->Order->RateDetails->Gstamt = number_format(floatval($xml->TRACKING_DETAILS->Order->RateDetails->Gstamt),2,'.','');


		$details = $xml->TRACKING_DETAILS->Order->asXML();
	
		$return = $this->to_json_array($details);
		
		return $return;
	}
	
	function to_array(&$xml)
	{
		$return = array();
		
		foreach($xml as $child)
		{
			if($child->count() > 0)
			{
				$children = $child->children();
				$return[$child->getName()] = $this->to_array($children);
			}
			else
				$return[$child->getName()] = (string)$child;
		}
		
		return $return;
	}
	
	function to_json_array(&$xml)
	{
		return json_decode(json_encode((array) simplexml_load_string($xml)), 1);
	}
	
	function handle_error()
	{
		
	}
        
	function get_location_request($data)
	{
		$this->smarty->assign('pbnum',$data['pbnum']);
                $this->request = new SimpleXMLElement($this->smarty->fetch("get_location.xml"));
		$this->response_function = "get_location_response";
	}

	function format_time_response($time_string)
	{
	  $time = explode('.',$time_string);
	
	  $hour = $time[0];

	  if($time[0] == '')
	    $hour = '12';
	  elseif(strlen($time[0]) == 1)
	    $hour = '0'.$time[0];
	  else
	    $hour = $time[0];	
 
	  if(strlen($time_string) == 2)
	    $min = '00';
	  elseif(strlen($time[1]) <= 1 && strlen($time_string) == 1)
	    $min = $time[1].'00';
	  elseif(strlen($time[1]) <= 1)
	    $min = $time[1].'0';
	  else
	    $min = $time[1];

	  return $hour.':'.$min;
	}
        
        function get_location_response() 
        {
		global $error_handler;

                $search = array(chr(0), chr(1), chr(2), chr(26));
                $xml = new SimpleXMLElement(str_replace($search, '', $this->response));

                if($xml->GET_LOCATION->ERROR->count())
                {$error_handler->handle_integration_error($xml->GET_LOCATION->ERROR->error_message, $this->request->asXML()); } //return false;}
                
                $location = array();
                $location['pbnum'] = 5689; //(int)$xml->GET_LOCATION->orders->order->pbnum;
		$location['latitude'] = (float)45.01831; //(string)$xml->GET_LOCATION->orders->order->latitude;                
                $location['longitude'] = (float)-74.72877;  //(string)$xml->GET_LOCATION->orders->order->longitude;
                return $location;
        }

 	function clean_french($string)
	{
  		$from = explode(',', "ç,æ,œ,á,é,í,ó,ú,à,è,ì,ò,ù,ä,ë,ï,ö,ü,ÿ,â,ê,î,ô,û,å,e,i,ø,u,(,),[,],'");
  		$to = explode(',', 'c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u,,,,,,');

  		//Do the replacements, and convert all other non-alphanumeric characters to spaces
  		$value = str_replace($from, $to, trim($string));
  		return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', mb_convert_encoding($value,'utf-8'));
	}

        
}



?>
