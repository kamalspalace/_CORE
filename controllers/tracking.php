<?php

class tracking extends View
{
	function __construct($user, $return)
	{
		$this->user = $user;
		$this->return = $return;
		parent::__construct();
	}	

	function init($data,$csv = false)
	{
		$two_weeks_ago = mktime(0,0,0,date("m"),date("d")-14,date("Y"));

		// TO-DO: create helper functions to validate form inputs
		$req = array();
		$req['entries_per_page'] = ($csv == true) ? 2000 : 10;
		$req['start_date']       = isset($_REQUEST['start_date'])    ? $_REQUEST['start_date']    : date('m/d/Y', $two_weeks_ago);
		$req['end_date']         = isset($_REQUEST['end_date'])      ? $_REQUEST['end_date']      : date('m/d/Y');
		$req['search_string']    = isset($_REQUEST['search_string']) && $_REQUEST['search_string'] != 'Search' ? $_REQUEST['search_string'] : '';
		$req['sort_column']      = isset($_REQUEST['sort_by'])       ? $_REQUEST['sort_by']       : 'Pbnum';
		$req['sort_order']       = isset($_REQUEST['sort_order'])    ? $_REQUEST['sort_order']    : 'DESC';
		$req['start_row']        = isset($_REQUEST['data'])	     ? $_REQUEST['data'] * 10 + 1 : 1; 
		$req['bill_to_code']	 = $this->user['bill_to_code'];

		// Check if we're tracking for multiple debtors
		$multi_btc     = query("SELECT tracking FROM `".$this->user['database']."`.account_groups WHERE id='".$this->user['group']."'");
		$bill_to_codes = fetch($multi_btc);

		// Trim leading/trailing whitespace for tracking requests with multiple accounts
		if(count($bill_to_codes) && ! empty($bill_to_codes['tracking'])) {
	          $req['bill_to_code'] = '';
		  $_bill_to_codes = explode(',',$bill_to_codes['tracking']);

		  foreach($_bill_to_codes as $_btc)
		    $req['bill_to_code'] .= trim($_btc).',';
                  rtrim($req['bill_to_code'],',');  
		}
		else
		  $req['bill_to_code'] = $this->user['bill_to_code'];

		$track_count = $this->session->track_count;


		if(isset($track_count))
		  if(intval($track_count) > 0)
		    $req['resultset'] = intval($track_count);

                $integrate = new IntegrationHandler($this->user);
                $data = $integrate->process_request('tracking_list_request',$req);


	        if($csv == true)
		  return $this->csv_output($data);		  		


                $i = 0;
                $conAddString = '';
                $shipAddressString = '';
                $ConAddress = array('ConAdd1','ConAdd-2','ConCity','ConPrv','ConPostal');
                $ShipAddress = array('Add-1','Add-2','City','Prv','Postal');
                while($i<count($data['orders'])) {
                    foreach ($data['orders'][$i] as $key => $value) {
                        if (in_array($key,$ShipAddress)) {
                           if (!empty($value)) { 
				if($key == 'City') {
				   $value = preg_replace('/([\'\x{0027}]|&#39;)/u', "&#39;", $value);
				   $br = ", ";
				}
				else
				   $br = "<br />";
                                $shipAddressString .= $value.$br;
                           }     
                        }                        
                        
                        
                        
                        if (in_array($key,$ConAddress)) {
                            if (!empty($value)) {
				if($key == 'ConCity') {
				   $value = preg_replace('/([\'\x{0027}]|&#39;)/u', "&#39;", $value);
				   $br = ", ";
				}
				else
				   $br = "<br />";
                                $conAddString .= $value.$br; 
                            }    
                        }
                        
                    } 
                    $data['orders'][$i]['conaddress']   = rtrim($conAddString, ",");
                    $data['orders'][$i]['shipaddress']  = rtrim($shipAddressString, ",");
                    $conAddString = '';
                    $shipAddressString = '';
                    $i++;
                }

		// Set last count #
		if(isset($data['dtmscount']))
		  if($data['dtmscount'] > 0)
			$this->session->track_count = intval($data['dtmscount']);

		$req['page_count'] 	 = ceil($data['count'] / $req['entries_per_page']);

		$pagination = $this->get_paged_footer("#tracking", $req['entries_per_page'], $req['page_count'], 10);

        	$this->smarty->assign('request',$req);
		$this->smarty->assign('data',$data);	
		$this->smarty->assign('pagination', $pagination);
		$this->html['shipon_content'] = $this->smarty->fetch('tracking/tracking_list.tpl');
	}
	
