<?php

class history extends View
{
	function __construct($user, $return)
	{
		$this->user = $user;
		$this->return = $return;
		parent::__construct();
	}

	function init($data)
	{
		$history_xml = file_get_contents($this->user['folder']."/templates/history.xml");
		$xml = new SimpleXMLElement($history_xml);
		
		$req = array();
		$req['entries_per_page'] = 10;
		$req['search_string']    = isset($_REQUEST['search_string']) && $_REQUEST['search_string'] != 'Search' ? $_REQUEST['search_string'] : '';
		$req['sort_column']      = isset($_REQUEST['sort_by'])       ? $_REQUEST['sort_by']       : 'id';
		$req['sort_order']       = isset($_REQUEST['sort_order'])    ? $_REQUEST['sort_order']    : 'DESC';
		$req['start_row']        = isset($_REQUEST['data'])	     ? $_REQUEST['data'] * 10 + 1 : 1; 
		$req['bill_to_code']	 = $this->user['bill_to_code'];
		

		$search_string = "1";
		$s = "";
		$var_array = array();
		$fieldString = "id,ext_id,cons_name,sent,timestamp";
		if(isset($_REQUEST['search_string']) && $_REQUEST['search_string'] != "Search")
		{   
			$s = $_REQUEST['search_string'];
			$search_string = "(id LIKE ? OR bill_id LIKE ? OR timestamp LIKE ? OR cons_name LIKE ?)";
			$var_array = array("%".$s."%","%".$s."%", "%".$s."%", "%".$s."%");
		}
		$sort_by = 'id';
		if(isset($_REQUEST['sort_by'])) $sort_by = $xml->content->column[(int)$_REQUEST['sort_by']]['key'];
		else $_REQUEST['sort_by'] = '0';
		if(!isset($_REQUEST['sort_order']))	$_REQUEST['sort_order'] = "DESC";		
		$history_result = query("SELECT " . $fieldString  .  " FROM `".$this->user['database']."`.shipments
								WHERE group_id='".$this->user['group']."' AND ".$search_string." AND ext_id > 0 ORDER BY ".$sort_by." ".$_REQUEST['sort_order']." 
								 LIMIT ".$data * $xml->entries_per_page.",".$xml->entries_per_page, $var_array);

		$result_count = num_rows($history_result);

		$num_result = query("SELECT id FROM `".$this->user['database']."`.shipments WHERE group_id='".$this->user['group']."' AND ".$search_string."  AND ext_id > 0", $var_array);
		$num = num_rows($num_result);
		$pageCount = ceil($num / $xml->entries_per_page);

	//	$titlebar_inputs =  "<input type='text' class='shipon_input' id='shipon_history_search' value='Search'>";
		
	//	$history = $this->get_field_header('100%', 'history', $titlebar_inputs);

		for($i = 0; $i < $xml->entries_per_page; $i++)
		{
			if($i < $result_count)
			{
				$shipment[] = fetch($history_result);
			}
		}
		$history .= $this->get_paged_footer("#history", $xml->entries_per_page, $pageCount, $xml->page_numbers);
		
		if(isset($_REQUEST['search_string']))
		{
			$this->return['inputs']['shipon_history_search'] = $_REQUEST['search_string'];
		}
		if(isset($_REQUEST['sort_by']))
		{
			$this->return['inputs']['shipon_history_sort_by'] = $_REQUEST['sort_by'];
		}
		else
			$this->return['inputs']['shipon_history_sort_by'] = '0';
			
		if(isset($_REQUEST['sort_order']))
		{
			$this->return['inputs']['shipon_history_sort_order'] = $_REQUEST['sort_order'];
		}
		$pagination =  $this->get_paged_footer("#history", $xml->entries_per_page, $pageCount, $xml->page_numbers);
		$this->smarty->assign('shipment',$shipment);
		$this->smarty->assign('request',$req);
		$this->smarty->assign('pagination',$pagination);
		$this->html['shipon_content'] = $this->smarty->fetch('history/history.tpl');
	}

	function view_pdf($id)
	{
		$preferences_q = query("SELECT settings FROM `".$this->user['database']."`.account_settings WHERE group_id='".$this->user['group']."' ORDER BY id DESC");
                $preferences_r = fetch($preferences_q);
                $this->smarty->assign('preferences',json_decode($preferences_r['settings'],1));

		$shipment = new Shipment($id,$this->user);
		$this->smarty->assign('shipment',$shipment->toArray());
		$this->html['shipon_content'] = $this->smarty->fetch('history/view_pdf.tpl').$this->get_pdf_footer($id);
	}
}

?>
