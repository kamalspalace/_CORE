<?php

function parse_login_xml($user)
{
	$login_xml = file_get_contents($user."/templates/login.xml");
	$xml = new SimpleXMLElement($login_xml);

	$login = $xml->children();

	$header = "<div id='shipon_login_inputs'>";

	foreach($login as $input)
	{
		$header .= "<label class='shipon_inputlabel' for='".$input['name']."'> ".$input['text'].": </label>";
		$header .= "<input type='".$input['type']."' id='".$input['name']."' name='".$input['name']."' class='shipon_textinput'/>";
	}
	
	$header .= "<a class='shipon_button' id='shipon_login' onclick='shipon_login();'> Login </a>
			  </div>";

	return $header;
}

function parse_login_popup($user)
{
	$login_xml = file_get_contents($user."/templates/login.xml");
	$xml = new SimpleXMLElement($login_xml);

	$login = $xml->children();

	$header = "";

	$header .= "<div class='shipon_popup' id='shipon_login_popup' title='".text('login')."'>";
	foreach($login as $input)
	{
		$header .= "<label class='shipon_inputlabel' for='".$input['name']."'> ".$input['text'].": </label>";
		$header .= "<input type='".$input['type']."' id='".$input['name']."' name='".$input['name']."' class='shipon_textinput'/>";
	}
	$header .= "<a class='shipon_button' id='shipon_logout' onclick='shipon_logout();'> Cancel </a>";
	$header .= "<a class='shipon_button' id='shipon_login' onclick='shipon_login();'> Login </a>";
	$header .= "</div>";

	//return $header;
}

function parse_header_xml($user)
{
	$header_xml = file_get_contents($user['folder']."/templates/header.xml");
	$xml = new SimpleXMLElement($header_xml);

	$views = $xml->CHILD->children();

	$header = "";

	foreach($views as $view)
	{
		$view_name = $view['view'];
		$view_text = '"'.$view['view'].'"';
		$header .= "<a id='".$view['view']."' name='".$view['view']."' class='shipon_button shipon_header_button' href='#$view_name' > ".text($view['view'])." </a>";
	}

	$controls = $xml->MASTER->children();

	$header .= "<div id='shipon_header_controls'>";
		if($user['type'] == '1')
			foreach($controls as $view)
			{
				$view_name = $view['view'];
				$view_text = '"'.$view['view'].'"';
				$header .= "<a id='".$view['view']."' name='".$view['view']."' class='shipon_button shipon_header_button' href='#$view_name' > ".text($view['view'])." </a>";
			}
		$header .= "<a id='shipon_logout' name='shipon_logout' class='shipon_button shipon_header_button' onclick='shipon_logout();' > ".text('logout')." </a>";
	$header .= "</div>";

	//$header .= "<div class='shipon_popup' id='shipon_login_popup' title='Log In'>"
	//				.generate_html_from_blocks($user, $popup_blocks).
	//			"</div>";

	return $header;
}