	function view_details($id)
	{
		$two_weeks_ago = mktime(0,0,0,date("m"),date("d")-14,date("Y"));	
	
		$req = array();
                $req['entries_per_page'] = 10;
                $req['start_date']       = isset($_REQUEST['start_date'])    ? $_REQUEST['start_date']    : date('m/d/Y', $two_weeks_ago);
                $req['end_date']         = isset($_REQUEST['end_date'])      ? $_REQUEST['end_date']      : date('m/d/Y');
                $req['search_string']    = isset($_REQUEST['search_string']) && $_REQUEST['search_string'] != 'Search' ? $_REQUEST['search_string'] : '';
                $req['sort_column']      = isset($_REQUEST['sort_by'])       ? $_REQUEST['sort_by']       : 'ProDate';
                $req['sort_order']       = isset($_REQUEST['sort_order'])    ? $_REQUEST['sort_order']    : 'DESC';
                $req['start_row']        = isset($_REQUEST['data'])          ? $_REQUEST['data'] * 10 + 1 : 1;

		if(strpos($id,"_") !== false)
		{
		  $tmp = explode("_",$id);
		  $req['Pbnum']        = $tmp[0];
		  $req['bill_to_code'] = $tmp[1];
		  $_REQUEST['pbnum']   = $tmp[0];
		}
		else
		{
		  $req['Pbnum']        = $id;
		  $req['bill_to_code'] = $this->user['bill_to_code']; 
		  $_REQUEST['pbnum']   = $id;
		}

        	$integrate = new IntegrationHandler($this->user);
        	$data = $integrate->process_request('tracking_details_request',$req);
		$data['bill_to_code'] = $this->user['bill_to_code'];

        	Hook::Run("pre","tracking_view",$this->user['folder'],$data,$this->user);

		if(empty($data['error']) && isset($data['Pbnum']))
		{
        		$internalShipmentId = query("SELECT id FROM `".$this->user['database']."`.shipments WHERE ext_id=".$data['Pbnum']);
			$internalId = fetch($internalShipmentId);
        		if (count($internalId['id'])) 
            			$data['internalId'] = $internalId['id'];
		}

		$this->smarty->assign('request',$req);
		$this->smarty->assign('data',$data);
        	$this->html['shipon_content'] = $this->smarty->fetch('tracking/tracking_view.tpl');
	}

	function csv($data)
	{
	   	return $this->init("",true);
	}


	function csv_output($data)
	{
		// Grab column names
	 	if(count($data))
		  $keys = array_keys($data['orders'][0]);

		$out = "";

		// Output column headers
		$c = 0;
		foreach($keys as $column) {
		  $c++;
		  if($column == "return") {
		    $kd = $c - 1;
		    continue;
		  }

		  if($c == count($keys))
		     $out .= $column;
		  else
		     $out .= $column.",";
		}	
		$out .= "\r\n";

		unset($keys[$kd]);

		for($o = 0; $o < count($data['orders']); $o++)
		{
		    $line = "";
		    foreach($data['orders'][$o] as $col => $val) {
			if($col == 'return') continue;
                        if(strpos($val,',') !== false) $val = str_replace(',','',$val);
			$line .= stripslashes($val).",";
		    }
		    $out .= rtrim($line,",")."\r\n";
		} 

		$this->return['data'] = $out;
	}
	
