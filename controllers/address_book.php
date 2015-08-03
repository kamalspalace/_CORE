<?php
include("models/address.php");
class address_book extends View
{
	function __construct($user, $return)
	{
		$this->user = $user;
		$this->return = $return;
		parent::__construct();
	}

	function init($data)
	{   
		$book_xml = file_get_contents($this->user['folder']."/templates/address_book.xml");
		$xml = new SimpleXMLElement($book_xml);

		$default_address_result = query("SELECT default_address_id FROM `".$this->user['database']."`.account_groups WHERE id='".$this->user['group']."'");
		$default_address_data = fetch($default_address_result);
		$default_address = $default_address_data['default_address_id'];
		
		$search_string = "1";
		$s = "";
		$var_array = array();
		if(isset($_REQUEST['search_string']) && $_REQUEST['search_string'] != "Search")
		{
			$s = $_REQUEST['search_string'];
			$search_string = "(name LIKE ? OR contact LIKE ? OR phone LIKE ? OR ext LIKE ? OR email LIKE ? OR street1 LIKE ? OR street2 LIKE ? or city LIKE ? or province LIKE ? or country LIKE ? OR postal LIKE ?)";
			$var_array = array("%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%", "%".$s."%");
		}

		$addresses_result = query( "SELECT *
								FROM `".$this->user['database']."`.address_book
								WHERE group_id=".$this->user['group']." AND ".$search_string."
								ORDER BY name
								LIMIT ".$data * $xml->entries_per_page.",".$xml->entries_per_page, $var_array);
        
		$num_result = query("SELECT id FROM `".$this->user['database']."`.address_book WHERE group_id='".$this->user['group']."' AND ".$search_string, $var_array);
		$num = num_rows($num_result);
		$pageCount = ceil($num / $xml->entries_per_page);

		$result_count = num_rows($addresses_result);
		
		$titlebar_inputs =  "<a id='shipon_address_new' onclick='shipon_open_popup(shipon_new_address_popup);'>+ New Address</a><input type='text' class='shipon_input shipon_input_search' id='shipon_address_search' placeholder='Search' value='Search'>";
		$address_book = $this->get_field_header('100%', 'address_book', $titlebar_inputs);
		
		$address_book .= "<table id='shipon_address_book'>";
		
		for($i = 0; $i < $xml->entries_per_page; $i++)
		{
			if($i < $result_count)
			{
				$address = fetch($addresses_result);

				if($address['id'] == $default_address)
					$address_book .= "<tr class='shipon_default_address'><td><div class='shipon_address_data'>";
				else
					$address_book .= "<tr><td><div class='shipon_address_data'>";

				$blocks = $xml->content->children();
				foreach($blocks as $block)
				{
					$block_xml = new SimpleXMLElement($block->asXML());

					$address_book .= "<div class='shipon_fieldblock' style='width:".$block_xml['width'].";'> ";
					
					if(count($block_xml->label))
						$block_xml->label = $this->text((string)$block_xml->label).":";
					
					$elements = $block_xml->children();
					foreach($elements as $element)
					{
						if($element->getName() == 'LIST_OF_COUNTRIES')
						{
							$element->select = $this->parse_countries_list();
							$LOC = $element->children();
							$element = $LOC[0];
						}
						$data = (string)$element['value'];

						if($data) /* display french char correctly */
							$element['value'] = htmlspecialchars_decode(htmlentities($address[$data], ENT_NOQUOTES, 'UTF-8'), ENT_NOQUOTES);//$address[$data];

						$address_book .= html_entity_decode($element->asXML());
					}

					$address_book .= " </div>";
				}

				$address_book .= "<input type='hidden' name='shipon_id' value='".$address['id']."' />";

				$address_book .= "</div></td><td> <div class='shipon_address_controls'>				
										<a class='shipon_button shipon_addressbook_button shipon_address_delete' onclick='shipon_address_delete(this)'>".$this->text('delete')."</a>
										<a class='shipon_button shipon_addressbook_button shipon_address_edit' onclick='shipon_address_edit(this)'>".$this->text('edit')."</a>
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
			$i = count($elements) - 1;
			
			$elements[$i]['type'] = 'hidden';
			$elements[$i]['disabled'] = 'false';
			$elements[$i]['value'] = '';
			$hidden .= $elements[$i]->asXML();
		}
		$hidden .= "<input type='hidden' name='shipon_id' value='' />";
		$address_book .= "<div id='shipon_address_backup'>".$hidden."</div>";

		$buttons = "<div id='shipon_save_cancel'>
						<a class='shipon_button shipon_addressbook_button' onclick='shipon_address_edit_save(this)'>".text('save')."</a>
						<a class='shipon_button shipon_addressbook_button' onclick='disable_all_addresses()'>".text('cancel')."</a>
					</div>
					<div id='shipon_yes_no'>
						<a class='shipon_button shipon_addressbook_button' onclick='shipon_address_delete_yes(this)'>".text('yes')."</a>
						<a class='shipon_button shipon_addressbook_button' onclick='shipon_address_leave(this)'>".text('no')."</a>
						<div class='shipon_delete_message'>".text('delete_message')."</div>
					</div>
					<div id='shipon_delete_edit'>
						<a class='shipon_button shipon_addressbook_button shipon_address_delete' onclick='shipon_address_delete(this)'>".text('delete')."</a>
						<a class='shipon_button shipon_addressbook_button shipon_address_edit' onclick='shipon_address_edit(this)'>".text('edit')."</a>
					</div>";

		$address_book .= "<div id='shipon_address_book_buttons'>".$buttons."</div>";

		$address_book .= $this->get_paged_footer("#address_book", $xml->entries_per_page, $pageCount, 10);

		$new_address = "<div id='shipon_new_address_popup' class='shipon_popup' title='".text('new_address')."'>";
			
	// fix me
		$popup_xml = file_get_contents(get_include_path()."templates/fields/".$xml->new_address_temp.".xml");
		$xml = new SimpleXMLElement($popup_xml);
		$popup_blocks = $xml->content->children();		
		$new_address .= $this->generate_html_from_blocks($popup_blocks);	
		$new_address .= "</div>";

		$address_book .= $new_address;
		
		if(isset($_REQUEST['search_string']))
		{
			$this->return['inputs']['shipon_address_search'] = strlen($_REQUEST['search_string']) > 0 ? $_REQUEST['search_string'] : "Search";
		}

		$this->html['shipon_content'] = $address_book;
	}
        
}

?>