function parse_bill_xml($user, $bol_id = 0, $action)
{
	$bill_xml = file_get_contents($user['folder']."/templates/bill_of_lading.xml");
	$xml = new SimpleXMLElement($bill_xml);

	$sections = $xml->children();

	$bill = "<form name='shipon_BOL' id='shipon_BOL' class='shipon_form' method='post'>";

	$bill .= "<div id='shipon_accordion'>";
	foreach($sections as $section)
	{
		$bill .= "<h3>".text($section['title'])."</h3>";
		$bill .= "<div class='shipon_section_content'>";
		$rows = $section->children();
		foreach($rows as $row)
		{
			$elements = $row->children();

			$bill .= "<div class='shipon_row'>";
			foreach($elements as $element)
			{
				$view_xml = file_get_contents("_CORE/templates/".$element['template'].".xml");
				$bill .= parse_field_xml($view_xml, $element['width'], $user['folder']);
			}
			$bill .= "</div>";
		}
		$bill .= "</div>";
	}
	$bill .= "</div>";

	$bill .= "<a class='shipon_button' id='shipon_back' onclick='shipon_back()'>Back</a>
			  <span id='shipon_output'></span>";

	switch($action)
	{
		case 'new':
			$bill .= "<a class='shipon_button' id='shipon_continue' onclick='validate_form(shipon_continue)'>Continue</a>";
		break;

		case 'edit':
			$bill .= "<a class='shipon_button' id='shipon_continue' onclick='validate_form(shipon_update)'>Continue</a>";
		break;
	}

	if($bol_id != 0)
	{
		$bill .= "<input type='hidden' name='shipon_bill_id' value='".$bol_id."' />
				  <span id='shipon_output'></span>";

		$shipment_data = query("SELECT * FROM `".$user['database']."`.shipments WHERE id='".$bol_id."' AND group_id='".$user['group']."'");

		if(num_rows($shipment_data))
		{
			$data = fetch($shipment_data);
			$json = json_decode($data['data'],true);

			$addresses = array();

			$shipper_data = query("SELECT * FROM `".$user['database']."`.address_book WHERE id='".$data['shipper_id']."'");
			format_address_array($addresses, $shipper_data, "shipon_shipper_");

			$consignee_data = query("SELECT * FROM `".$user['database']."`.address_book WHERE id='".$data['consignee_id']."'");			
			format_address_array($addresses, $consignee_data, "shipon_consignee_");

			if($data['payer_id'] != $data['consignee_id'] and $data['payer_id'] != $data['shipper_id'])
			{
				$payer_data = query("SELECT * FROM `".$user['database']."`.address_book WHERE id='".$data['payer_id']."'");
				format_address_array($addresses, $payer_data, "shipon_payer_");
				$json['shipon_freight_charges'] = 2;
			}
			else
			{
				if($data['payer_id'] == $data['shipper_id']) $addresses['shipon_freight_charges'] = 0;
				else $json['shipon_freight_charges'] = 1;
			}

			$addresses = json_encode($addresses);

			$json = json_encode($json);

			$bill .= "<script type='text/javascript'>fill_form('".$addresses."','".$json."');</script>";
		}
	}

	$bill .= "</form>";

	return $bill;
}

