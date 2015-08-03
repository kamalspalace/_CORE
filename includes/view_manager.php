<?php
include("includes/pdo.php");
include("includes/config.php");
include("includes/settings.php");
include("includes/session.php");
include("includes/smarty.php");
include("includes/mailer.php");
include("includes/hook.php");
include("includes/view.php");

// TO-DO: load these controllers dynamically instead of all at once
include("controllers/account_manager.php");
include("controllers/address_book.php");
include("controllers/history.php");
include("controllers/settings.php");
include("integration/integration.php");
include("controllers/shipment.php");
include("controllers/shiponline.php");
include("controllers/tracking.php");
include("controllers/cyfe.php");

require('includes/aws/aws-autoloader.php');
use Aws\S3\S3Client;

class ViewManager
{
	var $user = array();
	var $return = array();
	var $session;
	var $error;
	
	function __constructor() {}

	function process_request($action)
	{   
		global $error_handler;

		if($this->get_user_info())
		{
                         
			if($action == 'session_ttl')
			{
			  $s = new Session($this->user);
			  if($s->initByID($_REQUEST['sid']))
			    echo $s->keepAlive();
			  else
			    echo "0";
			  return;
			}

			if($action == 'cyfe')
			{
			  $Cyfe = new Cyfe($_REQUEST['user']);
			  $Cyfe->$_REQUEST['view_action']();
			}

			$error_handler->init($this->user);
			if($this->check_if_logged_in())
			{   
				$error_handler->init($this->user);
				$this->return['auth'] = 1;
				$view = isset($_REQUEST['view']) ? $_REQUEST['view'] : "";
				$view_action = isset($_REQUEST['view_action']) ? $_REQUEST['view_action'] : "";

		                if ($action != 'autocomplete' || $action != 'ext_track' || $action != 'notify') {    
                    			$info = array(
                	        	'action' => $_REQUEST['action'],
					'view_action' => isset($_REQUEST['view']) ? $_REQUEST['view'] : '',
                        		'session_id' => $_REQUEST['sid'],
					'data' => isset($_REQUEST['data']) ? $_REQUEST['data'] : '',
                        		'user_id' => $this->user['uid'],
                        		'user_db' => $this->user['database'],
					'browser' => $_REQUEST['browser'],
					'browser_version' => $_REQUEST['browser_version'],
					'os' => $_REQUEST['os'],
					'ip' => $_REQUEST['ip']);
                    
                    			HOOK::Log("post","activitylog",$this->user['folder'],$info);                       
                		}   
         
				switch($action)
				{
					case 'view':
						if(!isset($this->user['status']))	{$view = 'tracking';}
						$iView = new $view($this->user, $this->return);
						$custom_data = isset($_REQUEST['data']) ? $_REQUEST['data'] : "";
						$iView->$view_action($custom_data);
						$this->return = $iView->get_return();

						$this->return['error'] = $error_handler->error_message;
						print json_encode($this->return);
					break;
	
					case 'integrate':
						$integrate = new IntegrationHandler($this->user);
						$integrate->process_request($view_action,$_POST);
						$this->return['error'] = $error_handler->error_message;
						print json_encode($this->return);
					break;

					case 'shiponline':
						$shiponline = new shiponlineHandler($this->user);
						$custom_data = isset($_REQUEST['data']) ? $_REQUEST['data'] : "";
						$this->return = $shiponline->$view_action($_REQUEST);
						//$this->return['error'] = $error_handler->error_message;
						print json_encode($this->return);
					break;
				
					default: $this->$action();
				}
			}
			else
			{
				if(method_exists($this, $action))
					$this->$action();
				else
				{
					$backup = array();
					$backup['action'] = $_REQUEST['action'];
					$backup['view'] = $_REQUEST['view'];
					$backup['view_action'] = $_REQUEST['view_action'];
					$this->return['data'] = $backup;
					$this->return['auth'] = 0;
					
					$this->return['error'] = $error_handler->error_message;
					print json_encode($this->return);
				}
			}
		}
	}

	function init()
	{
		$init =  "<style type='text/css'>".file_get_contents('styles/qtip.css', true)."</style>
				  <style type='text/css'>".file_get_contents('styles/global.css', true)."</style>
				  <style type='text/css'>".file_get_contents('styles/datepicker.css', true)."</style>
				  <style type='text/css'>".file_get_contents($this->user['folder'].'/styles/layout.css', true)."</style>
				  <style type='text/css'>".file_get_contents($this->user['folder'].'/styles/theme.css', true)."</style>
				  <style type='text/css'>".file_get_contents($this->user['folder'].'/styles/datepicker.css', true)."</style>";

		$init .= "<div id='shipon_wrapper'>
					<div id='shipon_header_wrapper'>
						<div id='shipon_header'>";
					
		$promo = '';

		if($this->check_if_logged_in())
			$init .= $this->parse_header_xml();
		else
		{
			if($this->user['remote_auth'] == 0)
				$init .= $this->parse_login_xml();
			else
			{
				$init .= "<div id='shipon_login_inputs'>".text('remote_login_message')."</div>";
				$promo .= $this->parse_promo_xml();
			}
		}
			
		$init .= "		</div>
					</div>
				<div id='shipon_content'>".$promo."</div>
				<div id='shipon_return'></div>
                <div id='shipon_overlay'></div>                                    
				<div id='shipon_loading'>Loading</div>";

		$init .=	"</div>		
			<script type='text/javascript'>".file_get_contents('scripts/jquery.imgpreview.js', true)."</script>
			<script type='text/javascript'>".file_get_contents('scripts/jquery.outside.js', true)."</script>
			<script type='text/javascript'>".file_get_contents('scripts/qtip.js', true)."</script>
			<script type='text/javascript'>".file_get_contents('scripts/validate.js', true)."</script>
			<script type='text/javascript'>".file_get_contents('scripts/additional-methods.js', true)."</script>    
			<script type='text/javascript'>".file_get_contents('scripts/hashchange.js', true)."</script>
			<script type='text/javascript'>".file_get_contents('scripts/timepicker.js', true)."</script>
			<script type='text/javascript'>".file_get_contents('scripts/datepicker.js', true)."</script>
			<script type='text/javascript' src='http://maps.google.com/maps/api/js?sensor=false&callback=initialize'></script>    
			<script type='text/javascript'>".file_get_contents('scripts/core.js', true)."</script>
			<script type='text/javascript'>".file_get_contents($this->user['folder'].'/scripts/custom.js', true)."</script>
			<script type='text/javascript'>hide_overlay();</script>";


		print $init;
	}

