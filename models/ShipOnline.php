<?php

class ShipOnline
{
	public $xml;
	public $request;
	public $response;
	public $raw_data;

	// API Credentials
	private $SOCA_USRID;
	private $SOCA_PASWD;
	private $SOCA_TOKEN;

	function __construct($User, $Pass, $Token)
	{
		if($User)
		  $this->SOCA_USRID = $User;

		if($Pass)
		  $this->SOCA_PASWD = $Pass;

		if($Token)
		  $this->SOCA_TOKEN = $Token;

		$this->xml = new SimpleXMLElement('<REQUEST></REQUEST>');
		$this->xml->addChild('carrier');
	}
	
	function new_request()
	{
		$this->xml = new SimpleXMLElement('<REQUEST></REQUEST>');
		$this->xml->addChild('carrier');
	}

	function send()
	{

		//Setup the post variable
		$post = array(
			'SOCA_USRID'=> $this->SOCA_USRID,
			'SOCA_PASWD'=> $this->SOCA_PASWD,
			'SOCA_TOKEN'=> $this->SOCA_TOKEN,
			'SOCA_REQST'=> $this->xml->asXML(),
			);

		//cURL the request and get the response
		$ch = curl_init();
		//curl_setopt($ch, CURLOPT_URL, "http://xml.shiponline.ca/api.php");
		curl_setopt($ch, CURLOPT_URL, "http://xml.shiponline.ca/api.php");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$data = curl_exec($ch);
		curl_close($ch);

		try
		{
			$this->raw_data = $data;
			$this->response = new SimpleXMLElement($data);
		}
		catch(Exception $e)
		{
			$this->response = new SimpleXMLElement("<RESPONSE><ERROR>".$e->getMessage()."</ERROR></RESPONSE>");
		}
	}
	
	function get_request_xml()
	{
		return $this->xml->asXML();
	}
	
	function get_data()
	{
		return $this->raw_data;
	}
	
	function request_carriers($country)
	{
		$this->xml = new SimpleXMLElement('<REQUEST></REQUEST>');
	
		$this->xml->addChild('INFO');	
		$this->xml->INFO->addChild('CARRIERS');
		$this->xml->INFO->CARRIERS->addChild('country', $country);
	}

	function request_services($carrier,$country,$package_type)
	{
		$this->xml = new SimpleXMLElement('<REQUEST></REQUEST>');

		$this->request = $this->xml->addChild('INFO');
		$this->request->addChild('SERVICES');
		$this->request->SERVICES->addChild('carrier',$carrier);
		$this->request->SERVICES->addChild('country',$country);
		$this->request->SERVICES->addChild('package_type',$package_type);
		$this->request->addChild('ADDRESSES');
        $this->request->ADDRESSES->addChild('FROM');
        $this->request->ADDRESSES->addChild('TO');
	}

	function request_packaging_info($carrier,$country)
	{	
		$this->xml = new SimpleXMLElement('<REQUEST></REQUEST>');
	
		$this->xml->addChild('INFO');	
		$this->xml->INFO->addChild('PACKAGINGS');
		$this->xml->INFO->PACKAGINGS->addChild('carrier',$carrier);
		$this->xml->INFO->PACKAGINGS->addChild('country',$country);
	}

        function request_carrier_name($carrier_id)
        {
                $this->xml = new SimpleXMLElement('<REQUEST></REQUEST>');
                
                $this->xml->addChild('INFO');
                $this->xml->INFO->addChild('CARRIER_NAME');
                $this->xml->INFO->CARRIER_NAME->addChild('carrier_id', $carrier_id);
        }       
                
        function request_service_name($service_id)
        {               
                $this->xml = new SimpleXMLElement('<REQUEST></REQUEST>');
        
                $this->xml->addChild('INFO');
                $this->xml->INFO->addChild('SERVICE_NAME');
                $this->xml->INFO->SERVICE_NAME->addChild('service_id', $service_id);
        }       
        

	//-------------------------------------NEW SHIPMENT FUNCTIONS------------------------------------//

	function new_shipment($carrier)
	{
		$this->xml->carrier = $carrier;

		$this->request = $this->xml->addChild('NEW_SHIPMENT');

		$this->request->addChild('ADDRESSES');
		$this->request->ADDRESSES->addChild('FROM');
		$this->request->ADDRESSES->addChild('TO');
		$this->request->ADDRESSES->addChild('RETURN');
		$this->request->addChild('COMMODITIES');
		$this->request->addChild('PACKAGES');
		$this->request->addChild('LABEL');
		$this->request->addChild('DETAILS');
	}
	
	function set_label_info($info, $data)
	{
		$this->request->LABEL->addChild($info, $data);
	}
	
	//----------------------------------TIME IN TRANSIT FUNCTIONS---------------------------------//
	
	function time_in_transit($carrier)
	{
		$this->xml->carrier = $carrier;
		
		$this->request = $this->xml->addChild('TIME_IN_TRANSIT');
		
		$this->request->addChild('ADDRESSES');
		$this->request->ADDRESSES->addChild('FROM');
		$this->request->ADDRESSES->addChild('TO');
		$this->request->addChild('DETAILS');
	}

	//-------------------------------------GET RATES FUNCTIONS------------------------------------//

	function get_rates($carrier)
	{
		$this->xml->carrier = $carrier;

		$this->request = $this->xml->addChild('GET_RATES');

		$this->request->addChild('ADDRESSES');
		$this->request->ADDRESSES->addChild('FROM');
		$this->request->ADDRESSES->addChild('TO');
		$this->request->addChild('PACKAGES');
		$this->request->addChild('COMMODITIES');
		$this->request->addChild('DETAILS');
	}