function parse_validation_xml($user)
{ 
	$valid_xml = file_get_contents($user['folder']."/templates/validation.xml");
	$xml = new SimpleXMLElement($valid_xml);

	$section_num = (int)$_REQUEST['section'];

	$fields = $xml->SECTION[$section_num]->children();

	$script = "<script type='text/javascript'>";
	foreach($fields as $field)
	{
		$rules = $field->children();

		foreach($rules as $rule)
		{
			$script .= "jQuery('#".$rule."').rules('add', 
						{
							required: true,";

							foreach($rule->attributes() as $attr => $val)
							{
								$script .= $attr.":".$val.",";
							}

			$script .="	});";
		}
	}
	$script .= "</script>";

	return $script;
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

function parse_pdf_xml($user, $id)
{
	//print "../".$user['folder']."/pdf/default.pdf";
	//print file_get_contents("../".$user['folder']."/pdf/default.pdf");
	$pdf_xml = file_get_contents($user['folder']."/templates/pdf.xml");
	$xml = new SimpleXMLElement($pdf_xml);

	$pdf = get_field_header('100%', 'bill_of_lading', "<a class='shipon_button shipon_titlebutton' href='#send-".$id."'>".text('send')."</a>");

	$pdf .= "<object data='curl.php?action=generate_pdf&id=".$id."' type='application/pdf' width='".$xml->pdf_width."' height='".$xml->pdf_height."'></object>";

	$pdf .= get_pdf_footer($id);

	return $pdf;
}

function parse_history_xml($info)
{
	$history_xml = file_get_contents($info['folder']."/templates/history.xml");
	$xml = new SimpleXMLElement($history_xml);

	$history_result = query( "SELECT *
								FROM `".$info['database']."`.shipments
								WHERE group_id='".$info['group']."'
								ORDER BY id
								LIMIT ".$_REQUEST['data'] * $xml->entries_per_page.",".$xml->entries_per_page);

	$result_count = mysql_num_rows($history_result);

	$num_result = query("SELECT id FROM `".$info['database']."`.shipments WHERE group_id='".$info['group']."'");
	$num = num_rows($num_result);
	$pageCount = ceil($num / $xml->entries_per_page);

	$history = get_field_header('100%', 'history', '');
	$history .= "<table id='shipon_history'>";

	$history .="<tr>
					<th width='".$xml->content->colomn[0]['width']."'>".text($xml->content->colomn[0])."</th>
					<th width='".$xml->content->colomn[1]['width']."'>".text($xml->content->colomn[1])."</th>
					<th width='".$xml->content->colomn[2]['width']."'>".text($xml->content->colomn[2])."</th>
					<th width='".$xml->content->colomn[3]['width']."'>".text($xml->content->colomn[3])."</th>
				</tr>";
	
	for($i = 0; $i < $xml->entries_per_page; $i++)
	{
		if($i < $result_count)
		{
			$shipment = fetch($history_result);

			$address_result = query("SELECT name
									 FROM `".$info['database']."`.address_book
									 WHERE id='".$shipment['consignee_id']."'");

			if(num_rows($address_result))
			{
				$address = fetch($address_result);
				$consignee = $address['name'];
			}
			else
				$consignee = "Address not found!";
			
			$history .="<tr>
							<td>".$shipment['id']."</td>
							<td>".$shipment['date']."</td>
							<td>".$consignee."</td>
							<td>
								<a class='shipon_button shipon_history_button' href='#recall-".$shipment['id']."'> ".text('recall')." </a>
								<a class='shipon_button shipon_history_button' href='#view_pdf-".$shipment['id']."'> ".text('view_pdf')." </a>
							</td>
						</tr>";
		}
		else
		{
			$history .= "<tr><td></td><td></td><td></td><td></td></tr>";
		}
	}

	$history .= "</table>";
	$history .= get_paged_footer("#history", $xml->entries_per_page, $pageCount, $xml->page_numbers);

	return $history;
}

function parse_address_book_xml($user)
{
	$book_xml = file_get_contents($user['folder']."/templates/address_book.xml");
	$xml = new SimpleXMLElement($book_xml);

	$addresses_result = query( "SELECT *
								FROM `".$user['database']."`.address_book
								WHERE group_id=".$user['group']."
								ORDER BY id
								LIMIT ".$_REQUEST['data'] * $xml->entries_per_page.",".$xml->entries_per_page);

	$num_result = query("SELECT id FROM `".$user['database']."`.address_book WHERE group_id='".$user['group']."'");
	$num = num_rows($num_result);
	$pageCount = ceil($num / $xml->entries_per_page);

	$result_count = num_rows($addresses_result);
	
	$address_book = get_field_header('100%', 'address_book', "<a class='shipon_button shipon_titlebutton'> New </a>");
	
	$address_book .= "<table id='shipon_address_book'>";
    
	for($i = 0; $i < $xml->entries_per_page; $i++)
	{
		if($i < $result_count)
		{
			$address = fetch($addresses_result);
			$address_book .= "<tr><td><div class='shipon_address_data'>";

			$blocks = $xml->content->children();
			foreach($blocks as $block)
			{
				$block_xml = new SimpleXMLElement($block->asXML());

				$address_book .= "<div class='shipon_fieldblock' style='width:".$block_xml['width'].";'> ";
				
				if(count($block_xml->label))
					$block_xml->label = text($block_xml->label).":";
					
				$elements = $block_xml->children();
				foreach($elements as $element)
				{
					$data = (string)$element['value'];

					if($data)
						$element['value'] = $address[$data];

					$address_book .= $element->asXML();
				}				

				$address_book .= " </div>";
			}

			$address_book .= "<input type='hidden' name='shipon_id' value='".$address['id']."' />";

			$address_book .=	"</div></td><td> <div class='shipon_address_controls'>
									<a class='shipon_button shipon_addressbook_button' onclick='shipon_address_delete(this)'>".text('delete')."</a>
									<a class='shipon_button shipon_addressbook_button' onclick='shipon_address_edit(this)'>".text('edit')."</a>
								 </div></td></tr>";
		}
		else
		{
			$address_book .= "
				<tr>
					<td><div class='shipon_address_data'></div></td>
					<td><div class='shipon_address_controls'></div></td>
				</tr>
			";
		}
	}

	$address_book .= "</table>";

	$blocks = $xml->content->children();	
	$hidden = '';
	foreach($blocks as $block)
	{
		$elements = $block->children();
		
		$elements[1]['type'] = 'hidden';
		$elements[1]['disabled'] = 'false';
		$elements[1]['value'] = '';
		$hidden .= $elements[1]->asXML();
	}
	$hidden .= "<input type='hidden' name='shipon_id' value='' />";
	$address_book .= "<div id='shipon_address_backup'>".$hidden."</div>";

	$address_book .= get_paged_footer("#address_book", $xml->entries_per_page, $pageCount, $xml->page_numbers);

	return $address_book;
}

function parse_tracking_xml($user)
{
	$tracking_xml = file_get_contents($user['folder']."/templates/tracking.xml");
	$xml = new SimpleXMLElement($tracking_xml);

	$titlebar_inputs =  "<span class='shipon_titleblock'>".text('date_from').":<input type='text' class='shipon_input shipon_datepicker' id='shipon_tracking_date_from'></input></span>
						 <span class='shipon_titleblock'>".text('date_to').":<input type='text' class='shipon_input shipon_datepicker' id='shipon_tracking_date_to'></input></span>
						 <a class='shipon_button shipon_titlebutton' id='shipon_tracking_update'> ".text('update')." </a>";

	$tracking = get_field_header('100%', 'tracking', $titlebar_inputs);

	$tracking .= "<table id='shipon_tracking'>
						<tr>";

	$column_count = 0;
	$columns = $xml->COLUMNS->children();
	foreach($columns as $column)
	{
		$tracking .= "<th>".text($column)."</th>";
		$column_count++;
	}

	$tracking .= "		</tr>";

	$result_count = 0;
	for($i = 0; $i < $xml->entries_per_page; $i++)
	{
		if($i < $result_count)
		{
			$tracking .="";
		}
		else
		{
			$tracking .= "<tr>";

			for($j = 0; $j < $column_count; $j++) $tracking .= "<td/>";

			$tracking .= "</tr>";
		}
	}

	$tracking .= "</table>";

	$tracking .= get_paged_footer("#address_book", $xml->entries_per_page, $pageCount, $xml->page_numbers);

	return $tracking;
}

function parse_accounts_xml($user)
{
	if($user['type'] != 1)
		return parse_bill_xml($user, 0, 'new');

	$accounts_xml = file_get_contents($user['folder']."/templates/accounts.xml");
	$xml = new SimpleXMLElement($accounts_xml);

	$accounts_result = query("  SELECT *
								FROM `".$user['database']."`.accounts
								WHERE group_id = ".$user['group']."
								ORDER BY id
								LIMIT ".$_REQUEST['data'] * $xml->entries_per_page.",".$xml->entries_per_page);

	$num_result = query("SELECT id FROM `".$user['database']."`.accounts WHERE group_id='".$user['group']."'");
	$num = num_rows($num_result);
	$pageCount = ceil($num / $xml->entries_per_page);

	$result_count = num_rows($accounts_result);
	
	$accounts = get_field_header('100%', 'manage_accounts', "<a class='shipon_button shipon_titlebutton' onclick='new_account()'> New </a>");
	
	$accounts .= "<table id='shipon_accounts'>";

	$accounts .="<tr>
					<th width='".$xml->content->colomn[0]['width']."'>".text($xml->content->colomn[0])."</th>
					<th width='".$xml->content->colomn[1]['width']."'>".text($xml->content->colomn[1])."</th>
					<th width='".$xml->content->colomn[2]['width']."'>".text($xml->content->colomn[2])."</th>
					<th width='".$xml->content->colomn[3]['width']."'>".text($xml->content->colomn[3])."</th>
				</tr>";
	
	for($i = 0; $i < $xml->entries_per_page; $i++)
	{
		if($i < $result_count)
		{
			$account = fetch($accounts_result);
			
			$accounts .="<tr>
							<td>".$account['id']."</td>
							<td>".$account['username']."</td>
							<td>".$account['name']."</td>
							<td>
								<a class='shipon_button shipon_accounts_button' '> ".text('edit')." </a>";
								if($account['type'] != 1) $accounts .= "<a class='shipon_button shipon_accounts_button' onclick='delete_account(this)'> ".text('delete')." </a>";
			$accounts .="	</td>
						</tr>";
		}
		else
		{
			$accounts .= "<tr><td></td><td></td><td></td><td></td></tr>";
		}
	}

	$accounts .= "</table>";

	$login_xml = file_get_contents($user['folder']."/templates/login.xml");
	$xml = new SimpleXMLElement($login_xml);

	$accounts .= "	<div id='shipon_account_popup' class='shipon_popup' title='".text('new_account')."'>
						<div class='shipon_fieldblock' style='width: 100%'>
							<label class='shipon_label'>".text('address_name')."</label>
							<input type='text' id='shipon_account_name' name='shipon_account_name' class='shipon_input'></input>
						</div>";
	
	foreach($xml->input as $input)
	{
		$accounts .= "	<div class='shipon_fieldblock' style='width: 100%'>
							<label class='shipon_label'>".$input['text']."</label>
							<input type='".$input['type']."' id='".$input['name']."' name='".$input['name']."' class='shipon_input'></input>
						</div>";
	}
	
	$accounts .= "		<div class='shipon_fieldblock' style='width: 100%'>
							<a class='dialog_action shipon_button' onclick='shipon_process_account(this)'> ".text('create')." </a>
							<span id='shipon_account_output'></span>
						</div>
					</div>";

	$close = 'jQuery("#shipon_account_delete_popup").dialog("close");';

	$accounts .= "	<div id='shipon_account_delete_popup' class='shipon_popup' title='".text('new_account')."'>
						<div class='shipon_fieldblock' style='width: 100%'>
							".text('account_delete_message')."
						</div>
						<div class='shipon_fieldblock' style='width: 100%'>
							<a class='shipon_button' onclick='shipon_delete_account(this)'> ".text('yes')." </a>
							<a class='shipon_button' onclick='".$close."'> ".text('no')." </a>
						</div>
					</div>";

	$accounts .= get_paged_footer("#address_book", $xml->entries_per_page, $pageCount, $xml->page_numbers);

	return $accounts;
}

function parse_field_xml($view_xml, $width, $user)
{
	$xml = new SimpleXMLElement($view_xml);

	$title_buttons = "";
	if($xml->title_buttons)
	{
		$buttons = $xml->title_buttons->children();
		foreach($buttons as $button)
		{
			$title_buttons .= $button->asXML();
		}
	}

	$field = get_field_header($width, $xml->title, $title_buttons);		
		$blocks = $xml->content->children();
		$field .= generate_html_from_blocks($user, $blocks);						
	$field .= get_field_footer();

	if($xml->popup)
	{
		$popup_blocks = $xml->popup->children();

		$field .= "<div class='shipon_popup' id='".$xml->popup['id']."' title='".text($xml->popup['title'])."'>";
			$field .= generate_html_from_blocks($user, $popup_blocks);
		$field .= "</div>";
	}

	return $field;
}

function parse_goods_table($user)
{
	$bill_xml = file_get_contents($user."/templates/goods_table.xml");
	$xml = new SimpleXMLElement($bill_xml);

	$headers = array();
	$inputs = array();
	$widths = array();

	$elements = $xml->children();

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
	$table .= "<tr>";
	$count = count($headers);
	for($i = 0; $i < $count; $i++)
	{
		$table .= "<th width='".$widths[$i][0]."'>".text($headers[$i])."</th>";
	}
	$table .= "<th></th>";
	$table .= "</tr>";
	$table .= "<tr>";
	foreach($inputs as $input)
	{
		$table .= "<td>".$input."</td>";
	}
	$table .= "<td></td>";
	$table .= "</tr>";
	$table .= "</table>";

	//$table .= "<a class='shipon_button' id='shipon_goods_add' onclick='shipon_goods_add()'> Add </a>";

	return $table;
}

function generate_html_from_blocks($user, $blocks)
{
	$field = "";
	foreach($blocks as $block)
	{
		$field .= "<div class='shipon_fieldblock' style='width:".$block['width'].";'> ";
		
		if($block->goods_table)
		{
			$field .= parse_goods_table($user);
		}
		else
		{
			if(count($block->label))
				$block->label = text($block->label).":";
			
			$elements = $block->children();
			foreach($elements as $element)
			{
				$field .= $element->asXML();
			}
		}

		$field .= " </div>";
	}

	return $field;
}

function get_field_header($width, $title, $buttons)
{
	$field =	"<div class='shipon_field' id='shipon_".$title."_content' style='width: ".$width."'>
					<div class='shipon_field_container'>
						<div class='shipon_title'>
							<div class='shipon_titletext'>".text($title)."</div>";
	if($buttons) $field .= "<div class='shipon_titlebuttons'>".$buttons."</div>";
	$field .= 		   "</div>
						<div class='shipon_field_content'>";

	return $field;
}

function get_field_footer()
{
	$field =			"</div>
					 </div>
				 </div>";

	return $field;
}

function get_pdf_footer($id)
{
	$field =			"</div>
						<div id='shipon_pages'>
							<a id='shipon_edit' class='shipon_button' href='#edit-".$id."'>".text('edit')."</a>
							<a id='shipon_send' class='shipon_button' href='#send-".$id."'>".text('send')."</a>
						</div>
					 </div>
				 </div>";

	return $field;
}

function get_paged_footer($href, $entriesperpage, $pageCount, $pagenumbers)
{
	$field = "			</div>
						<div id='shipon_pages'>
							<span id='shipon_prev_links'>".prevPageLink($href, $entriesperpage, $pageCount)."</span>
							<span id='shipon_page_links'>".pageLinks($href, $pagenumbers, $entriesperpage, $pageCount)."</span>
							<span id='shipon_next_links'>".nextPageLink($href, $entriesperpage, $pageCount)."</span>
						</div>
					</div>
				</div>";

	return $field;
}

function pageLinks($href, $pages, $entriesperpage, $pageCount)
{
	if($_REQUEST['data'] == '') $_REQUEST['data'] = 0;
	// figure out our page numbers we're going to display
	if($pageCount > $pages)
	{
		$capped;
		
		if($_REQUEST['data'] > (floor($pages / 2) - 1))
		{
			$start = $_REQUEST['data'] - floor($pages / 2);
		}
		else // if there aren't, start at 0
		{
			$start = 0;
			$capped = 0;
		}
		
		if($_REQUEST['data'] < $pageCount - floor($pages / 2))
		{
			$end = $_REQUEST['data'] + (floor($pages / 2) + 1);
		}
		else // if there aren't start at the end
		{
			$end = $pageCount;
			$capped = 1;
		}		
		
		if(($end - $start) < $pages)
		{			
			$diff = $pages - ($end - $start); // the amount of pages we need to add	
			
			if(!$capped) // if we're capped at the start, add to the end
				$end += $diff;			
			else // if we're capped at the end, add to the start			
				$start -= $diff;
		}
	}	
	else
	{
		$start = 0;
		$end = $pageCount;
	}
	
	// output the page numbers
	$pages = "";
	for($i = $start; $i < $end; $i++)
	{
		if($_REQUEST['data'] == $i)
			$pages .= "<a class='shipon_button_disabled shipon_page_button'>".($i + 1)."</a>";
		else
			$pages .= "<a class='shipon_button shipon_page_button' href='".$href."-".$i."'>".($i + 1)."</a>";
	}

	return $pages;
}

function nextPageLink($href, $entriesperpage, $pageCount)
{
	$buttons = "";
	if($_REQUEST['data'] < $pageCount - 1)
		$buttons .= "<a class='shipon_button shipon_page_nav' href='".$href."-".($_REQUEST['data'] + 1)."'> Next </a>";
	else
		$buttons .= "<a class='shipon_button_disabled shipon_page_nav'> Next </a>";

	if($_REQUEST['data'] != $pageCount - 1 && $pageCount > 0)
		$buttons .= "<a class='shipon_button shipon_page_nav' href='".$href."-".($pageCount - 1)."'> Last </a>";
	else
		$buttons .= "<a class='shipon_button_disabled shipon_page_nav'> Last </a>";

	return $buttons;
}

function prevPageLink($href, $entriesperpage, $pageCount)
{
	$buttons = "";
	if($_REQUEST['data'] > 0)
		$buttons .= "<a class='shipon_button shipon_page_nav' href='".$href."-".($_REQUEST['data'] - 1)."'> Previous </a>";
	else
		$buttons .= "<a class='shipon_button_disabled shipon_page_nav'> Previous </a>";

	if($_REQUEST['data'] != 0)
		$buttons .= "<a class='shipon_button shipon_page_nav' href='".$href."'> First </a>";
	else
		$buttons .= "<a class='shipon_button_disabled shipon_page_nav'> First </a>";

	return $buttons;
}

function text($tag)
{
	$text = query("SELECT ".$_REQUEST['lang']." FROM localization WHERE tag='".$tag."'");

	if(num_rows($text))
	{
		$text = fetch($text);
		return $text[$_REQUEST['lang']];
	}
	else
		return $tag;
}

?>