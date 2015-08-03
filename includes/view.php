<?php

class View
{
	var $user;
	var $return;
	var $session;
	var $html;
	var $lang;

	function __construct()
	{
        $this->initLang();
		$this->dbc 	= DB::getLink();
		$this->settings = Settings::get($this->user['database']);
		$this->session  = new Session($this->user);
		$this->session->initByID($_REQUEST['sid']);
		$this->smarty   = new SmartyTpl($this->user['folder']);
		$this->smarty->error_reporting = E_ALL & ~E_NOTICE;
		$this->smarty->assign('lang',$this->lang);
		$this->smarty->assign('user',$this->user);
		$this->smarty->assign('session',$this->session);
		$this->smarty->assign('settings',$this->settings->toArray());
		$this->smarty->muteExpectedErrors();
	}

	function get_return()
	{
		$this->return['html']  = $this->html;
		return $this->return;
	}

	function parse_field_xml($view_xml, $width, $help = false)
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

		$field = $this->get_field_header($width, $xml->title, $title_buttons, $help);		
		$blocks = $xml->content->children();
		$field .= $this->generate_html_from_blocks($blocks);
		$field .= $this->get_field_footer();

		if($xml->popup)
		{
			$popup_blocks = $xml->popup->children();

			$field .= "<div class='shipon_popup' id='".$xml->popup['id']."' title='".$this->text($xml->popup['title'])."'>";
			$field .= $this->generate_html_from_blocks($popup_blocks);
			$field .= "</div>";
		}