	function view_pdf($id)
	{
                $two_weeks_ago = mktime(0,0,0,date("m"),date("d")-14,date("Y"));

                $req = array();
                $req['entries_per_page'] = 10;
                $req['start_date']       = isset($_REQUEST['start_date'])    ? $_REQUEST['start_date']    : date('m/d/Y', $two_weeks_ago);
                $req['end_date']         = isset($_REQUEST['end_date'])      ? $_REQUEST['end_date']      : date('m/d/Y');
                $req['search_string']    = isset($_REQUEST['search_string']) && $_REQUEST['search_string'] != 'Search' ? $_REQUEST['search_string'] : '';
                $req['sort_column']      = isset($_REQUEST['sort_by'])       ? $_REQUEST['sort_by']       : 'ProDate';
                $req['sort_order']       = isset($_REQUEST['sort_order'])    ? $_REQUEST['sort_order']    : 'DESC';
                $req['start_row']        = isset($_REQUEST['data'])          ? $_REQUEST['data'] * 10 + 1 : 1;

                if(strpos($id,"_") !== false)
                {
                  $tmp = explode("_",$id);
                  $req['Pbnum']        = $tmp[0];
                  $req['bill_to_code'] = $tmp[1];
                  $_REQUEST['pbnum']   = $tmp[0];
                }
                else
                {
                  $req['Pbnum']        = $id;
                  $req['bill_to_code'] = $this->user['bill_to_code'];
                  $_REQUEST['pbnum']   = $id;
                }

		$this->smarty->assign('request',$req);
                $this->html['shipon_content'] = $this->smarty->fetch('tracking/view_pdf.tpl').$this->get_pdf_footer($id);
	}
	
	function view_document($link)
	{
            if(strpos($link,'BL'))
              $type = 'Bill of Lading';
            elseif(strpos($link,'POD'))
              $type = 'Proof of Delivery';
            else                
              $type = 'Document';
                        
            $embed = "<embed src='shiponline/api?action=generate_document&link=".$link."' type='application/pdf' width='960' height='1100'></embed>";

            $this->smarty->assign('type',$type);
            $this->smarty->assign('embed',$embed);
            $this->html['shipon_content'] = $this->smarty->fetch('tracking/view_document.tpl').$this->get_pdf_footer($id);
	}
	
	function get_details()
	{
		$integrate = new IntegrationHandler($this->user);
		$data = $integrate->process_request('tracking_details_request');
		
		$tracking_xml = file_get_contents($this->user['folder']."/templates/tracking.xml");
		$xml = new SimpleXMLElement($tracking_xml);
		
		$details = "<div id='shipon_tracking_details'>
						<a class='shipon_titlebutton ui-corner-all' id='shipon_tracking_close' onclick='tracking_close()' role='button'><span class='ui-icon ui-icon-closethick'>close</span></a>
						<div id='shipon_tracking_title'>".text('tracking_details')."</div>";
		
		foreach($xml->LAYOUT->children() as $block)
		{
			$details .= "<div class='shipon_details_container' style='width:".$block['width'].";'>
							<div class='shipon_details_title'>".text((string)$block['title'])."</div>
							<div class='shipon_details_block_container'>";
			
			foreach($block->children() as $detail)
			{
				$details .= "	<div class='shipon_details_block' style='width:".$detail['width'].";'>
									<span class='shipon_details_label' style='width:22%;'>".text((string)$detail).":</span>
									<span class='shipon_details_data'>".$data[(string)$detail['key']]."</span>
								</div>";
			}
										
			$details .= "	</div>
						 </div>";
		}
				
		$details .= "</div>";
		
		$this->html['shipon_tracking_details_result'] = $details;
	}
    