	//-----------------------------------------TRACK FUNCTIONS-----------------------------------------//

	function track($carrier, $country, $tracking_number)
	{
		$this->xml->carrier = $carrier;

		$this->request = $this->xml->addChild('TRACK');
		$this->request->addChild('DETAILS');

		$this->request->addChild('country', $country);
		$this->request->addChild('tracking_number', $tracking_number);
	}

	
	function get_tracking_events()
	{
		$events = array();
		$event_count = count($this->response->TRACK->EVENTS->EVENT);
		
		for($i = 0; $i < $event_count; $i++)
		{
			$events[$i]['timestamp'] = $this->response->TRACK->EVENTS->EVENT[$i]->timestamp;
			$events[$i]['location'] = $this->response->TRACK->EVENTS->EVENT[$i]->location;
			$events[$i]['description'] = $this->response->TRACK->EVENTS->EVENT[$i]->description;
		}
		
		return $events;
	}
	
	function get_tracking_details()
	{
		$details = array();
		
		$details['trackingNumber'] = $this->response->TRACK->trackingNumber;
		$details['serviceInfo'] = $this->response->TRACK->serviceInfo;
		$details['estimatedDelivery'] = $this->response->TRACK->estimatedDelivery;
		$details['signedBy'] = $this->response->TRACK->signedBy;
		$details['signatureImage'] = $this->response->TRACK->signatureImage;
		$details['signatureLocation'] = $this->response->TRACK->signatureLocation;
		
		return $details;
	}
	
	//------------------------------------ADDRESS VALIDATE FUCNTIONS-----------------------------------//
	
	function validate($carrier)
	{
		$this->xml->carrier = $carrier;
		
		$this->request = $this->xml->addChild('VALIDATE');
		$this->request->addChild('ADDRESSES');
		$this->request->ADDRESSES->addChild('TO');
	}
	
	//------------------------------------------VOID FUCNTIONS-----------------------------------------//
	
	function void($carrier)
	{
		$this->xml->carrier = $carrier;
		
		$this->request = $this->xml->addChild("VOID");
		$this->request->addChild('DETAILS');
	}

	//-----------------------------------ADDING INFORMATION FUNCTIONS----------------------------------//

	function add_package($package)
	{
		$current_package = $this->request->PACKAGES->addChild('PACKAGE');

		$package_options = array_keys($package);
		foreach ($package_options as $option)
		{
			$current_package->addChild($option, $package[$option]);
		}
	}

	function add_commodity($commodity)
	{
		$commodity_xml = $this->request->COMMODITIES->addChild('COMMODITY');
	
		$commodity_options = array_keys($commodity);	
		foreach($commodity_options as $option)
		{
			$commodity_xml->addChild($option, $commodity[$option]);
		}
	}

	function set_from_address($from, $data)
	{
		$this->request->ADDRESSES->FROM->addChild($from, $data);
	}

	function set_to_address($to, $data)
	{
		$this->request->ADDRESSES->TO->addChild($to, $data);
	}

	function set_return_address($return, $data)
	{
		$this->request->ADDRESSES->RETURN->addChild($return, $data);
	}

	function set_option($name, $option)
	{
		$this->request->DETAILS->addChild($name, $option);
	}
	
	function change_option($name, $option)
	{
		$this->request->DETAILS->$name = $option;
	}

	//-----------------------------------------RESPONSE FUNCTIONS--------------------------------------//

	function print_response_xml()
	{
		return ($this->response->asXML());
	}

	function get_shipment_tracking()
	{
		return $this->response->SHIPMENT->tracking_shipment;
	}

	function get_shipment_cost()
	{
		return $this->response->SHIPMENT->charges;
	}

	function get_package_tracking($i)
	{
		return $this->response->SHIPMENT->PACKAGES->PACKAGE[$i]->tracking_package;
	}

	function get_package_count()
	{
		return count($this->response->SHIPMENT->PACKAGES->PACKAGE);
	}
	
	function get_validation_response()
	{
		return $this->response->VALIDATE->validate;	
	}

	function get_package_label_img($i)
	{
		return("<img src='data:image/".$this->response->SHIPMENT->PACKAGES->PACKAGE[$i]->label_format.";base64, ".$this->response->SHIPMENT->PACKAGES->PACKAGE[$i]->label."' />");
	}

	function get_package_label_data($i)
	{
		return $this->response->SHIPMENT->PACKAGES->PACKAGE[$i]->label;
	}
	
	function get_package_label_format($i)
	{
		return $this->response->SHIPMENT->PACKAGES->PACKAGE[$i]->label_format;
	}

	function get_rate_estimate()
	{
		return $this->response->RATES->rate;
	}
	
	function get_carriers()
	{
		return $this->response->CARRIERS;	
	}

	function get_services()
	{
		return $this->response->SERVICES;
	}

	function get_packaging_info()
	{
	   	$packages = array();

		foreach($this->response->PACKAGE_TYPES->children() as $types)
		{
		 $type = (array)$types;
		 $packages[$type['code']] = $type['name'];
		}

		asort($packages);

		return $packages;
	}

        function get_carrier_name()
        {
                return $this->response->CARRIER_NAME;
        }

        function get_service_name()
        {
                return $this->response->SERVICE_NAME;
        }

	function has_errors()
	{
		if($this->response->ERROR)
			return true;
		else
			return false;
	}
	
	function get_error()
	{
		return $this->response->ERROR;
	}
	
	function get_message()
	{
		return $this->response->SHIPMENT->message;
	}
}
?>