		return $field;
	}

	function generate_html_from_blocks($blocks)
	{
		$field = "";
		foreach($blocks as $block)
		{
			$field .= "<div class='shipon_fieldblock' style='width:".$block['width'].";'> ";
			
			if($block->goods_table)
			{
				$field .= $this->parse_goods_table();
			}
			else if($block->LIST_OF_COUNTRIES)
			{
				
				if(count($block->label))
				{
					$block->label = $this->text((string)$block->label).":";
					$field .= $block->label->asXML();
				}
				$block->LIST_OF_COUNTRIES->select = $this->parse_countries_list();
				$field .= html_entity_decode($block->LIST_OF_COUNTRIES->select->asXML());
			}
			else
			{
				if(count($block->label))
					$block->label = $this->text((string)$block->label).":";
				
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
	
	function parse_countries_list()
	{
		$countries_xml = file_get_contents($this->user['folder']."/templates/countries.xml");
		$xml = new SimpleXMLElement($countries_xml);
		
		$count = $xml->count();
		
		$return = "";
		for($i = 0; $i < $count; $i++)
		{
			$return .= $xml->option[$i]->asXML();
		}

		return $return;
	}

	function get_field_header($width, $title, $buttons, $help = false)
	{

		if($title == 'filler')
		{
			$field =    "<div class='shipon_filler' style='width: ".$width."'>
							<div class='shipon_filler_container'>
							<div class='shipon_filler_title'>";
		}
		else
		{
			$help_icon = $help == true ? "<span id='shipon_".$title."_tip' class='shipon_icon shipon_help_icon'>?</span>" : "";
			$field =	"<div class='shipon_field' id='shipon_".$title."_content' style='width: ".$width."'>
							<div class='shipon_field_container'>
								<div class='shipon_title'>
									<div class='shipon_titletext'>".$this->text($title)."</div>".$help_icon;
		}

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
		$field =			"
						<div id='shipon_pages'>&nbsp;</div>
					 </div>
				 </div>";

		return $field;
	}

	function page($data)
	{
		$this->init($data);
	}

	function get_paged_footer($href, $entriesperpage, $pageCount, $pagenumbers)
	{
		$field = "			</div>
						<div id='shipon_pages'>
							<span id='shipon_prev_links'>".$this->prevPageLink($href, $entriesperpage, $pageCount)."</span>
							<span id='shipon_page_links'>".$this->pageLinks($href, $pagenumbers, $entriesperpage, $pageCount)."</span>
							<span id='shipon_next_links'>".$this->nextPageLink($href, $entriesperpage, $pageCount)."</span>
						</div>
					</div>
				</div>";

		return $field;
	}

	function pageLinks($href, $pages, $entriesperpage, $pageCount)
	{
		$page = isset($_REQUEST['data']) ? $_REQUEST['data'] : 0;
		// figure out our page numbers we're going to display
		if($pageCount > $pages)
		{
			$capped;
			
			if($page > (floor($pages / 2) - 1))
			{
				$start = $page - floor($pages / 2);
			}
			else // if there aren't, start at 0
			{
				$start = 0;
				$capped = 0;
			}
			
			if($page < $pageCount - floor($pages / 2))
			{
				$end = $page + (floor($pages / 2) + 1);
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
			if($page == $i)
				$pages .= "<a class='shipon_button_disabled shipon_page_button'>".($i + 1)."</a>";
			else
				$pages .= "<a class='shipon_button shipon_page_button' href='".$href."#page-".$i."'>".($i + 1)."</a>";
		}

		return $pages;
	}

	function nextPageLink($href, $entriesperpage, $pageCount)
	{
		$page = isset($_REQUEST['data']) ? $_REQUEST['data'] : 0;
		$buttons = "";
		if($page < $pageCount - 1)
			$buttons .= "<a class='shipon_button shipon_page_nav' href='".$href."#page-".($page + 1)."'> Next </a>";
		else
			$buttons .= "<a class='shipon_button_disabled shipon_page_nav'> Next </a>";

		if($page != $pageCount - 1 && $pageCount > 0)
			$buttons .= "<a class='shipon_button shipon_page_nav' href='".$href."#page-".($pageCount - 1)."'> Last </a>";
		else
			$buttons .= "<a class='shipon_button_disabled shipon_page_nav'> Last </a>";

		return $buttons;
	}

	function prevPageLink($href, $entriesperpage, $pageCount)
	{
		$page = isset($_REQUEST['data']) ? $_REQUEST['data'] : 0;
		$buttons = "";

                if($page != 0)
                        $buttons .= "<a class='shipon_button shipon_page_nav' href='".$href."'> First </a>";
                else
                        $buttons .= "<a class='shipon_button_disabled shipon_page_nav'> First </a>";


		if($page > 0)
			$buttons .= "<a class='shipon_button shipon_page_nav' href='".$href."#page-".($page - 1)."'> Prev </a>";
		else
			$buttons .= "<a class='shipon_button_disabled shipon_page_nav'> Prev </a>";

		return $buttons;
	}

/* --- original function
        function load_tooltips($section)
        {
		$tooltips = query("SELECT * FROM `".$this->user['database']."`.tooltips WHERE controller = '".$section."'");
		$tooltips = fetchAll($tooltips);

                if(count($tooltips))
                {
		  $script = "<script type='text/javascript'>";
 		  foreach($tooltips as $tip)
                  {
                    $script .= "jQuery('".$tip['selector']."').qtip(
                                      {
                                      		content:
                                                {
                                                	text: '".$tip['text']."',
                                                        title:
                                                        {
                                                        	text: '".$tip['title']."',
                                                                button: ".$tip['button']."
                                                         }
                                                },
                                                position:
                                                {
                                                         my: '".$tip['my']."',
                                                         at: '".$tip['at']."'
                                                },
                                                show:
                                                {
                                                        target: jQuery('".$tip['selector']."')
                                                },
                                                style: {classes: 'ui-tooltip-green'}
                                    });";
                  }
                  $script .= "</script>";
                }
                return $script;
        }
*/
	function loadSettings($controller)
	{
                $settings = query("SELECT * FROM `".$this->user['database']."`.settings WHERE controller = '".$controller."'");
                $settings = fetchAll($settings);
		
		foreach($settings as $setting)
		  $return[$setting['property']] = $setting['value'];

		return $return;
	}

	function text($tag)
	{
	  if(array_key_exists((string)$tag,$this->lang))
		return $this->lang[(string)$tag];
	  else
		return ucwords($tag);
	}

	function initLang()
	{
	  $lang_default = query("SELECT * FROM localization");
	  $lang_default = fetchAll($lang_default);
	
	  foreach($lang_default as $row)
	    $lang_d[$row['tag']] = $row['EN'];

	  $lang_custom  = query("SELECT * FROM `".$this->user['database']."`.localization");
	  $lang_custom  = fetchAll($lang_custom);
	
	  foreach($lang_custom as $row)
	    $lang_c[$row['tag']] = $row['EN'];	  

	  $this->lang = array_merge($lang_d,$lang_c);
	}

	

	function load() {}
        

	function load_tooltips($controller, $section=NULL)
	{
			$tooltips = "";
			$con = $controller;
			$sec = $section;
			if ($con=='shipment') {
			   if (isset($section)) {
				  $secNumber = $section + 1;    
				  $sec = 'section' . $secNumber;  
			   }
			}

			if (isset($section)) {
				$stm = "SELECT * FROM `".$this->user['database']."`.tooltips WHERE controller = ? AND section = ?";
			} else {
				$stm = "SELECT * FROM `".$this->user['database']."`.tooltips WHERE controller = ?";
			}
			$sql = $this->dbc->prepare($stm);
			$sql->bindValue(1,$con,PDO::PARAM_STR);
			if (isset($sec)) {
				$sql->bindValue(2,$sec,PDO::PARAM_STR);
			}    
			$sql->execute();
			$col = $sql->fetchAll(PDO::FETCH_ASSOC);

			if(count($col))
			{                
			   $tooltips .= "<script type='text/javascript'>";

			   foreach ($col as $tip) {
					$tooltips .= "jQuery('".$tip['selector']."').qtip(
					{
							content: 
							{
									text: '".$tip['text']."', 
									title: 
									{
											text: '<span style=\"float: left;\">".$tip['title']."</span>', 
											button: ".$tip['button']."
									}
							 }, 
							 position: 
							 {
									 my: '".$tip['my']."',
									 at: '".$tip['at']."'
							 },
							 show:
							 {
										  target: jQuery('".$tip['selector']."') 
							 },
							 style: {classes: 'ui-tooltip-green'}
				   });";

			   }
			   $tooltips .= "</script>";
			}
			return $tooltips;
	}

}

?>