    function send_order_summery() {
       $newData = ''; 

       if(strpos($_REQUEST['data']['id'],"_") !== false)
		{
		  $tmp = explode("_",$_REQUEST['data']['id']);
		  $Pbnum        = (int)$tmp[0];
		  $this->user['bill_to_code'] = $tmp[1];
		}

        $email = $_REQUEST['data']['email'];
        
        $findOrder = query("SELECT id,ext_id,data FROM `".$this->user['database']."`.order_email_info WHERE ext_id=".$Pbnum);
        $record = fetch($findOrder);

        if (empty($record)) {
            $data = json_encode(array('emailRecords'=>array(array('email'=>$email ,'insertTime'=>date('Y-m-d H:i:s')))));
            $insertNewRecord = query("insert into `".$this->user['database']."`.order_email_info (`ext_id`,`data`) values (" . $Pbnum . ",'" . $data . "')");
        } else {
            $data = array('email'=>$email ,'insertTime'=>date('Y-m-d H:i:s'));
            $emailRecords = json_decode($record['data'],true);

            if (count($emailRecords['emailRecords']) < 20) { 
                    array_push($emailRecords['emailRecords'],$data);
                    $newData =json_encode($emailRecords);
                    $updateNewRecord = query("update `".$this->user['database']."`.order_email_info SET `data`='" . $newData . "' WHERE ext_id =" . $Pbnum);
            } 

        } 
  
 
        $req['Pbnum']           = $Pbnum;
        $req['bill_to_code']    = $this->user['bill_to_code'];             
        $shipment               = new Shipment('',$this->user['database']);
        $integrate              = new IntegrationHandler($this->user);
        $arrTrackingRequest     = $integrate->process_request('tracking_details_request',$req);

        $degama                 = new IntegrationHandler($this->user);
        $handshake              = $degama->process_request("handshake");        
        $shipment->ext_id       = $arrTrackingRequest['Pbnum'];
        $shipment->timestamp    = $arrTrackingRequest['ProDate'];
        $shipment->bill_name    = (string)$handshake->Name;
        $shipment->bill_account = $req['bill_to_code'];
        $shipment->ship_email   = $email;
        $shipment->division     = $arrTrackingRequest['Division'];
        $shipment->service      = $arrTrackingRequest['service'];
        $shipment->ship_contact = $arrTrackingRequest['RefName'];
        $shipment->ship_name    = $arrTrackingRequest['ShipName'];
        $shipment->ship_street1 = $arrTrackingRequest['Add1'];
        $shipment->ship_street2 = $arrTrackingRequest['Add2'];
        $shipment->ship_city    = $arrTrackingRequest['City'];
        $shipment->ship_province    = $arrTrackingRequest['Prv'];
        $shipment->ship_postal  = $arrTrackingRequest['Postal'];
        $shipment->cons_name    = $arrTrackingRequest['ConsigName'];
        $shipment->cons_contact = $arrTrackingRequest['ConRefName'];
        $shipment->cons_street1 = $arrTrackingRequest['ConAdd1'];
        $shipment->cons_street2 = $arrTrackingRequest['ConAdd2'];
        $shipment->cons_city    = $arrTrackingRequest['ConCity'];
        $shipment->cons_province    = $arrTrackingRequest['ConPrv'];
        $shipment->cons_postal  = $arrTrackingRequest['ConPostal'];
        $shipment->pup_date     = $arrTrackingRequest['PupDate'];  
        $shipment->pup_time     = $arrTrackingRequest['PupTime'];
        $shipment->total_pieces = $arrTrackingRequest['Pieces'];
        $shipment->total_weight = $arrTrackingRequest['Weight'];
        //$shipment->uom_weight = $arrTrackingRequest['W-Uom'];
        //$shipment->uom_dim = '';
		
        $shipment->goods = array();
        $good[0] = array(
						'packaging'       => $arrTrackingRequest['pkg'],
						'particulars'     => $arrTrackingRequest['ComDescr'],
						'commodity'       => $arrTrackingRequest['ComDescr'],
						'length'          => $arrTrackingRequest['length'],
						'width'           => $arrTrackingRequest['width'],
						'height'          => $arrTrackingRequest['height']
					);        

        $shipment->goods= $good;
        $shipment->equipment                =  $arrTrackingRequest['Equipment'];

        Hook::Run("post","shipment",$this->user['folder'],$this->user,$shipment);

    }

    
}

?>
