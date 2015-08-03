<?php
include("models/shipment.php");
include("models/shipment_good.php");
class bol extends View
{

	function __construct($user, $return)
	{
		$this->user = $user;
		$this->return = $return;
		parent::__construct();
	}

	function init()
	{
		$preferences_q = query("SELECT settings FROM `".$this->user['database']."`.account_settings WHERE group_id='".$this->user['group']."' ORDER BY id DESC");
		$preferences_r = fetch($preferences_q);
		$this->smarty->assign('preferences',json_decode($preferences_r['settings'],1));
		$view  = $this->smarty->fetch('shipment/shipment.tpl');
		$view .= $this->load_tooltips('shipment','0');
		$view  .= "<script type='text/javascript'>window.goods_set_division = undefined;</script>";		
		$this->html['shipon_content'] = $view;

	}
	
	function init_with_data($id,$returnObj = 0)
	{

                $shipment = new Shipment($id,$this->user);
                $shipdata = $shipment->toArray();

                $preferences_q = query("SELECT settings FROM `".$this->user['database']."`.account_settings WHERE group_id='".$this->user['group']."'");
                $preferences_r = fetch($preferences_q);
		$preferences   = json_decode($preferences_r['settings'],1);

		if(isset($shipdata['pup_area']) && $shipdata['pup_area'] != '')
		  $preferences['shipon_settings_dad_pup_area'] = $shipdata['pup_area'];
		
                if(isset($shipdata['del_area']) && $shipdata['del_area'] != '')
                  $preferences['shipon_settings_dad_del_area'] = $shipdata['del_area'];

                $this->smarty->assign('preferences', $preferences);
                $view  = $this->smarty->fetch('shipment/shipment.tpl');
                $view .= $this->load_tooltips('shipment','0');
                $this->html['shipon_content'] = $view;

		foreach($shipdata as $var => $val)
		{
		  if($var == 'id' || $var == 'timestamp' || $var == 'group_id' || $var == 'bill' || $var == 'pbnum' || $var == 'ext_id') continue;	
		  if(gettype($val) == "array" && $var == 'goods') continue;
		  if(strpos($var,'bill') !== false) continue;		

		  $key = 'shipon_'.$var;
		  $this->return['inputs'][$key] = $val;
		}

		$this->return['inputs']['shipon_quote_no'] = '';
		$this->return['inputs']['shipon_del_date'] = date('m/d/Y');

		$goods = json_encode($shipdata['goods']);
		$html  = "<script type='text/javascript'>window.goods_set_division = '".$shipment->division."'; window.goods_set_uom = '".$shipment->uom."'; window.is_recall = true; fill_goods_table('".$goods."');</script>";
	
        	$this->html['shipon_content'] .= $html;

        if($returnObj != 0)
           return $shipment;
	}
	
	function edit($bol_id)
	{
		$shipment = $this->init_with_data($bol_id,1);
		$this->return['inputs']['shipon_id'] = $shipment->id;
		$this->return['inputs']['shipon_ext_id'] = $shipment->ext_id;		
	}

	function copy($bol_id)
	{
		$this->init_with_data($bol_id);
	}

