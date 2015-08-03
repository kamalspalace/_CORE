<?php
//$ajax = Registry::get('ajax');
include("models/ShipOnline.php");
//$SOL_ADDON = &Registry::get('addons.shiponline');
//print_r($_REQUEST);
class shiponlineHandler 
{
	private $shipment;
	private $settings;
	private $carrier;
	private $user;
	function __construct($user) {
		$this->settings = Settings::get($this->user['database'])->toArray();
		$this->shipment = new ShipOnline($this->settings['shiponline']['user'],
										 $this->settings['shiponline']['password'],
										 $this->settings['shiponline']['token']);

		$this->carrier = 'UPS'; // fix me
	}
	
	function validateAddress($data) {
		$this->shipment->validate($this->carrier);

		if($data['shipon_cons_country'] == 'US')
		{   
			$this->set_to_address($data);
			$this->shipment->send();

			//$html['alert'] = $shipment->get_data();
			return $this->shipment->get_validation_response();
		}
		else
		{
			return false;
		}
	}

	function track($data)
	{

		$this->shipment->track($this->carrier, 'CA', $data['trackingNo']);
		$this->shipment->send();
		$return = array();
		$return['events']  = $this->shipment->get_tracking_events();
		$return['details'] = $this->shipment->get_tracking_details();
		return $return;
	}
	
	function set_to_address($data)
	{   
		if(!empty($data['shipon_cons_name']))
		  $this->shipment->set_to_address('company', $data['shipon_cons_name']);

		$this->shipment->set_to_address('contact', $data['shipon_cons_contact']);
		$this->shipment->set_to_address('phone', $data['shipon_cons_phone']);
		$this->shipment->set_to_address('address1', $data['shipon_cons_street1']);
		$this->shipment->set_to_address('address2', $data['shipon_cons_street2']);
		$this->shipment->set_to_address('address3', $data['shipon_del_note']);
		$this->shipment->set_to_address('email', $data['shipon_cons_email']);
		$this->shipment->set_to_address('city', $data['shipon_cons_city']);
		$this->shipment->set_to_address('state', $data['shipon_cons_province']);
		$this->shipment->set_to_address('postal', $data['shipon_cons_postal']);
		$this->shipment->set_to_address('postal_ext', '');
		$this->shipment->set_to_address('country', $data['shipon_cons_country']);
	}
	
	function set_from_address($data)
	{

		$this->shipment->set_from_address('shipper', $data['shipon_ship_name']);
		$this->shipment->set_from_address('contact', $data['shipon_ship_contact']);
		$this->shipment->set_from_address('phone', $data['shipon_ship_phone']);
		$this->shipment->set_from_address('address1', $data['shipon_ship_street1']);
		$this->shipment->set_from_address('city', $data['shipon_ship_city']);
		$this->shipment->set_from_address('state', $data['shipon_ship_province']);
		$this->shipment->set_from_address('postal', $data['shipon_ship_postal']);
		$this->shipment->set_from_address('country', $data['shipon_ship_country']);

	}
	
