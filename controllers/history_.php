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
		$settings = $this->loadSettings("history");

		$search_string = "1";
		$s = "";
		$var_array = array();

		if(isset($_REQUEST['search_string']))
		{
			$s = $_REQUEST['search_string'];
			$search_string = "(id=? OR bill_id=? OR timestamp LIKE ? OR data LIKE ? OR goods LIKE ? OR sent_addresses LIKE ?)";
			$var_array = array($s, $s, "%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%");
		}
		
/*
		$sort_by = 'id';
		if(isset($_REQUEST['sort_by'])) $sort_by = $xml->content->column[(int)$_REQUEST['sort_by']]['key'];
		else							$_REQUEST['sort_by'] = '0';
		if(!isset($_REQUEST['sort_order']))	$_REQUEST['sort_order'] = "DESC";


		$history_result = query( "SELECT *
								FROM `".$this->user['database']."`.shipments
								WHERE group_id='".$this->user['group']."' AND ".$search_string."
								ORDER BY ".$sort_by." ".$_REQUEST['sort_order']." 
								LIMIT ".$data * $xml->entries_per_page.",".$xml->entries_per_page, $var_array);

*/

		$history_result = query( "SELECT *
					  FROM `".$this->user['database']."`.shipments
					  WHERE group_id='".$this->user['group']."' AND ".$search_string."
					  LIMIT 0,10");
		$history_result    = fetchAll($history_result);
		$history_result_n  = count($history_result);

		// Count total resultset
		$history_total     = query("SELECT id FROM `".$this->user['database']."`.shipments WHERE group_id='".$this->user['group']."' AND ".$search_string, $var_array);
		$history_total_n   = num_rows($history_total);

		// Divide and get number of pages
		$number_of_pages   = ceil($history_total_n / $settings['rows_per_page']);

		
/*
		$titlebar_inputs =  "<span class='shipon_titleblock'>".text('search').":<input type='text' class='shipon_input' id='shipon_history_search'></input></span>						 
						 <a class='shipon_button shipon_titlebutton' id='shipon_history_search_button' onclick='history_search()'> ".text('search')." </a>";
		
*/
		$this->smarty->assign('data',$history_result);
		$html = $this->smarty->fetch('history/history.tpl');	

/*
		$history = $this->get_field_header('100%', 'history', $titlebar_inputs);
		$history .= "<table id='shipon_history'>";

		$history .= "<tr>";
		$k = 0;
		$count = $xml->content->column->count();
		foreach($xml->content->children() as $column)
		{
			$sort_class = "";
			if($k == $_REQUEST['sort_by']) $sort_class = "class='shipon_sort_".$_REQUEST['sort_order']."'";
			if($k < $count - 1)	$history .= "<th ".$sort_class." onclick='sort_by(this)' width='".$column['width']."'>".text($column)."</th>";
			else				$history .= "<th ".$sort_class." width='".$column['width']."'>".text($column)."</th>";
			$k++;
		}
		$history .="</tr>";
		
		for($i = 0; $i < $xml->entries_per_page; $i++)
		{
			if($i < $result_count)
			{
				$shipment = fetch($history_result);

				$address_result = query("SELECT name
									 FROM `".$this->user['database']."`.address_book
									 WHERE id='".$shipment['consignee_id']."'");

				if(num_rows($address_result))
				{
					$address = fetch($address_result);
					$consignee = $address['name'];
				}
				else
					$consignee = "Address not found!";
					
				$sent = $shipment['sent'] == 1 ? 'sent' : '';
				
				$ship_id = strlen($shipment['bill_id']) > 0 ? "<a class='shipon_button shipon_history_button' href='#tracking#view_details-".$shipment['bill_id']."'>".$shipment['bill_id']."</a>" : $shipment['id'];
				
				$history .="<tr class='".$sent."'>
							<td>".$ship_id."</td>
							<td>".$shipment['timestamp']."</td>
							<td>".$consignee."</td>
							<td>".($status = $shipment['sent'] == 1 ? '<div class="shipon_sent">Sent</div>' : '<div class="shipon_pending">Pending</div>')."</td>
							<td>
								<a class='shipon_button shipon_history_button' href='#bol#recall-".$shipment['id']."'> ".text('recall')." </a>
								<a class='shipon_button shipon_history_button' href='#history#view_pdf-".$shipment['id']."'> ".text('view_pdf')." </a>
							</td>
						</tr>";
			}
			else
			{
				$history .= "<tr><td></td><td></td><td></td><td></td><td></td></tr>";
			}
		}

		$history .= "</table>
						<input type='hidden' id='shipon_history_sort_by' name='shipon_history_sort_by'></input>
						<input type='hidden' id='shipon_history_sort_order' name='shipon_history_sort_order'></input>";
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

	*/
		$this->html['shipon_content'] = $html;
	}

	function view_pdf($id)
	{
		$pdf_xml = file_get_contents($this->user['folder']."/templates/pdf.xml");
		$xml = new SimpleXMLElement($pdf_xml);

		$pdf = $this->get_field_header('100%', 'bill_of_lading', "<a class='shipon_button shipon_titlebutton' onclick='shipon_send(".$id.")'>".text('send')."</a>");

		$pdf .= "<embed src='shiponline/curl.php?action=generate_pdf&id=".$id."' type='application/pdf' width='".$xml->pdf_width."' height='".$xml->pdf_height."'></embed>";

		$pdf .= $this->get_pdf_footer($id);

		$this->html['shipon_content'] = $pdf;
	}
}

?>