	function validate()
	{        
		$valid_xml = file_get_contents($this->user['folder']."/templates/validation.xml");
		$xml = new SimpleXMLElement($valid_xml);

		$section_num	= (int)$_REQUEST['section'];
		$row_count	= (int)$_REQUEST['goods_row_count'];

		$fields = $xml->SECTION[$section_num]->children();
		$script = "<script type='text/javascript'>";
		foreach($fields as $field)
		{   
			$rules = $field->children();

			foreach($rules as $rule)
			{
				$tag_name = $rule->getName();
				$message = "";
				if($tag_name == 'rule')
				{
					$script .= "jQuery('[name=".$rule."]').rules('add', 
								{
									required: true,";
					
									foreach($rule->attributes() as $attr => $val)
									{
										if($attr != 'message') $script .= $attr.":".$val.",";
										else $message .= $val;
									}
									
									if($message) $script .= "messages: {required:'".$message."'}";
	
					$script .="	}); ";
				}
				else
				{
					for($i = 0; $i < $row_count; $i++)
					{
						$script .= "jQuery('#".$rule."-".($i + 1)."').rules('add', 
									{
										required: true,";
						
										foreach($rule->attributes() as $attr => $val)
										{
											$script .= $attr.":".$val.",";
										}
		
						$script .="	});";
					}
				}
			}
		}
		//Appends validation rule
		/* if ($section_num == 1) {
			for($i = 0; $i < $row_count; $i++)
			{

				$script .= "jQuery('.emptyCheck" . $i . "').rules('add', 
						{
							required: true,digits:true,min:1,";
				$script .="	});";
			}			
		} */

        	$script .= "</script>";
		$script .= $this->load_tooltips('shipment',$section_num + 1);
		$this->html['shipon_output'] = $script;
	}

    	function load_goods_select()
	{
		$div = $_POST['division'];
		$car = $this->user['carrier'];

		if($div == "MES" || $div == "FRT" || $div == "LTL")
		  $car = "0";

		$stm = "SELECT * FROM `".$this->user['database']."`.settings_goods_types WHERE division = ? AND carrier = ? ORDER BY good_name";
		$sql = $this->dbc->prepare($stm);
		$sql->bindValue(1,$div,PDO::PARAM_STR);
		$sql->bindValue(2,$car,PDO::PARAM_STR);
		$sql->execute();
		$col = $sql->fetchAll(PDO::FETCH_ASSOC);

		if(! count($col))
		  return;

		$return = "";

		foreach($col as $gt)
		{ 
		  $default = ($gt['default'] == 1) ? "selected" : ""; 
		  $return .= '<option value="'.$gt['good_value'].'" '.$default.'>'.$gt['good_name'].'</option>';
		}

		$this->html['output'] = $return;
	}

	function parse_goods_table()
	{
		$bill_xml = file_get_contents($this->user['folder']."/templates/goods_table.xml");
		$xml = new SimpleXMLElement($bill_xml);

		$headers = array();
		$inputs = array();
		$widths = array();

		$max_rows = $xml->max_rows;
		$elements = $xml->COLUMNS->children();

		foreach($elements as $element)
		{
			$widths[] = $element['width'];
			$element_xml = file_get_contents("_CORE/templates/goods_table/".$element.".xml");
			$td = new SimpleXMLElement($element_xml);

			$headers[] = $td->title;
			$td_elements = $td->inputs->children();

			$input_element = "";
			foreach($td_elements as $td_element)
			{
				$input_element .= $td_element->asXML();
			}

			$inputs[] = $input_element;
		}

		$table = "<table id='shipon_goods_table'>";
		$table .= "<thead>";
		$table .= "<tr>";
		$count = count($headers);
		for($i = 0; $i < $count; $i++)
		{
			$table .= "<th width='".$widths[$i][0]."'>".text($headers[$i])."</th>";
		}
		$table .= "<th width='10px'></th>";
		$table .= "</tr>";
		$table .= "</thead>";
		$table .= "<tbody>";
		$table .= "<tr>";
		foreach($inputs as $input)
		{
			$table .= "<td><div class='shipon_td_container'>".$input."</div></td>";
		}
		$table .= "<td></td>";
		$table .= "</tr>";
		$table .= "</tbody>";
		$table .= "</table>";

		$table .= "<!--a style='display:none;' class='shipon_button' id='shipon_goods_add' onclick='shipon_goods_add()'> Add </a-->
					<input type='hidden' id='shipon_goods_max_rows' value='".$max_rows."' />";

		return $table;
	}

	function new_shipment()
	{
		// Create shipment and assign _POST vars to object
	 	$shipment = new Shipment("",$this->user);

		foreach($_POST as $key => $val)
		{ 
            		if(gettype($val) == "array") continue;
			else
			{
				$_key = str_replace('shipon_','',$key);
				$shipment->$_key = $val;
			}
		}

		$shipment->group_id = $this->user['group'];
        	$shipment->bill_account = $this->user['bill_to_code'];

		$shipment_id = $shipment->Create();

		// Create shipment good(s) and send _POST vars to good object
		for($g = 0; $g < count($_POST['shipon']['goods']); $g++)
		{
		  $good = new ShipmentGood($shipment);

		  foreach($_POST['shipon']['goods'][$g] as $key => $val)
		  {
			$good->$key = $val;
		  }

		  $good->Create();
		  unset($good);
		}

		return $shipment;
	}

	function update_shipment($id)
	{
        	// Create shipment and send _POST vars to object
        	$shipment = new Shipment($id,$this->user);

        	foreach($_POST as $key => $val)
        	{
                	if(gettype($val) == "array") continue;
                	else
                	{
                       	 	$_key = str_replace('shipon_','',$key);
                        	$shipment->$_key = (string)$val;
                	}
        	}

		$shipment->group_id = $this->user['group'];
		$shipment->bill_account = $this->user['bill_to_code'];

        	$shipment_id = $shipment->Update();
		$shipment->clearGoods();

        	// Create shipment good(s) and send _POST vars to good object
        	for($g = 0; $g < count($_POST['shipon']['goods']); $g++)
        	{	 
          		$good = new ShipmentGood($shipment);
          
          		foreach($_POST['shipon']['goods'][$g] as $key => $val)
          		{
			   $good->$key = $val;
			}
          		$good->Create();
          		unset($good);
        	}
		return $shipment;
	}

	function process_bol()
	{
       		if(!empty($_POST['shipon_id']))
         	   $shipment = $this->update_shipment($_POST['shipon_id']);
       		elseif ($_POST['shipon_qte_only'] == 2 && empty($_POST['shipon_id']))
       	 	   $shipment = $this->new_shipment();
       		else
       	 	   $shipment = $this->new_shipment();

       		if($_POST['shipon_qte_only'] < 2)
	   	   $shipment = $this->get_rates($shipment->id);

	   	$shipment->Update();

	   	$this->return['data'] = $shipment->id;
	}

	function sub_shipment()
	{

	   	$shipment = new Shipment($_POST['shipment_id'],$this->user);

	   	if($shipment->qte_only < 1)
	   	{
           		$integrate = new IntegrationHandler($this->user);
	     		$data = $integrate->process_request('quote_to_order',$shipment);
	   	}

       		$shipment->sent = 1;
       		$shipment->Update();

       		Hook::Run("post","shipment",$this->user['folder'],$this->user,$shipment,$this->session);

	   	$this->html['shipon_viewpdf_status'] = "<span class='shipon_title_warning'>This order is finalized and has been submitted.&nbsp;</span><span class='shipon_icomoon_success'>&#xe01c;</span>";
		$this->html['shipon_return'] .= "<script type='text/javascript'>var newObj = jQuery('.shipon_field_container object').clone(); jQuery('.shipon_field_container object').remove(); jQuery('#shipon_tracking_view_header').after(jQuery(newObj).attr('data','shiponline/api?action=generate_pdf&id=".$_POST['shipment_id']."&rand=' + (Math.random() * 6) + 1).attr('id','newPDF'));</script>";
       		$this->html['shipon_return'] .= "<script type='text/javascript'>jQuery('#shipon_edit_order').remove(); jQuery('#shipon_submit_order').remove(); jQuery('#shipon_submit_loader').remove(); alert('Shipment submitted successfully. Thank you for your order!');</script>";   
	}

	function get_rates($returnObj = 0)
	{
		//to update the email in user settings
		if ($_POST['shipon_division'] == 'LTL') {
			$preferences_q = query("SELECT settings FROM `".$this->user['database']."`.account_settings WHERE id='".$this->user['uid']."'");
			$preferences_r = fetch($preferences_q);
			if (!$preferences_r) {
				$defaultSettingsAddress =  array('shipon_settings_dad_beh'	=>  0,
								 'shipon_settings_dad_email'	=>  $_POST['shipon_ship_email'],
								 'shipon_settings_dad_company'	=>  $_POST['shipon_ship_name'],
								 'shipon_settings_dad_contact'	=>  $_POST['shipon_ship_contact'],
								 'shipon_settings_dad_phone'	=>  $_POST['shipon_ship_phone'],
								 'shipon_settings_dad_ext'	=>  $_POST['shipon_ship_ext'],
								 'shipon_settings_dad_street1'	=>  $_POST['shipon_ship_street1'],	
								 'shipon_settings_dad_street2'	=>  $_POST['shipon_ship_street2'],
								 'shipon_settings_dad_city'	=>  $_POST['shipon_ship_city'],
								 'shipon_settings_dad_prov'	=>  $_POST['shipon_ship_province'],
								 'shipon_settings_dad_postal'	=>  $_POST['shipon_ship_postal']);
				query("INSERT INTO `".$this->user['database']."`.account_settings (`group_id`,`settings`) VALUES (?,?)", array($this->user['uid'],json_encode($defaultSettingsAddress)));
			} else {
				$emailSettingsData = json_decode($preferences_r['settings'],1);
				if (empty($emailSettingsData['shipon_settings_dad_email'])) {
					$emailSettingsData['shipon_settings_dad_email'] = $_POST['shipon_ship_email'];
					query("UPDATE `".$this->user['database']."`.account_settings SET settings='" . json_encode($emailSettingsData) . "' WHERE id='".$this->user['uid']."'");
				}
			}
		}
		if(!empty($_POST['shipon_id']))
		  $shipment = $this->update_shipment($_POST['shipon_id']);
		elseif($returnObj > 0)
		  $shipment = new Shipment($returnObj,$this->user);
		else
		  $shipment = $this->new_shipment();
	
		$integrate = new IntegrationHandler($this->user);
		$data = $integrate->process_request('rate_request',$shipment, $this->session);

                if($returnObj > 0)
                  return $shipment;

		$this->smarty->assign('data',$data);
		$this->html['shipon_rates_content']  = $this->smarty->fetch('shipment/boxes/rates.tpl');
		$this->return['inputs']['shipon_id'] = $shipment->id;
	}
	
	function get_quote()
	{
		// Check if quote/pbnum exists in local database
		$exists = query("SELECT id,sent FROM `".$this->user['database']."`.shipments WHERE ext_id='".(int)$_REQUEST['pbnum']."'");
		$result = fetch($exists);	

		if($result) {
			if($result['sent'] == 1) {
				$this->html['shipon_output'] = "<script type='text/javascript'>alert('You can not retrieve this quote as it has already been submitted.');</script>";
				return;
			}
			$this->return['inputs']['shipon_id'] = $result['id'];
		}			

		$req['Pbnum']           = (int)$_REQUEST['pbnum'];
		$req['bill_to_code']    = $this->user['bill_to_code'];             
			
		$integrate              = new IntegrationHandler($this->user);
		$arrTrackingRequest     = $integrate->process_request('tracking_details_request',$req);

		if (!isset($arrTrackingRequest['error_message'])){		
			if ($arrTrackingRequest['RecStatus']== 'QTE' && $this->user['bill_to_code'] == $arrTrackingRequest['Code']) {

				$this->return['inputs']['shipon_reference_po'] 	= $arrTrackingRequest['RefNumb'];
				$this->return['inputs']['shipon_reference'] 	= $arrTrackingRequest['ShBOL'];
				$this->return['inputs']['shipon_quote_no'] 	= $arrTrackingRequest['Pbnum'];
						
				$this->return['inputs']['shipon_ship_name'] 	= $arrTrackingRequest['ShipName'];
				$this->return['inputs']['shipon_ship_contact'] 	= $arrTrackingRequest['RefName'];
				$this->return['inputs']['shipon_ship_phone'] 	= $arrTrackingRequest['Tel'];
				$this->return['inputs']['shipon_ship_street1'] 	= $arrTrackingRequest['Add1'];
				$this->return['inputs']['shipon_ship_street2']	= $arrTrackingRequest['Add2'];
				$this->return['inputs']['shipon_ship_city'] 	= $arrTrackingRequest['City'];
				$this->return['inputs']['shipon_ship_province'] = $arrTrackingRequest['Prv'];
				$this->return['inputs']['shipon_ship_postal'] 	= $arrTrackingRequest['Postal'];

				$this->return['inputs']['shipon_cons_name'] 	= $arrTrackingRequest['ConsigName'];
				$this->return['inputs']['shipon_cons_contact'] 	= $arrTrackingRequest['ConRefName'];
				$this->return['inputs']['shipon_cons_phone'] 	= $arrTrackingRequest['Contel'];
				$this->return['inputs']['shipon_cons_street1']	= $arrTrackingRequest['ConAdd1'];
				$this->return['inputs']['shipon_cons_street2'] 	= $arrTrackingRequest['ConAdd2'];
				$this->return['inputs']['shipon_cons_city'] 	= $arrTrackingRequest['ConCity'];
				$this->return['inputs']['shipon_cons_province'] = $arrTrackingRequest['ConPrv'];
				$this->return['inputs']['shipon_cons_postal'] 	= $arrTrackingRequest['ConPostal'];
				$this->return['inputs']['shipon_cargo_scn']     = $arrTrackingRequest['cargoNo'];
				
				$this->return['inputs']['shipon_ext_id']	= $arrTrackingRequest['Pbnum'];					
				$this->return['inputs']['shipon_pup_note']      = $arrTrackingRequest['delinst'];
				$this->return['inputs']['shipon_qte_only']	= 2;

				// Goods Table
				$this->return['inputs']['shipon_skids']				     = $arrTrackingRequest['Skid'];
				$this->return['inputs']['shipon\\[goods\\]\\[0\\]\\[pieces\\]']      = $arrTrackingRequest['RateDetails']['Pieces'];
				$this->return['inputs']['shipon\\[goods\\]\\[0\\]\\[particulars\\]'] = $arrTrackingRequest['RateDetails']['ComDescr'];
				$this->return['inputs']['shipon\\[goods\\]\\[0\\]\\[length\\]']      = $arrTrackingRequest['length'];
				$this->return['inputs']['shipon\\[goods\\]\\[0\\]\\[width\\]'] 	     = $arrTrackingRequest['width'];
				$this->return['inputs']['shipon\\[goods\\]\\[0\\]\\[height\\]']      = $arrTrackingRequest['height'];
				$this->return['inputs']['shipon\\[goods\\]\\[0\\]\\[weight\\]']      = $arrTrackingRequest['Weight'];

				$this->return['shipon_pieces'] = $arrTrackingRequest['RateDetails']['Pieces'];
				$goods = json_encode($arrTrackingRequest['RateDetails']);
				$html  = "<script type='text/javascript'>window.is_recall = false;</script>";				

				if(! empty($arrTrackingRequest['pkg']))
					$html .= "<script type='text/javascript'>jQuery('shipon\\[goods\\]\\[0\\]\\[packaging\\]').val('".$arrTrackingRequest['pkg']."');</script>";
				
				$this->html['shipon_return'] .= $html;

				$this->smarty->assign('data', $arrTrackingRequest);
				$this->html['shipon_rates_content'] = $this->smarty->fetch('shipment/boxes/rates.tpl');

				$this->html['shipon_output'] = "<script type='text/javascript'>jQuery('#shipon_get_rates').hide();</script>";
				$this->return['data']['success'] = 1;

			} else {
				$this->return['alert'] = "Quote has been converted to an order and can no longer be retrieved. Please call customer service.";
				$this->return['data']['success'] = 0;
			}  
		} else {
			$this->return['alert'] = "Invalid quote number. Please try again.";
			$this->return['data']['success'] = 0;
		}	
	}
		
	function rate_quote_contact() {
		$shipment = new Shipment($_POST['shipment_id'],$this->user);
		Hook::Run("post","rate",$this->user['folder'],$this->user,$shipment,$this->session);
	        $this->return['alert'] = "Thank you! Our rates department will contact you shortly.";	
	}
	
}

?>