	function getServices($data)
	{
            //Default passing 02 as code just for testing purpose original argument is "Package Type"
			$this->shipment->request_services($this->carrier,$data['shipon_ship_country'],02);
			$this->set_from_address($data);
			$this->set_to_address($data);
			$this->shipment->send();

			$services = $this->shipment->get_services();
			return $services;
			/*
			$html['shiponline_services'] .= "<select id='service_key' name='shipment_data[service]' class='servicesDD'>";

			foreach($services->children() as $service)
			{
					$html['shiponline_services'] .= "<option value='".ltrim($service->code)."'>".$service->name."</option>";
			}

			$html['shiponline_services'] .= "</select>";

			print(json_encode($html));
		exit;
			 * 
			 */
	}
	
	
	
	
/*
	//$return_text = '';
	$html = array();

	//$zip = strstr($_REQUEST['postal'], '-', true);
	//$_REQUEST['postal'] = $zip;

	$shipping_method = 'standard_rate';

	//$shipment = new ShipOnline($SOL_ADDON['username'],$SOL_ADDON['password'],$SOL_ADDON['token']);

	//print(json_encode($ajax));
	$mode = 'asdfasdf';
	if($mode == 'ship')
	{
		if (!empty($_REQUEST['shipment_data']['products']) && fn_check_shipped_products($_REQUEST['shipment_data']['products'])) 
		{
			// Load carrier name
			$shipment->request_carrier_name($_REQUEST['shipment_data']['carrier']);
			$shipment->send();
			$carrier = $shipment->get_carrier_name();

			// Load service name
			$shipment->request_service_name($_REQUEST['shipment_data']['service']);
			$shipment->send();
			$service = $shipment->get_service_name();		

			$shipment->new_shipment($_REQUEST['shipment_data']['carrier']);

			set_from_address($shipment);
			set_to_address($shipment);
			set_return_address($shipment);
			add_commodities_to_shipment($shipment);
			add_package_to_shipment($shipment);
			set_options($shipment);

			$shipment->set_option('service', $_REQUEST['shipment_data']['service']);


			// Residential Indicator
			if($_REQUEST['shiponline_res'] == 'on')
			   $shipment->set_option('residentialAddressIndicator', '1');

			// Signature Requirements
			if($_REQUEST['shiponline_sig'] == 'on')
			{
			   switch($_REQUEST['shipment_data']['carrier'])
			   {
				  case "FEDEX":
				$shipment->set_option('signatureType','DIRECT');
				  break;

				  case "UPS":
				$shipment->set_option('signatureType','2');
				  break;
			   }
			}

			// Packaging Type
			$shipment->set_option('packagingType',$_REQUEST['shipment_data']['package_type']);

			$shipment->send();

					$trackings = array();

					for($i = 0; $i < $shipment->get_package_count(); $i++)
					{
							$trackings[$i] = (string)$shipment->get_package_tracking($i);
					}

			$trackings = json_encode($trackings);

			if($shipment->has_errors())
				$html['ship_output'] = "<b>".$shipment->get_error()."</b>";

			else
			{
				$order_info = fn_get_order_info($_REQUEST['shipment_data']['order_id'], false, true, true);
				$shipment_data = $_REQUEST['shipment_data'];
				$shipment_data['tracking_number'] = $trackings;
				$shipment_data['label'] = $shipment->get_package_label_data(0);
				$shipment_data['timestamp'] = time();
				$shipment_data['service'] = $service;
				$shipment_data['sol'] = 1;

				$shipment_id = db_query("INSERT INTO ?:shipments ?e", $shipment_data);

					$products = array_unique(array_keys($_REQUEST['shipment_data']['products']));

				foreach ($products as $product) {
					$amount = $_REQUEST['shipment_data']['products'][$product]['amount'];	

					$_data = array(
						'item_id' => $product,
						'shipment_id' => $shipment_id,
						'order_id' => $_REQUEST['shipment_data']['order_id'],
						'product_id' => $order_info['items'][$product]['product_id'],
						'amount' => $amount,
					);

					db_query("INSERT INTO ?:shipment_items ?e", $_data);
				}

				if($_REQUEST['notify_user']) {
				  $force_notification = fn_get_notification_rules($_REQUEST);
				  if (!empty($force_notification['C'])) {
					  $shipment_mail = array(
						'shipment_id' => $shipment_id,
						'timestamp' => $shipment_data['timestamp'],
						'shipping' => $service,
						'tracking_number' => json_decode($shipment_data['tracking_number']),
						'carrier' => $carrier,
						'comments' => $shipment_data['comments'],
						'items' => $_REQUEST['shipment_data']['products'],
					  );

					  $view_mail->assign('shipment', $shipment_mail);
					  $view_mail->assign('order_info', $order_info);

					  $company = fn_get_company_placement_info($order_info['company_id'], $order_info['lang_code']);
					  $view_mail->assign('company_placement_info', $company);

					  $result = fn_send_mail($order_info['email'], array('email' => $company['company_orders_department'], 'name' => $company['company_name']), 'addons/shiponline/shipment_products_subj.tpl', 'addons/shiponline/shipment_products.tpl', '', $order_info['lang_code'], '', true, $order_info['company_id']);
				  }
				}


				if (!empty($shipment_data['order_status'])) {
					fn_change_order_status($_REQUEST['shipment_data']['order_id'], $_REQUEST['shipment_data']['order_status'], '', fn_get_notification_rules(array(), false));
				}

				fn_set_notification('N', fn_get_lang_var('notice'), fn_get_lang_var('shipment_has_been_created'));

				$html['new_shipment_content'] = "
						<div id='label' style='width: 47%; float: right;'>
							<h2 class='subheader'> Label </h2>
							<fieldset style='width: 50%;'>
								<embed name='plugin' src='".fn_url('shiponline.pdf&id='.$shipment_id)."' type='application/pdf' width='450' height='575'>
							</fieldset>
						</div>

						<div id='info' style='width: 47%; float: left;'>
							<h2 class='subheader'> Info </h2>
							<fieldset style='width: 100%;'>
								<div class='form-field'>
									<label> Carrier: </label>
									".$carrier."
								</div>
								<div class='form-field'>
									<label> Method: </label>
									".$service."
								</div>
								<div class='form-field'>
									<label> Tracking Number(s): </label>
									".format_tracking($trackings)."
								</div>
								<div class='form-field'>
									<label> Shipment Cost: </label>
									".$shipment->get_shipment_cost()."
								</div>
								<div class='form-field'>
									<label> Message: </label>
									".$shipment->get_message()."
								</div>
							</fieldset>
						</div>

						<div class='buttons-container' style='position: absolute; top: 651px; left: 13px; '>
							<span class='submit-button cm-button-main'>
								<input class='cm-dialog-closer cm-cancel tool-link' id='shiponline_done' type='button' value='Done' />
							</span>						
						</div>
						";
			}
			//$html['alert'] = $shipment->get_data();
			print(json_encode($html));
			//print($shipment->get_package_label_img(0));
		} else {
			fn_set_notification('E', fn_get_lang_var('error'), fn_get_lang_var('products_for_shipment_not_selected'));
		}
	   exit;
	}
	elseif($mode == 'validate')
	{
		$shipment->validate($_REQUEST['carrier']);

		if($_REQUEST['country'] == 'US')
		{
			set_to_address($shipment);

			$shipment->send();

			//$html['alert'] = $shipment->get_data();
			$html['address_validation'] = "<b>".$shipment->get_validation_response()."</b>";
		}
		else
		{
			$html['address_validation'] = "";
		}

		print(json_encode($html));
	  exit;
	}
	elseif($mode == 'track')
	{
		$shipment->track($_REQUEST['carrier'], $_REQUEST['country'], $_REQUEST['tracking']);

		switch($_REQUEST['carrier'])
		{
			case 'UPS':
				$shipment->set_option('referenceCode', 'IK');
			break;

			case 'FEDEX':
				$shipment->set_option('trackingIdType', 'TRACKING_NUMBER_OR_DOORTAG');
			break;
		}

		$shipment->send();

		if($shipment->has_errors())
		{
			$html['tracking_table'] = "
			<table cellpadding='0' cellspacing='0' border='0' width='100%' class='table'>
				<tr>
					<th width='25%'>Tracking Number</th>
					<th width='25%'>Shipping Method</th>
					<th>Status</th>
				</tr>
				<tr>
					<td> ".$_REQUEST['tracking']." </td>
					<td> - </td>
					<td> ".$shipment->get_error()." </td>
				</tr>
			</table>
			";
		}
		else
		{

		}

		print(json_encode($html));
	   exit;
	}
	elseif($mode == 'rate')
	{
		//get all shipping methods available:
		$services = json_decode($_REQUEST['methods']);
		$selected = $_REQUEST['selected'];

		$shipment->get_rates($_REQUEST['shipment_data']['carrier']);

					// Residential Indicator
					if($_REQUEST['shiponline_res'] == 'on')
					   $shipment->set_option('residentialAddressIndicator', '1');

					// Signature Requirements
					if($_REQUEST['shiponline_sig'] == 'on')
					{
					   switch($_REQUEST['shipment_data']['carrier'])
					   {
						  case "FEDEX":
							$shipment->set_option('signatureType','DIRECT');
						  break;

						  case "UPS":
							$shipment->set_option('signatureType','2');
						  break;
					   }
					}

			set_from_address($shipment);
			set_to_address($shipment);
			add_package_to_shipment($shipment);
			set_options($shipment);

		$return_data = "";
		$html['service_data'] = "<select id='service_key' name='shipment_data[service]' class='servicesDD'>"; 
		foreach($services as $services_string)
		{
			$split = explode('|',$services_string);
			$service_code = $split[0];
			$service_name = $split[1];

			// rate
			$shipment->change_option('service', ltrim($service_code));
			if($service_code == "GROUND_HOME_DELIVERY")
			{
				$shipment->set_option('residentialAddressIndicator', '1');
			}
			$shipment->send();

			$service_rate = (string)$shipment->get_rate_estimate();

			if($service_code == $selected)
			  $selectd = "selected='selected'";
			else
			  $selectd = "";

			$html['service_data'] .= "<option value='".$service_code."' $selectd>".$service_name." (".$service_rate.")</option>";

		}
		$html['service_data'] .= "</select>";
		print(json_encode($html));
	   exit;
	}
	elseif($mode == 'packagings')
	{

		$select  = $_REQUEST['selected'];

		$carrier = $_REQUEST['carrier'];
			$country = $_REQUEST['country'];

		$html['shiponline_packages'] = "";

		$shipment->request_packaging_info($carrier,$country);
		$shipment->send();

		$packages = $shipment->get_packaging_info();

		$html['shiponline_packages'] .= "<option value=''>-</option>";

		foreach($packages as $enum => $long)
		{
			if($select == $enum)
			  $selected = " selected='selected'";
			else
			  $selected = "";

			$html['shiponline_packages'] .= "<option value='".$enum."'$selected>".$long."</option>";
		}

		print(json_encode($html));
		exit;
	}

	elseif($mode == 'carriers')
	{
		$selected = $_REQUEST['selected'];
		$service_data = array();
		$html['shiponline_carriers'] = "";
		$shipment->request_carriers($_POST['country']);	
		$shipment->send();

		$carriers = $shipment->get_carriers();

		$html['shiponline_carriers'] .= "<select id='carrier_key' onchange='get_packaging()' name='shipment_data[carrier]' class='carrierDD'>";

		foreach($carriers->children() as $carrier)
		{
			if($carrier->code == $selected)
			  $selectd = "selected='selected'";
			else
			  $selectd = "";

			$html['shiponline_carriers'] .= "<option value='".$carrier->code."' $selectd>".$carrier->name."</option>";		
		}

		$html['shiponline_carriers'] .= "</select>";

		print(json_encode($html));
		exit;
	}

	elseif($mode == 'services')
	{

		$shipment->request_services($_REQUEST['shipment_data']['carrier'],$_REQUEST['country'],$_REQUEST['shipment_data']['package_type']);
			set_from_address($shipment);
			set_to_address($shipment);
			$shipment->send();

			$services = $shipment->get_services();

			$html['shiponline_services'] .= "<select id='service_key' name='shipment_data[service]' class='servicesDD'>";

			foreach($services->children() as $service)
			{
					$html['shiponline_services'] .= "<option value='".ltrim($service->code)."'>".$service->name."</option>";
			}

			$html['shiponline_services'] .= "</select>";

			print(json_encode($html));
		exit;
	}

	elseif($mode == 'void')
	{
		$shipment->void($_REQUEST['carrier']);

		$shipment->request->addChild('country', $_REQUEST['country']);
		$shipment->request->addChild('tracking_number', $_REQUEST['tracking']);

		switch($_REQUEST['carrier'])
		{
			case 'FEDEX':
				$shipment->set_option('trackingIdType', 'GROUND');
			break;
		}

		$shipment->send();

		$html['void_output'] = "<b>".$shipment->response->VOID->severity.": </b>".$shipment->response->VOID->message;

		print(json_encode($html));
	   exit;
	}
	elseif($mode == 'pdf')
	{
		$id = $_REQUEST['id'];
			$data = db_get_array("SELECT label, carrier FROM ?:shipments WHERE shipment_id='".$id."'");

			$decoded = base64_decode($data[0]['label']);
	/*        $carrier = $data[0]['carrier'];

			$pdf = new PDFlib();
			$pdf->begin_document("","");
					$pdf->begin_page_ext(288, 432, "");

							switch($carrier)
							{
									case 'UPS':
											$pvf = "/pvf/image.gif";
											$pdf->create_pvf($pvf, $decoded, "");
											$image = $pdf->load_image("gif", $pvf, "");

											$pdf->fit_image($image, 0, -71.5, "boxsize {288 432} orientate east scale {0.3595}");
									break;

									case 'FEDEX':
											$pvf = "/pvf/image.png";
											$pdf->create_pvf($pvf, $decoded, "");
											$image = $pdf->load_image("png", $pvf, "");

											$pdf->fit_image($image, 0, 0, "boxsize {288 432} fitmethod {entire}");
									break;
							}

							$pdf->delete_pvf($pvf);

					$pdf->end_page_ext("");
			$pdf->end_document("");

			//Display PDF in the Web-browser
			$buf = $pdf->get_buffer();
	*/
/*			header("Content-type: application/pdf");
			header("Content-Disposition: inline; filename=label_".$id.".pdf");

			print $decoded;
		exit;
	}
	elseif($mode == 'details')
	{
			$params = $_REQUEST;

			if (empty($params['shipment_id'])) {
					return array(CONTROLLER_STATUS_NO_PAGE);
			}

			if (!empty($params['shipment_id'])) {
					$params['order_id'] = db_get_field('SELECT ?:shipment_items.order_id FROM ?:shipment_items WHERE ?:shipment_items.shipment_id = ?i', $params['shipment_id']);
			}

			$shippings = db_get_array("SELECT a.shipping_id, a.min_weight, a.max_weight, a.position, a.status, b.shipping, b.delivery_time, a.usergroup_ids FROM ?:shippings as a LEFT JOIN ?:shipping_descriptions as b ON a.shipping_id = b.shipping_id AND b.lang_code = ?s WHERE a.status = ?s ORDER BY a.position", DESCR_SL, 'A');

			$order_info = fn_get_order_info($params['order_id'], false, true, true);

			if (empty($order_info)) {
					return array(CONTROLLER_STATUS_NO_PAGE);
			}
			if (!empty($params['shipment_id'])) {
					$params['advanced_info'] = true;

					// list($shipment, $search, $total) = fn_get_shipments_info($params);

					$shipment = db_get_array("SELECT * FROM ?:shipments WHERE shipment_id='".$params['shipment_id']."'");


					if (isset($params['advanced_info']) && $params['advanced_info'] && !empty($shipment)) {
							foreach ($shipment as $id => $ship) {
									$items = db_get_array('SELECT item_id, amount FROM ?:shipment_items WHERE shipment_id = ?i', $ship['shipment_id']);
									if (!empty($items)) {
											foreach ($items as $item) {
													$shipment[$id]['items'][$item['item_id']] = $item['amount'];
											}
									}
							}
					}


					if (!empty($shipment)) {
							$shipment = array_pop($shipment);
							$shipment['tracking_number'] = json_decode($shipment['tracking_number']);
							foreach ($order_info['items'] as $item_id => $item) {
									if (isset($shipment['items'][$item_id])) {
											$order_info['items'][$item_id]['amount'] = $shipment['items'][$item_id];
									} else {
											$order_info['items'][$item_id]['amount'] = 0;
									}
							}
					} else {
							$shipment = array();
					}

					$view->assign('shipment', $shipment);
			}

			fn_add_breadcrumb(fn_get_lang_var('shipments'), 'shipments.manage.reset_view');
			fn_add_breadcrumb(fn_get_lang_var('search_results'), "shipments.manage.last_view");

			$view->assign('shippings', $shippings);
			$view->assign('order_info', $order_info);


	}

	function format_tracking($trackings)
	{
		$t = json_decode($trackings);

		foreach($t as $package)
		{
		  $response .= $package."<br />";
		}

	   return $response;
	}

	function setup_response($html)
	{
		$data = array();
		$response = array();

		$data['html'] = $html;
		$response['data'] = $data;

		return $response;
	}

	function set_from_address(&$shipment)
	{
		$SOL_ADDON = &Registry::get('addons.shiponline');

		if($SOL_ADDON['origin'] != 'sol')
		{
			$company = fn_get_company_placement_info("");

			$shipment->set_from_address('shipper', $company['company_name']);
			$shipment->set_from_address('contact', '');
			$shipment->set_from_address('phone', $company['company_phone']);
			$shipment->set_from_address('address1', $company['company_address']);
			$shipment->set_from_address('city', $company['company_city']);
			$shipment->set_from_address('state', $company['company_state']);
			$shipment->set_from_address('postal', $company['company_zipcode']);
			$shipment->set_from_address('country', $company['company_country']);
		}
	}

	function set_to_address(&$shipment)
	{
		if($_REQUEST['company'])
		  $shipment->set_to_address('company', $_REQUEST['company']);

		$shipment->set_to_address('contact', $_REQUEST['first_name']." ".$_REQUEST['last_name']);
		$shipment->set_to_address('phone', (strlen($_REQUEST['phone']) > 1) ? $_REQUEST['phone'] : '123-123-4567');
		$shipment->set_to_address('address1', $_REQUEST['address']);
		$shipment->set_to_address('address2', $_REQUEST['address2']);
		$shipment->set_to_address('address3', $_REQUEST['shipment_data']['instructions']);
		$shipment->set_to_address('email', $_REQUEST['email']);
		$shipment->set_to_address('city', $_REQUEST['city']);
		$shipment->set_to_address('state', $_REQUEST['state']);
		$shipment->set_to_address('postal', str_replace(' ', '', $_REQUEST['zip']));
		$shipment->set_to_address('postal_ext', $_REQUEST['zip_ext']);
		$shipment->set_to_address('country', $_REQUEST['country']);
	}

	function set_return_address(&$shipment)
	{
			$SOL_ADDON = &Registry::get('addons.shiponline');

		if($SOL_ADDON['returns_address'] == 'N')
		  return;

		$shipment->set_return_address('company', $SOL_ADDON['returns_company']);
			$shipment->set_return_address('contact', $SOL_ADDON['returns_attn_to']);
			$shipment->set_return_address('phone', $SOL_ADDON['returns_telephone']);
			$shipment->set_return_address('address1', $SOL_ADDON['returns_street1']);
			$shipment->set_return_address('address2', $SOL_ADDON['returns_street2']);
			$shipment->set_return_address('city', $SOL_ADDON['returns_city']);
			$shipment->set_return_address('state', $SOL_ADDON['returns_state']);

		if(strpos($SOL_ADDON['returns_postal'],'-'))
		{
		  $psplit = explode('-',$SOL_ADDON['returns_postal']);
		  $postal = $psplit[0];
		  $postex = $psplit[1];
		}
		else
		  $postal = $SOL_ADDON['returns_postal'];

			$shipment->set_return_address('postal', str_replace(' ', '', $postal));
			$shipment->set_return_address('postal_ext', $postex);
			$shipment->set_return_address('country', $SOL_ADDON['returns_country']);
	}

	function add_package_to_shipment(&$shipment)
	{
		foreach($_REQUEST['shipment_data']['packaging'] as $package)
		{
			if($_REQUEST['shipment_data']['carrier'] == 'UPS')
			{
			   $package['weightUnit'] = "LBS";
			   $package['lengthUnit'] = "IN";
			   $package['packagingType'] = $package['type'];
			   $package['monetaryValue'] = $package['insurance'];
			   $package['insuredCurrency'] = 'CAD';
			   unset($package['insurance']);
			   unset($package['type']);
			}
			elseif($_REQUEST['shipment_data']['carrier'] == 'FEDEX')
			{
			   unset($package['type']);
			   unset($package['length']);
			   unset($package['width']);
			   unset($package['height']);
			   unset($package['insurance']);
			   unset($package['packagingType']);	
			}
			$shipment->add_package($package);
		}
	}

	function add_commodities_to_shipment(&$shipment)
	{

		$products = array_unique(array_keys($_REQUEST['shipment_data']['products']));

		foreach($products as $product)
		{
			$commodity = array();
			$commodity['numberOfPieces']			= 1;
			$commodity['commodityDescription']      = $_REQUEST['shipment_data']['products'][$product]['comdesc'];
			$commodity['countryOfManufacture']      = $_REQUEST['shipment_data']['products'][$product]['com'];
			$commodity['unitValue']					= $_REQUEST['shipment_data']['products'][$product]['dec_value'];
			$commodity['unitQuantity']				= $_REQUEST['shipment_data']['products'][$product]['amount'];
			$commodity['weightUOM']					= "LB";
			$commodity['weightUnit']                = $_REQUEST['shipment_data']['products'][$product]['weight'];
			$commodity['amountTotal']				= $commodity['unitValue'] * $commodity['unitQuantity'];
			$commodity['customsCurrency']			= "USD";

			if($commodity['unitQuantity'] > 0)
			  $shipment->add_commodity($commodity);		  
		}

	}

	function set_options(&$shipment)
	{
		switch($_REQUEST['shipment_data']['carrier'])
		{
			case 'PURO':
			$shipment->set_option('weightUnit', 'LB');
			break;

			case 'UPS':
			$shipment->set_option('negotiatedRates', '0');
			$shipment->set_option('referenceCode', 'IK');
			$shipment->set_option('referenceValue', $_REQUEST['shipment_data']['order_id']);
			$shipment->set_option('PickupType', '06');
			break;

			case 'FEDEX':
			$shipment->set_option('lengthUnit', 'IN');
			$shipment->set_option('weightUnit', 'LB');
			$shipment->set_option('packagingType',$_REQUEST['shipment_data']['packaging'][0]['type']);
			$shipment->set_option('dropoffType', 'REGULAR_PICKUP');
			$shipment->set_option('paymentType', 'SENDER');

			$shipment->set_option('insuredValue', $_REQUEST['shipment_data']['insurance']);
			$shipment->set_option('customsValue', fn_customs_totals());

			$shipment->set_option('referenceCode', 'INVOICE_NUMBER');
			$shipment->set_option('referenceValue', $_REQUEST['shipment_data']['order_id']);

			if($_REQUEST['shipment_data']['service'] == "GROUND_HOME_DELIVERY")
			{
				$shipment->set_option('residentialAddressIndicator', '1');
			}
			break;	
		}
	}

	function fn_customs_totals()
	{
		$total = 0;
			$products = array_unique(array_keys($_REQUEST['shipment_data']['products']));
			foreach($products as $product)
			{
		  $total = $total + ($_REQUEST['shipment_data']['products'][$product][amount] * $_REQUEST['shipment_data']['products'][$product]['dec_value']);
		}
	  return $total;
	}

	function fn_check_shipped_products($products)
	{
		$allow = true;
		$total_amount = 0;

		if (!empty($products) && is_array($products)) {
			foreach ($products as $product) {
				$total_amount += empty($product['amount']) ? 0 : $product['amount'];
			}

			if ($total_amount == 0) {
				$allow = false;
			}

		} else {
			$allow = false;
		}

		return $allow;
	}
*/}
?>