	function login($relogin = false)
	{
		global $error_handler;

		Hook::Run("pre","login",$this->user['folder']);		
		$login = query("SELECT * FROM `".$this->user['database']."`.accounts WHERE username=? AND password=?", array($_REQUEST['shipon_user'], md5($_REQUEST['shipon_pass'])));
		$account = fetch($login);
		$return = array();
		if(count($account) && $account['active'] == '1')
		{
			$group_result = query("SELECT * FROM `".$this->user['database']."`.accounts
					       LEFT JOIN `".$this->user['database']."`.account_groups 
					       ON `".$this->user['database']."`.accounts.group_id = `".$this->user['database']."`.account_groups.id 
					       WHERE `".$this->user['database']."`.accounts.id=".$account['id']);
			$group = fetch($group_result);
			$this->user['group'] = $group['group_id'];
			$this->user['name']  = $group['name'];
                        $this->user['username'] = $group['username'];
                        $this->user['phone'] = $group['phone'];
                        $this->user['email'] = $group['email'];
			$this->user['ext']   = $group['ext'];
			$this->user['type']  = $account['type'];
			$this->user['uid']   = $account['id'];
			$this->user['bill_to_code'] = $group['bill_to_code']; // TO FIX: this shouldn't be done this way
			$this->user['carrier'] = $group['carrier'];	
			$this->user['status'] = 1;

                        $this->session = new Session($this->user);
			$this->session->Init();

			$this->return['auth'] = 1;

			if($relogin == true)
			{	
				$return['shipon_header'] = $this->parse_header_xml(true); // we do this incase the user changes and their access level is different
				$return['shipon_return'] = "<script type='text/javascript'>show_overlay('#shipon_wrapper'); jQuery(window).trigger('hashchange');</script>";
			   	 $data = array(
                        		'action' => 'relogin',
					'view_action' => 'relogin',
                        		'session_id' => $_REQUEST['sid'],    
					'data' => '',
                        		'user_id' => $this->user['uid'],
                        		'user_db' => $this->user['database'],
                                	'browser' => $_REQUEST['browser'],
                                	'browser_version' => $_REQUEST['browser_version'],
                                	'os' => $_REQUEST['os'],
					'ip' => $_REQUEST['ip']
                			);
             			}
            		 else {   
				$return['shipon_header'] = $this->parse_header_xml();
				$data = array(
                            		'action' => 'login',
			    		'view_action' => 'login',
                            		'session_id' => $_REQUEST['sid'],    
					'data'  => '',
                            		'user_id' => $this->user['uid'],
                            		'user_db' => $this->user['database'],
                            		'browser' => $_REQUEST['browser'],
			    		'browser_version' => $_REQUEST['browser_version'],
                            		'os' => $_REQUEST['os'],
			    		'ip' => $_REQUEST['ip']
                		);
			}
            		HOOK::Log("post","activitylog",$this->user['folder'],$data); 
			if(! Hook::Run("post","login",$this->user['folder'],$this->user,$this->session,$this->return)) {

			  $this->return['auth'] = 0;

                          query("DELETE FROM `".$this->user['database']."`.sessions WHERE session_id='".$_REQUEST['sid']."'");
                          query("DELETE FROM `".$this->user['database']."`.session_data WHERE session_id='".$_REQUEST['sid']."'");

			  unset($this->return['html']);
			  print json_encode($this->return);
			  return false; 
			}
			
		}
		else
		{
			if($relogin == true)
				$return['shipon_return'] = "<script type='text/javascript'>jQuery('#ui-dialog-title-shipon_login_popup').html('Invalid user/password.'); shipon_open_popup('#shipon_login_popup'); </script>";
			else
			{
				$this->return['auth'] = 0;
				$this->return['alert'] = "Invalid login credentials. Please try again.";
			}
		}

		$this->return['html'] = $return;
		$this->return['error'] = $error_handler->error_message;
		print json_encode($this->return);
	}

	function relogin()
	{
		$this->login(true);
	}
	
	function logout()
	{
		global $error_handler;
		
		if(isset($this->return['auth']) && $this->return['auth'] == 1)
		{
			query("DELETE FROM `".$this->user['database']."`.sessions WHERE id=".$this->user['uid']);
			query("DELETE FROM `".$this->user['database']."`.session_data WHERE session_id='".$_REQUEST['sid']."'");
		}
		
		$init = "<div id='shipon_header_wrapper'>
					<div id='shipon_header'>";
		
		$promo = '';
		if($this->user['remote_auth'] == 0)
			$init .= $this->parse_login_xml();
		else
		{				
			$init .= "<div id='shipon_login_inputs'>".text('remote_login_message')."</div>
						<script type='text/javascript'>get_user_info();</script>";
			$promo .= $this->parse_promo_xml();
		}
		
		$init .= "	</div>
				</div>
				<div id='shipon_content'>".$promo."</div>
				<div id='shipon_return'></div>
                <div id='shipon_overlay'></div>
				<div id='shipon_loading'>Loading</div>";

		$this->return['html']['shipon_wrapper'] = $init;

		$this->return['error'] = $error_handler->error_message;
        
		print json_encode($this->return);
	}

	// External tracking function from a tenant's website
	function ext_track()
	{
		$alpha = $_REQUEST['alpha'];
		$pbnum = $_REQUEST['pbnum'];

		if(empty($alpha) && empty($pbnum))
		{
			$this->return['error'] = "Invalid tracking info.";
			die(json_encode($this->return));
		}

		// To-do find a way to cross-reference a BTC with an Order # in Degama
		$shipmentbtc = query("SELECT id, ext_id, bill_account FROM `".$this->user['database']."`.shipments WHERE ext_id=? OR ext_alpha=?",array($pbnum,$alpha));
		$shipmentBtc = fetch($shipmentbtc);

		//if(empty($shipmentBtc['bill_account']) || empty($shipmentBtc['id']) || empty($shipmentBtc['ext_id']))
		//{
		//	$this->return['error'] = "Cannot retrieve tracking info.";
		//	die(json_encode($this->return));
		//}

		$req = array();
		$req['Pbnum'] = (empty($pbnum) ? $shipmentBtc['ext_id'] : $pbnum);
		$req['Alpha'] = $alpha;
		$req['bill_to_code'] = $shipmentBtc['bill_account'];

        $integrate = new IntegrationHandler($this->user);
        $data = $integrate->process_request('tracking_details_request',$req);
		$data['bill_to_code'] = $shipmentBtc;

		$view = new tracking($this->user, $this->return);
		
		Hook::Run("pre","tracking_view",$this->user['folder'],$data,$view->user);

		$view->smarty->assign('request',$req);
		$view->smarty->assign('data',$data);

        print $view->smarty->fetch('tracking/tracking_view_web.tpl');
	}

        function notify()
        {
                        $this->user['group'] = 1;
                        $this->user['status'] = 1; 
			
			print Hook::Run("cron","shipment",$this->user['folder'],$this->user);
        }
	
	function get_info()
	{
		global $error_handler;
		
		$this->return['auth'] = 0;
		if($this->get_user_info())
		{
			if($this->check_if_logged_in())
			{
				$this->return['auth'] = 1;
				$this->return['name'] = $this->user['name'];
			}
		}
		
		$this->return['error'] = $error_handler->error_message;
		print json_encode($this->return);
	}
	
	function view_pdf()
	{
		$id_result = query("SELECT id FROM `".$this->user['database']."`.shipments WHERE token=?",array($_REQUEST['token']));
		$id_array = fetch($id_result);
		
		$pdf_xml = file_get_contents($this->user['folder']."/templates/pdf.xml");
		$xml = new SimpleXMLElement($pdf_xml);

		$pdf .= "<embed src='shiponline/api?action=generate_pdf&id=".$id_array['id']."' type='application/pdf' width='".$xml->pdf_width."' height='".$xml->pdf_height."'></embed>";

		print $pdf;
		//$this->return['html']['shipon_content'] = $pdf;
		
		//print json_encode($this->return);
	}
	
	function generate_document()
	{
		$settings = Settings::get($this->user['database'])->toArray();

        	if(!isset($_POST['link'])) die();

		$host = "http://".$settings['aws_host_url'].'/';

		if(substr($_POST['link'],0,5) == 'EZPRO')
		  $prefix = "EZPRO";
		else
		  $prefix = "DTMS";

		// Instantiate S3 Client
                $S3Client = S3Client::factory(array(
                                                'key'    => $settings['aws_access_key_id'],
                                                'secret' => $settings['aws_secret_access_key']
                                             ));

		switch($prefix)
		{
                        case "EZPRO":
                                $year   = substr($_POST['link'],5,4);
                                $month  = substr($_POST['link'],9,2);
                                $search = "EZPRO".$year.$month;
                                $result = $S3Client->listObjects(array(
                                                                   'Bucket' => $settings['aws_bucket'],
                                                                   'MaxKeys' => 1,
                                                                   'Prefix' => "EZPRO/POD/".$year."/".$month.str_replace($search,'/',$_POST['link'])
                                                                ));
                        break;

			default:
				$year  = substr($_POST['link'],5,4);
				$month = substr($_POST['link'],10,2);
				$search = "DTMS_".$year."_".$month."_";
	        		$result = $S3Client->listObjects(array(
                               					   'Bucket' => $settings['aws_bucket'],
                               					   'MaxKeys' => 1,
                               					   'Prefix' => "DTMS/".$year."/".$month.str_replace($search,'/',$_POST['link'])."."
                        					));
			break;
		}

        if(isset($result['Contents']))
          $link  = $host . $result['Contents'][0]['Key'];
       	else
          die();

	echo $link;

        $data = file_get_contents($link);
        
        $pdf = new PDFlib();
        
        $pdf->begin_document("","");	
			for($frame = 1; /* */; $frame++)
			{
				$pdf->create_pvf("/tracking/pvf/image", $data, "");
				$bol = $pdf->load_image("auto", "/tracking/pvf/image","page=".$frame);
				
				if ($bol == 0)
				{
					 break;
				}
			
				$imagewidth = $pdf->get_value("imagewidth", $bol) * 72 / $pdf->get_value("resx", $bol);
				$imageheight = $pdf->get_value("imageheight", $bol) * 72 / $pdf->get_value("resy", $bol);
			
				$pdf->begin_page_ext($imagewidth, $imageheight, "");
					$pdf->fit_image($bol, 0, 0, "");
				$pdf->end_page_ext("");
		
				$pdf->delete_pvf("/tracking/pvf/image");
			}
		
		$pdf->end_document("");

		$buf = $pdf->get_buffer();
		
		print $buf;
	}

	function generate_pdf()
	{
		define('FPDF_FONTPATH','includes/pdf/font/');
		require_once('includes/pdf/fpdf.php');
		require_once('includes/pdf/fpdi.php');
		
		//get layout xml
		$pdf_xml = file_get_contents($this->user['folder']."/templates/pdf.xml");

		// if we state explicitly to show rates on waybill, then we use a different template
		if(isset($_REQUEST['show_rates']) && $_REQUEST['show_rates'] > 0)
		  $pdf_xml = file_get_contents($this->user['folder']."/templates/pdf_rates.xml");

		$xml = new SimpleXMLElement($pdf_xml);

		$pdf = new AlphaPDI('P', 'pt', array($xml->pdf_pt_width, $xml->pdf_pt_height));
		
		$pdf->SetAutoPageBreak(false);

		$pdf->AddPage();

		$pdf->setSourceFile($this->user['folder']."/pdf/default.pdf");

		$tplIdx = $pdf->importPage(1);

		$pdf->useTemplate($tplIdx, 0, 0, 0, 0, false);

		// Check for external shipment data
		if(isset($_REQUEST['bill_id']))
		{	
			$req = array();
			$req['pbnum'] = $_REQUEST['bill_id'];
			$req['bill_to_code'] = $this->user['bill_to_code']; 

                	$integrate = new IntegrationHandler($this->user);
                	$data = $integrate->process_request('order_details_request',$req);

			// Prevent order numbers from being inputted sequentially
			if($data == false)
			  return false;

			$data['sent'] = 1;

			
		}
		else
		{
			$shipment = new Shipment($_REQUEST['id'],$this->user);
			$data = $shipment->toArray();
		}	


		$data['ship_name'] = clean_string($data['ship_name']);
                $data['ship_street1'] = clean_string($data['ship_street1']);
                $data['ship_street2'] = clean_string($data['ship_street2']);
		$data['ship_city'] = clean_string($data['ship_city']);
	        $data['cons_name'] = clean_string($data['cons_name']);	
		$data['cons_street1'] = clean_string($data['cons_street1']);
                $data['cons_street2'] = clean_string($data['cons_street2']);
		$data['cons_city'] = clean_string($data['cons_city']);

		$xml_data = $xml->LAYOUT->DATA->children();
		$radios_data = $xml->LAYOUT->RADIOS->children();
		$goods_data = $xml->LAYOUT->GOODS->column_data->children();
		$select_data = $xml->LAYOUT->SELECT->children();
		$custom_data = $xml->LAYOUT->CUSTOM->children();


                $pdf->SetFont('Helvetica', 'B', '12');
		$pdf->SetTextColor(0,0,0);
		$pdf->SetAlpha(0.75);

		foreach($xml_data as $entry)
		{
			if($entry['size']) $pdf->SetFontSize($entry['size']);
			if (!$entry["multiline"]) {
				$pdf->SetXY($entry['x'], $entry['y']);
			}
			if (isset($entry["maxlength"]) && strlen($data[(string)$entry]) > $entry["maxlength"])
			{
				$data[(string)$entry] = substr($data[(string)$entry],0,(int)$entry["maxlength"]);
			} 
			if($entry["array"] && isset($data[(string)$entry['array']][(string)$entry]))
			{
				if($entry["multiline"]) 			$pdf->MultiCell($entry['width'], $entry['height'], $data[(string)$entry['array']][(string)$entry]);
				else if($entry["letter_spacing"]) 	$pdf->CellFitSpaceForce($entry["width"], 0, $data[(string)$entry['array']][(string)$entry]);
				else								$pdf->Write(0, strtoupper($data[(string)$entry['array']][(string)$entry]));
			}
			elseif(isset($data[(string)$entry]))
			{
	            			if($entry["multiline"]) {
					
						if ($entry == 'pup_note' && $data[(string)$entry] != '') {
							$data[(string)$entry] = "PU-" . $data[(string)$entry]; 
						}
						else if ($entry == 'del_note' && $data[(string)$entry] != '') {
							$data[(string)$entry] = "DL-" . $data[(string)$entry];
						}

	                    $textRaw  = wordwrap($data[(string)$entry],31,'\n');
	                    $wrapArr = explode('\n',$textRaw);

	                    $i = 0;
	                    foreach($wrapArr as $line)
	                    {
	                            if($i == 0)
	                              $pdf->SetXY($entry['x'], $entry['y']);
	                            else
	                              $pdf->SetXY($entry['x'], ((int)$entry['y'] + ($i * 10)));

	                            $pdf->MultiCell($entry['width'], 0,$line);
	                            $i++;
	                    }
	            }
				else if($entry["letter_spacing"]) {
					if (isset($entry["type"]) && $entry["type"] == 'date') {
						$dateArr = explode('/',$data[(string)$entry]);
						$i = 0;
						foreach ($dateArr as $line) 
						{
							if ($i==1)
								$pdf->SetXY($entry['x'],$entry['y']);
							else {
								if ($i==0)
									$pdf->SetXY(((int)$entry['x']+30),$entry['y']);
								else
									$pdf->SetXY(((int)$entry['x']+($i*30)),$entry['y']);
							}	
							if ($i == 2)
								$pdf->MultiCell($entry['width'], 0,substr($line,-2));
							else 
								$pdf->MultiCell($entry['width'], 0,$line);
	                        
							$i++;
						}
					} else {
						$pdf->CellFitSpaceForce($entry["width"], 0, $data[(string)$entry]);
					}	
				}
				else								$pdf->Write(0, strtoupper($data[(string)$entry]));
			}
			else
			{   
				$pdf->Write(0, strtoupper((string)$entry));
			}
		}
		
		foreach($radios_data as $radio)
		{
			if(isset($data[(string)$radio->key]))
			{
				$radio_value = $data[(string)$radio->key];
				$offset_mult = (int)$radio->options->$radio_value;
				if($radio['orient'] == 'horizontal')    $pdf->Rect($radio['x'] * $offset_mult, $radio['y'], $radio['size'], $radio['size'], 'F');
				else if($radio['orient'] == 'vertical') $pdf->Rect($radio['x'], $radio['y'] * $offset_mult, $radio['size'], $radio['size'], 'F');
			}
		}
		
		foreach($select_data as $select)
		{
			if($select['size']) $pdf->SetFontSize($select['size']);
			$pdf->SetXY($select['x'], $select['y']);
			if($select["array"] && isset($data[(string)$select['array']][(string)$select->key])) $select_value = $data[(string)$select['array']][(string)$select->key];
			else if(isset($data[(string)$select->key]))											 $select_value = $data[(string)$select->key];
			if(isset($select_value)) $pdf->Write(0, $select->options->$select_value);
		}
		
		$y = (int)$xml->LAYOUT->GOODS->table_start_y;
		if($xml->LAYOUT->GOODS->size) $pdf->SetFontSize($xml->LAYOUT->GOODS->size);
	  	foreach($data['goods'] as $good)
		{
			foreach($goods_data as $column)
			{
				$pdf->SetXY((int)$column->offset, $y);
				$string = "";
				foreach($column->keys->children() as $key)
				{
					$key_name = (string)$key->getName();
					switch($key_name)
					{
						case "entry":
							if(($key == 'length' || 
							    $key == 'width'  || 
							    $key == 'height' ||
							    $key == 'rate'   ||
							    $key == 'total') 
							    && isset($good[(string)$key]) && $good[(string)$key] < 1) 
							  $string .= "-";
							elseif($key == 'particulars' && ! isset($_REQUEST['bill_id']) && isset($good[(string)$key]) && strlen($good[(string)$key]) > 0) 
							  $string .= " - ".$good[(string)$key];
							elseif($key == 'pieceskids')
							  $string .= $data['skids'].'-'.$good['pieces'];
							else
							  if(isset($good[(string)$key])) 
							     $string .= $good[(string)$key]." ";
						break;
		
						case "select":
							$tag_name = $good[(string)$key->key];
							if($tag_name != 'COMMENT')
							  $string .= strtoupper($key->options->$tag_name)." ";
						break;

						case "static":
							$string .= $key;
						break;
					}
				}
				$pdf->Write(0, $string);
			}
			$y += (int)$xml->LAYOUT->GOODS->line_height;
		}
		
		foreach($custom_data as $entry)
		{
			$string = "";
			$pdf->SetXY($entry['x'], $entry['y']);
			if($entry['size']) $pdf->SetFontSize($entry['size']);
			if($entry['goods'])
			{
				foreach($data['goods'] as $good)
				{
					if ($entry['total']) {
						$key_count = $entry->count();
						$i = 0;
						$j = 0;
						foreach($entry->children() as $key)
						{
							$j = $j + (int)$good[(string)$key];
							$i++;
						}
						$string = $string + $j;
					} else {
						$key_count = $entry->count();
						$i = 0;
						foreach($entry->children() as $key)
						{
							$string .= $good[(string)$key];
							$i++;
							if($i < $key_count) $string .= $entry['key_seperator'];
						}
						$string .= $entry['data_seperator'];
					}	
				}
			}
			else
			{
				$key_count = $entry->count();
				$i = 0;
				foreach($entry->children() as $key)
				{
					if($entry["array"] && isset($data[(string)$entry['array']][(string)$key]))	$string .= $data[(string)$entry['array']][(string)$key];
					else																		$string .= $data[(string)$key];
					$i++;
					if($i < $key_count) $string .= $entry['key_seperator'];
				}
			}
			
			if($entry["multiline"]) $pdf->MultiCell($entry['width'], $entry['height'], $string);
			else					$pdf->Write(0, $string);
		}




		if($data['sent'] < 1) {

			// PENDING NOTICE
			$pdf->SetFillColor(0,0,0,0.5);
			$pdf->Rect(0,0,$xml->pdf_pt_width,$xml->pdf_pt_height,'F');
		
                	$pdf->SetFont('Helvetica', 'B', '14');

                	$y_notice = ceil(intval($xml->pdf_pt_height / 2));
                	$x_notice = intval($xml->pdf_pt_width) - 500;

                	$pdf->SetTextColor(250,245,25);

                	$pdf->SetXY($x_notice,$y_notice);
			$pdf->SetAlpha(1);
                	$pdf->Write(0,"This shipment is still pending; click submit order to process.");
		}

		print $pdf->Output('', 'S');
	}

	function text($tag)
	{

                $text = query("SELECT ".$_REQUEST['lang']." FROM `".$this->user['database']."`.localization WHERE tag='".$tag."' LIMIT 1");
                
                if(num_rows($text) > 0)
                {
                        $text = fetch($text);
                        return $text[$_REQUEST['lang']];
                }
                else
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

	}
	
	function get_shipment_data($id)
	{
		$addresses = array();
		
		$shipment_result = query("SELECT * FROM `".$this->user['database']."`.shipments WHERE id='".$id."'");
		$shipment = fetch($shipment_result);
		
		$sent = $shipment['sent'];
		
		if(!$sent)
		{
			$shipper_result = query("SELECT * FROM `".$this->user['database']."`.address_book WHERE id='".$shipment['shipper_id']."'");
			$addresses['shipper'] = fetch($shipper_result);
	
			$consignee_result = query("SELECT * FROM `".$this->user['database']."`.address_book WHERE id='".$shipment['consignee_id']."'");
			$addresses['consignee'] = fetch($consignee_result);
	
			if($shipment['payer_id'] != $shipment['consignee_id'] && $shipment['payer_id'] != $shipment['shipper_id'])
			{
				$payer_result = query("SELECT * FROM `".$this->user['database']."`.address_book WHERE id='".$shipment['payer_id']."'");
				$addresses['payer'] = fetch($payer_result);
			}
			else
			{
				if($shipment['payer_id'] == $shipment['consignee_id']) $addresses['payer'] = $addresses['consignee'];
				else $addresses['payer'] = $addresses['shipper'];
			}
		}
		else
		{
			$addresses = json_decode($shipment['sent_addresses'], true);
		}
				
		$goods = array();
		$goods['goods'] = json_decode($shipment['goods'], true);
		
		$data = array_merge($addresses, json_decode($shipment['data'], true), $goods);	
		$data['date'] = $shipment['date'];
		
		return $data;
	}
	
	function get_tracking_details_data($id)
	{
		$_REQUEST['pbnum'] = $id;
		$integrate = new IntegrationHandler($this->user);
		$data = $integrate->process_request('order_details_request');
		
		return $data;
	}

	function parse_login_xml()
	{
		$login_xml = file_get_contents($this->user['folder']."/templates/login.xml");
		$xml = new SimpleXMLElement($login_xml);

		$login = $xml->children();

		$header = "<div id='shipon_login_inputs'>";

		foreach($login as $input)
		{
			$header .= "<label class='shipon_inputlabel' for='".$input['name']."'> ".$input['text'].": </label>";
			$header .= "<input type='".$input['type']."' id='".$input['name']."' name='".$input['name']."' class='shipon_textinput'/>";
		}
		
		$header .= "	<a class='shipon_button' id='shipon_login' onclick='shipon_login();'> Login </a>
					</div>";

		return $header;
	}
	
	function parse_promo_xml()
	{
		$login_xml = file_get_contents($this->user['folder']."/templates/login_promotion.xml");
		$xml = new SimpleXMLElement($login_xml);

		$promo  = "<div id='shipon_login_promo'>";
		$promo .= $xml->contents->asXML();
		$promo .= "</div>";

		return $promo;
	}

	function parse_login_popup()
	{
		$login_xml = file_get_contents($this->user['folder']."/templates/login.xml");
		$xml = new SimpleXMLElement($login_xml);

		$login = $xml->children();

		$header = "";

		$header .= "<div class='shipon_login_popup' id='shipon_login_popup' title='".text('please_relogin')."'>";
		foreach($login as $input)
		{
			$header .= "<div class='shipon_fieldblock' style='width:100%;'>
							<label class='shipon_inputlabel'> ".$input['text'].": </label>
							<input type='".$input['type']."' id='".$input['name']."' name='".$input['name']."' class='shipon_textinput'/>
						</div>";
		}
		$header .= "	<div class='shipon_fieldblock' style='width:100%;'>
							<a class='shipon_button' id='shipon_relogin_cancel' onclick='shipon_logout();'> Cancel </a>
							<a class='shipon_button' id='shipon_relogin_login' onclick='shipon_popup_login();'> Login </a>
						</div>
					        <input type='hidden' id='shipon_relogin' name='shipon_relogin' value='true' />
						<input type='hidden' id='shipon_action' name='shipon_action' />
						<input type='hidden' id='shipon_view' name='shipon_view' />
						<input type='hidden' id='shipon_view_action' name='shipon_view_action' />
					</div>";

		return $header;
	}

	function parse_header_xml($skipopup = false)
	{
		$header_xml = file_get_contents($this->user['folder']."/templates/header.xml");
		$xml = new SimpleXMLElement($header_xml);

		$views = $xml->CHILD->children();

		$header = "";

		foreach($views as $view)
		{
			$view_name = $view['view'];
			$view_text = '"'.$view['view'].'"';
			if($this->user['status'] == 1)
				$header .= "<a id='".$view['view']."' name='".$view['view']."' class='shipon_button shipon_header_button' href='#$view_name' ><span id='si_".$view['view']."' class='shipon_icon'>".$view['icon']."</span><br />".$this->text($view['view'])." </a>";
			else
				$header .= "<a id='".$view['view']."' name='".$view['view']."' class='shipon_button shipon_header_button' disabled='disabled'><span id='si_".$view['view']."' class='shipon_icon'>".$view['icon']."</span><br />".$this->text($view['view'])." </a>";
		}

		$controls = $xml->MASTER->children();

		$header .= "<div id='shipon_header_controls'>";
		if($this->user['type'] == '1')
			foreach($controls as $view)
			{
				$view_name = $view['view'];
				$view_text = '"'.$view['view'].'"';
				if($this->user['status'] == 1)
					$header .= "<a id='".$view['view']."' name='".$view['view']."' class='shipon_button shipon_header_button' href='#$view_name' ><span id='si_".$view['view']."' class='shipon_icon'>".$view['icon']."</span><br />".$this->text($view['view'])." </a>";
				else
					$header .= "<a id='".$view['view']."' name='".$view['view']."' class='shipon_button shipon_header_button' disabled='disabled'><span id='si_".$view['view']."' class='shipon_icon'>".$view['icon']."</span><br />".$this->text($view['view'])." </a>";
			}
		if($xml->LOGOUT)
			$header .= "<a id='shipon_logout' name='shipon_logout' class='shipon_button shipon_header_button' onclick='shipon_logout();' ><span id='si_".$view['view']."' class='shipon_icon'>&#xe020;</span><br />".text('logout')." </a>";
		$header .= "</div>";

		if(! $skipopup)
			$header .= $this->parse_login_popup();

		//$header .= "<div class='shipon_popup' id='shipon_login_popup' title='Log In'>"
		//				.generate_html_from_blocks($user, $popup_blocks).
		//			"</div>";

		return $header;
	}
	
	function autocomplete()
	{
		$data = "";	

		if($_POST['type'] == 'source')
		{
			$param = $_POST["term"];
			switch($_POST['dest'])
			{
				case 'address':
					$address = new address($this->user['database']);
					$address->group_id = $this->user['group'];
					$address->name = $param;
					$result = $address->Find();
					$i = 0;
					if(count($result))
					  foreach ($result as $row)
					  {
						$name = $row['name'];
						$city = $row['city'];
						$add_id = $row['id'];
								
						$data[$i] = array("id" => $i, 
								  "value" => $name, 
							  	  "label" => $name." (".$city.")",
								  "add_id" => $add_id);
                                                
                        			$i++;
					  }
				break;
				
				case 'province':
					$auto_xml = file_get_contents($this->user['folder']."/templates/autocomplete.xml");
					$xml = new SimpleXMLElement($auto_xml);
					$param = strtolower($param);
					$auto_data = $xml->province->$_POST['mod']->xpath("*[contains(translate(text(), 'ABCDEFGHJIKLMNOPQRSTUVWXYZ', 'abcdefghjiklmnopqrstuvwxyz'),'".$param."') or 
																		 contains(translate(name(), 'ABCDEFGHJIKLMNOPQRSTUVWXYZ', 'abcdefghjiklmnopqrstuvwxyz'),'".$param."')]");
					$i = 0;
					foreach($auto_data as $prov)
					{
						$data[$i] = array("id" => $i, 
							"value" => $prov->getName(), 
							"label" => $prov->getName()." (".$prov.")");
						$i++;
					}
				break;
			}
		}
		else
		{
			switch($_POST['dest'])
			{
				case 'address':
					$id_array = explode('_', $_POST['input_id']);
					$id = "#".$id_array[0].'_'.$id_array[1].'_';
                    			$address = new address($this->user['database'],$_POST['add_id']);
                    			$row = $address->toArray();
					$addressData = array();
					foreach($row as $key => $value) {
						if($key == 'province')
 						  $addressData[$id.'province'] = $value;
						else
						  $addressData[$id.$key] = $value;
					}
					$data = array("data" => $addressData);
				break;
			}
		}
		
		echo json_encode($data);
	}

	function get_address()
	{
        	$address = new address($this->user['database'],$_POST['add_id']);                
        	$row = $address->toArray();
		
		$data = array("data" => $row);

		echo json_encode($data);
	}

	function save_address()
	{   
		global $error_handler;

		if($this->return['auth'] == 1)
		{
			
			
			$error = autosave_address($this->user);
			
			if(strlen($error) > 1)
			{
				$this->return['data'] = $error;
				//print $valid;
			}
		}

		$this->return['error'] = $error_handler->error_message;
		print json_encode($this->return);
	}

	function delete_address()
	{
		global $error_handler;
		
		if($this->return['auth'] == 1) {}
                        $address = new address($this->user['database'],$_REQUEST['address_id']);        
                        $address->clearAddress();
			//query("DELETE FROM `".$this->user['database']."`.address_book WHERE id=?", array($_REQUEST['address_id']));

		$this->return['error'] = $error_handler->error_message;
		print json_encode($this->return);
	}
	
	function get_user_info()
	{
		$auth = query("SELECT * FROM clients WHERE username=? AND password=?", array($_REQUEST['user'], $_REQUEST['pass']));
		$user_array = fetch($auth);
	
		$this->user['folder'] = $user_array['folder_path'];
		$this->user['database'] = $user_array['database'];
		$this->user['remote_auth'] = $user_array['remote_authentication'];
		$this->user['client'] = $user_array['name'];
		$this->user['uid'] = '';
	
		return $auth;
	}	

	function check_if_logged_in()
	{

		if(isset($_REQUEST['shipon_user']) || isset($_REQUEST['shipon_pass']))
		{
			return false;
		    if(! isset($_REQUEST['shipon_relogin']))
		      if($this->login())
			return true;
		      else
			return false;
		    else
                      if($this->login(true)) //relogin
			return true;
		      else
			return false;

		    if($this->return['auth'] != 1) {
		        query("DELETE FROM `".$this->user['database']."`.sessions WHERE session_id='".$_REQUEST['sid']."'");
                        query("DELETE FROM `".$this->user['database']."`.session_data WHERE session_id='".$_REQUEST['sid']."'");

		      return false;
		    }
		}

		if($this->user['database'] == 'shipon_bol-demo')
		{
			$this->user['group'] = 1;
			$this->user['name'] = 'Demo';
			$this->user['type'] = 1;
			$this->user['id'] = 1;
			$this->user['status'] = 1;
			return true;
		}
		
		$login = query("SELECT * FROM `".$this->user['database']."`.sessions WHERE session_id=?",array($_REQUEST['sid']));
		$sessions = fetch($login);

		if(count($sessions))
		{

			$time = time();
			$delta = $time - $sessions['timestamp'];

            		if($delta < 1800)
			{ 


				query("UPDATE `".$this->user['database']."`.sessions SET timestamp=".time()." WHERE id=".$sessions['id']);
				$group_result = query("SELECT group_id,ext,email,type,name,username,phone FROM `".$this->user['database']."`.accounts WHERE id=".$sessions['id']);
				$group = fetch($group_result);
				$status_result = query("SELECT * FROM `".$this->user['database']."`.account_groups WHERE id=".$group['group_id']);
				$status = fetch($status_result);
				$this->user['group'] = $group['group_id'];
				$this->user['status'] = $status['status'];
				$this->user['account'] = $status['shipper_account_no'];
				$this->user['name'] = $group['name'];
				$this->user['username'] = $group['username'];
				$this->user['phone'] = $group['phone'];
				$this->user['ext'] = $group['ext'];
				$this->user['email'] = $group['email'];
				$this->user['type'] = $group['type'];
				$this->user['id'] = $sessions['id'];
				$this->user['uid'] = $sessions['id'];
				$this->user['carrier'] = $status['carrier'];
				$this->user['bill_to_code'] = $status['bill_to_code'];
				$this->session = new Session($this->user,$_REQUEST['sid']);
				return true;
			} else {
				return false;
            }    
		}
		return false;
	}
	
        function get_tooltip() {
            $tooltips = "";
            $con = $_REQUEST['id'];

            $col = query("SELECT * FROM `".$this->user['database']."`.tooltips WHERE selector ='".$con."'");
            $record = fetch($col);
            print json_encode($record);                    
        }
        
        function get_location() {
            $_REQUEST['pbnum'] = $_POST['pbnum'];
            $integrate = new IntegrationHandler($this->user);
            $data = $integrate->process_request('get_location_request');            
            print json_encode(array('success'=>true,'data'=>$data));
        }
		
		function change_bill_to_code() {
			$degama = new IntegrationHandler($this->user);
			$return = $degama->process_request("handshake",$_POST['bill_id']);

			$billto = (string)$return->{'Bill-To'};

			// This account is not the bill-to
			if(! empty($billto)) {
				$degama    = new IntegrationHandler($this->user);
				$return = $degama->process_request("handshake",$billto);				
			}

			// Canned response... We don't send the whole dump to the client
			$response = array();
			$response['Name']		  = (string)$return->Name;
			$response['Contact2']	  = (string)$return->Contact2;
			$response['Phone']	      = (string)$return->Phone;
			$response['Email']		  = (empty($return->Email)) ? '' : (string)$return->Email;
			$response['Street1']	  = (string)$return->Street1;
			$response['City'] 		  = (string)$return->City;
			$response['Province']     = (string)$return->Province;
			$response['Postal']		  = (string)$return->Postal;
			$response['Country']	  = (empty($return->Country)) ? 'CA' : (string)$return->Country;
			$response['bill_to_code'] = (string)$return->bill_to_code;

			print (json_encode($response)); 
		}
		

		function get_quotes()
		{
			$req['Pbnum']           = (int)$_REQUEST['pbnum'];
			$req['bill_to_code']    = $this->user['bill_to_code'];             

			
			$integrate              = new IntegrationHandler($this->user);
			$arrTrackingRequest     = $integrate->process_request('tracking_details_request',$req);
			//$degama                 = new IntegrationHandler($this->user);
			//$handshake              = $degama->process_request("handshake");


			if (!isset($arrTrackingRequest['error'])){
				if ($arrTrackingRequest['RecStatus']== 'QTE') {
					print (json_encode(array('success'=>true,'data'=>$arrTrackingRequest,'error'=>"")));
				} else {
					//print (json_encode(array('success'=>true,'error'=>true)));
				}  
			} else {
				print (json_encode(array('success'=>true,'error'=>true)));
			}	
		}
		
}

?>
