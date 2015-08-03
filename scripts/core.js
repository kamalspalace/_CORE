jQuery(window).bind('hashchange', function ()
{
    //show_loading();
    var hash = window.location.hash.split("#");
    var custom_data = handle_custom_view_data(hash[1]);
    change_view(hash[1], hash[2], custom_data);
    jQuery('.shipon_header_button_hover').removeClass('shipon_header_button_hover');
    jQuery('#' + hash[1]).addClass('shipon_header_button_hover');

});

$.extend($.ui.accordion.animations, {
  fastslide: function(options) {
    $.ui.accordion.animations.slide(options, { duration: 300 }); }
  });

$.fn.hasAttr = function(name) {  
   return this.attr(name) !== undefined;
};

jQuery('#bol').bind('click', function () 
{
  var post = {
		view: 'bol',
		view_action: 'init',
		data: ''		
	};

  load_content('view', post, init_view, unload_view);
});

jQuery('#shipon_pass').keydown(function (e){
        if(e.keyCode == 13){
        	shipon_login();
	}
});

function session_ttl()
{
    jQuery.ajax(
	{
		url: "shiponline/api?action=session_ttl", type: "POST",
		async: false,
		success: function (data)
		{
		    window.ttl = data;
		}
	});

	return window.ttl;
}

jQuery(window).trigger('hashchange');

init_login_popup();

jQuery.fn.rowCount = function() {
	return jQuery('tr',jQuery(this).find('tbody')).length;
}

function load_content(action, post, fnPtrAfter, fnPtrBefore)
{   
    //post += "&current_hash=" + window.location.hash;
    jQuery.ajax({
        url: "shiponline/api?action=" + action,
        type: 'POST',
        cache: false,
        dataType: 'json',
        data: post,
        success: function (data)
        {			

        if (data.alert)
            alert(data.alert);
				
	    if (data.auth == 0)
            {
                pre_popup_handler();
                shipon_open_popup('#shipon_login_popup');
                jQuery('#shipon_action').val(data.data.action);
                jQuery('#shipon_view').val(data.data.view);
                jQuery('#shipon_view_action').val(data.data.view_action);
                return 0;
            }

            if (typeof (fnPtrBefore) == "function")
            {
                if (data.data != null) fnPtrBefore(data.data);
                else fnPtrBefore();
            }
			
            for (var k in data.html) {
                if(jQuery('#' + k).length > 0)
                   jQuery('#' + k).html(data.html[k]);
            }

            for (var j in data.inputs)
			{
                	var type = jQuery('input[name="' + j + '"]').attr('type');
    				if(type == 'radio' && jQuery('input:radio[name="' + j + '"]').length > 0)
    					jQuery('input:radio[name="' + j + '"][value="' + data.inputs[j] + '"]').prop('checked', true);
    				else 
                        if(jQuery('input[name="' + j + '"]').length)
                   		   jQuery('#' + j).val(data.inputs[j]);
			}

            if (typeof (fnPtrAfter) == "function")
            {
                if (data.data != null) fnPtrAfter(data.data);
                else fnPtrAfter();
            }
			
			if(data.error.length > 0)
			{
				jQuery('#shipon_content').html(jQuery('#shipon_content').html() + error_popup(data.error));
				jQuery("#shipon_error_popup").dialog({
					width: 600, resizable: false
				});
				return 0;
			}
        },
        error: function (message)
        {
            hide_overlay();
            var error = message.responseText;
            jQuery('#shipon_content').html(jQuery('#shipon_content').html() + error_popup(error));
            jQuery("#shipon_error_popup").dialog({
                width: 600, resizable: false
            });
        }
    });
}

function add_change_listener(element, callback)
{
    jQuery(element).data('oldVal', jQuery(element).val());

    jQuery(element).bind("propertychange change keyup input paste", function (event)
    {
        if (jQuery(element).data('oldVal') != jQuery(element).val())
        {
            jQuery(element).data('oldVal', jQuery(element).val());

            if (typeof (callback) == 'function') callback();
        }
    });
}

function error_popup(message)
{
    return "<div id='shipon_error_popup' title='Error'><p>" + message +"</p></div>";
}

function init_login_popup()
{
    jQuery('#shipon_login_popup').dialog({ autoOpen: false, modal: true, closeOnEscape: false, minHeight: 80, resizable: false,
        open: function (event, ui) { jQuery(".ui-dialog-titlebar-close").hide();}
    });
}

function shipon_login()
{
    var post = {
        shipon_user: jQuery('#shipon_user').val(),
        shipon_pass: jQuery('#shipon_pass').val()
    };

    load_content('login', post, function (data)
    {
        // alert(data);
        jQuery(window).trigger('hashchange');
        init_login_popup();
    });
}

function shipon_popup_login()
{


    jQuery('#shipon_login_popup').dialog('close');
    var post = {
        shipon_user: jQuery('#shipon_user').val(),
        shipon_pass: jQuery('#shipon_pass').val(),
        backup_action: jQuery('#shipon_action').val(),
        view: jQuery('#shipon_view').val(),
        view_action: jQuery('#shipon_view_action').val(),
	shipon_relogin: 'true'
    };

	show_overlay('#shipon_wrapper');
	
	shipon_pass:jQuery('#shipon_pass').val('');
	
	var hash = window.location.hash.split("#");
	
	//alert(hash[1] + " = " + jQuery('#shipon_view').val() + " | " + hash[2] + " = " + jQuery('#shipon_view_action').val());

    if(hash[1] == jQuery('#shipon_view').val() && (hash[2] == jQuery('#shipon_view_action').val() || jQuery('#shipon_view_action').val() == 'init')) { 
        load_content('relogin', post, init_view, unload_view);
    }	else { 
        load_content('relogin', post);
    }	
}

function shipon_logout()
{
    jQuery('#shipon_login_popup').dialog('close');
    shipon_pass: jQuery('#shipon_pass').val('');
	
    jQuery("#shipon_login_popup").dialog("destroy").remove();

    load_content('logout', null, function()
	{
		window.location = homeUrl;
	});
}

//function change_hash()
//{
//    var hash = window.location.hash.split("#");
//    change_view(hash[1]);
//}

function shipon_back()
{
    var acc = jQuery("#shipon_accordion"),
    index = acc.accordion('option', 'active'),
    nxt = index - 1;

    if (nxt >= 0)
    {
		jQuery("#shipon_BOL").validate().resetForm();
        acc.accordion('option','active', nxt);
    }

    hide_overlay();
}

function shipon_continue()
{   

    var acc = jQuery("#shipon_accordion"),
    index = acc.accordion('option', 'active'),
    total = acc.children('div').length,
    nxt = index + 1;
    if (index === 0) {
		//Temporary code to test functinal services from timax. return false is to avoide complete flow.
		//check the out put from the log in console console.log() in firebug 
		// getShiponlineDetail();
		// getServiceShiponlineDetail()
		// return false;
		//===============================
		jQuery(".shiponConSave").click();
		jQuery(".shiponShipSave").click();
	}
    if (nxt >= total)
    {
		//Before post enable all the select drop down boxes
		jQuery("#shipon_BOL select").attr("disabled", false);
		check_payer_enabled();
        var data = jQuery('form').serialize();

        data += '&view=bol';
        data += '&view_action=process_bol';
		
        load_content('view', data, function (data)
        {
	    window.goods_set_divison = '';
            document.location.hash = "#history#view_pdf-" + data;
        });
    }
    else
        hide_overlay();

    var section_name = "handle_section_" + nxt;

    if (typeof window[section_name] === "function")
        if(window[section_name]() == true) {
	  acc.accordion('activate', nxt);
    	  jQuery(window).scrollTop(jQuery('#shipon_accordion').offset().top);
	}
    return false;
}

function shipon_update()
{
    var acc = jQuery("#shipon_accordion"),
    index = acc.accordion('option', 'active'),
    total = acc.children('div').length,
    nxt = index + 1;

    if (nxt >= total)
    {
		check_payer_enabled();
        	var data = jQuery('form').serialize();
		data += '&view=bol';
        	data += '&view_action=update_bol';
		data += '&shipon_bill_id=' + jQuery("#shipon_bill_id").val();

        load_content('view', data, function (data)
		{
			document.location.hash = "#history#view_pdf-" + data;
		});
    }
	else
        hide_overlay();
		
	var section_name = "handle_section_" + nxt;

    if (typeof window[section_name] === "function")
        window[section_name]();

    acc.accordion('activate', nxt);
}

function check_service_selected()
{
   if(!jQuery("input[name='shipon_service']:checked").val()) 
      return false;
   else
      return true;
}

function check_payer_enabled()
{
	jQuery('input[id*="shipon_bill"]').each(function()
	{
		if(jQuery(this).attr("readonly") == 'readonly')
			jQuery(this).removeAttr("readonly");
		else
			jQuery(this).attr("readonly", "readonly");
	});
}

function change_view(view, action, custom_data)
{
    show_overlay('#shipon_wrapper')

    if (typeof view === 'undefined'){view = 'bol';}
    if (typeof action === 'undefined'){action = 'init';}  

    var data = action.split('-');

    var post = {
        view: view,
        view_action: data[0],
        data: data[1]
    };
	
	if(typeof(custom_data) != 'undefined')	
		for(var i in custom_data) post[i] = custom_data[i];

    load_content('view', post, init_view, unload_view);
}

function handle_custom_view_data(view)
{
	var custom_data;
	switch(view)
	{
		case 'tracking':
			custom_data = {
				start_date: window.tracking_from,
				end_date: window.tracking_to,
				search_string: jQuery('#shipon_tracking_search').val(),
				sort_by: jQuery('#shipon_tracking_sort_by').val(),
				sort_order: jQuery('#shipon_tracking_sort_order').val()
			};
		break;
		case 'history':
			custom_data = {
				search_string: jQuery('#shipon_history_search').val(),
				sort_by: jQuery('#shipon_history_sort_by').val(),
				sort_order: jQuery('#shipon_history_sort_order').val()
			}
		break;
		case 'address_book':
			custom_data = {
				search_string: jQuery('#shipon_address_search').val()
			}
		break;
	}
	
	return custom_data;
}

function pre_popup_handler()
{
	if(jQuery('embed').length > 0)
	{
		var width = jQuery('embed').prop('width');
		var height = jQuery('embed').prop('width');
		var filler = "<div style='width:" + width + "px; height:" + height + "px; background: #505050;'></div>";
		jQuery('.shipon_field_content').html(filler);
	}
}

function unload_view()
{
    jQuery('.shipon_datepicker').datepicker("destroy").remove();
	jQuery('.shipon_autocomplete').autocomplete("destroy").remove();
    jQuery('.shipon_popup').each(function ()
    {
        var id = "#" + jQuery(this).attr('id');
        jQuery(id).dialog("destroy").remove();
    });
}

function init_view()
{
    jQuery('.shipon_radio').buttonset();
	jQuery('.shipon_tabs').tabs();
    jQuery('.shipon_datepicker').datepicker();
    if(!jQuery('.shipon_datepicker').val()) jQuery('.shipon_datepicker').datepicker('setDate', new Date());
    jQuery('.shipon_timepicker').timeEntry({spinnerImage: '', useMouseWheel: false, defaultTime: +0, show24Hours: true});
    jQuery("table:not('#shipon_tracking_details_info') tbody tr:nth-child(even)").addClass("shipon_table_odd");
    jQuery("table:not('#shipon_tracking_details_info') tbody tr:nth-child(odd)").addClass("shipon_table_even");
    setup_autocomplete();
	
	jQuery('#shipon_content').find('.shipon_slider').each(function ()
	{
		var slider_min = Number(jQuery(this).attr('min'));
		var slider_max = Number(jQuery(this).attr('max'));
		jQuery(this).slider({min:slider_min, max:slider_max, change: shipon_slider_change});
	});

    jQuery('#shipon_content').find('.shipon_popup').each(function ()
    {
        var id = "#" + jQuery(this).attr('id');
        jQuery(id).dialog({ autoOpen: false, modal: true, minHeight: 68, resizable: false});
        jQuery(id).parent().draggable('option', 'containment', '#shipon_content');
    });

    var hash = window.location.hash.split("#");
	if(!hash[1]) var init_name = "init_bol";
    else var init_name = "init_" + hash[1];
    if (typeof window[init_name] === "function")
        window[init_name](hash[2]);
    adjust_input_width();
    jQuery('#shipon_accordion').accordion({icons: false,  event: false});
    jQuery('#shipon_accordion').accordion("resize");

    onload_custom();
    hide_overlay();
}

function shipon_slider_change(event, ui)
{
	var value = jQuery("#" + event.target.id).slider("option", "value");
	var id = "#" + jQuery("#" + event.target.id).attr('name');
	jQuery(id).val(value);
}

function shipon_save_settings()
{
	var data = jQuery('form#shipon_account_settings').serialize();

	data += '&view=preferences';
        data += '&view_action=save_settings';
	
	load_content('view', data, function() { alert('Settings saved successfully.'); });
}

function shipon_settings_tab(tab)
{
	var tabClicked = jQuery(tab).html();
	jQuery('#shipon_settings_view_header span').each(function(index,value) {
	  if(jQuery(this).html() == tabClicked) {
	    jQuery(this).removeClass('shipon_setting_option').addClass('shipon_setting_option_selected');
	    jQuery("div[id='shipon_settings_" + jQuery(this).html().toLowerCase() + "']").show();
          }
	  else {
	    jQuery(this).removeClass('shipon_setting_option_selected').addClass('shipon_setting_option');
	    jQuery("div[id='shipon_settings_" + jQuery(this).html().toLowerCase() + "']").hide();
 	  }
	});
}

function history_search()
{
	window.location.hash = "#history";
	jQuery(window).trigger('hashchange');
}

function address_search()
{
	window.location.hash = "#address_book";
	jQuery(window).trigger('hashchange');
}

function shipon_open_popup(id)
{
    jQuery(id).dialog('open');
    jQuery(id).animate({marginLeft: '1px'}); // this fixes a weird IE height issue
    adjust_input_width_in(id);
}

function adjust_input_width()
{
    jQuery('.shipon_field').each(function ()
    {
        var id = jQuery(this).prop('id');
        adjust_input_width_in("#" + id);
    });
}

function adjust_input_width_in(id)
{
    jQuery(id).find('.shipon_fieldblock').each(function ()
    {
        if (jQuery(this).find('.shipon_radio').length == 0 && jQuery(this).find('#shipon_goods_table').length == 0)
        {
            var width = jQuery(this).width();
            var label_width = 0;
			
			var $label = jQuery(this).find('label');

            if ($label.length > 0)
                var label_width = $label.width();            

            var padding = jQuery(this).find('input').outerWidth() - jQuery(this).find('input').width() - 1;
			
			if (navigator.appName == "Microsoft Internet Explorer")
			{
				if(label_width > 0) width -= 5;
				jQuery(this).find('input, .shipon_slider').width(width - label_width - padding - 5);
				jQuery(this).find('textarea').width(width - label_width - padding - 13);
				jQuery(this).find('select').width(width - label_width - padding - 5);
			}
			else
			{
				//if(label_width > 0) width -= 5;
				width -= 12;
				jQuery(this).find('input, .shipon_slider').width(width - label_width);
				jQuery(this).find('textarea').width(width - label_width - 1);
				jQuery(this).find('select').width(width - label_width);
			}
        }
		else if(jQuery(this).find('#shipon_goods_table').length > 0)
		{
			jQuery(this).find('td').each(function ()
			{
				var $inputs = jQuery(this).children("div").children();
				var num = $inputs.length;
				var width = jQuery(this).width();
				
				var calc_width = 0;
				for(var i = 0; i < num; i++)
				{
					var local_width = width / num - 20;
					calc_width += local_width + 8;
					jQuery($inputs[i]).width(local_width);
				}
				
				jQuery(jQuery(this).children()[0]).width(calc_width);
			});
		}
        else if (jQuery(this).find('.shipon_radio').length > 0)
        {
            var count = jQuery(this).find('.shipon_radio').find('input').length;
            var width = jQuery(this).width() / count - 1;

            jQuery(this).find('.shipon_radio').find('label').each(function ()
            {
                jQuery(this).width(Math.floor(width));
            });
        }
		else if(jQuery(this).find('.shipon_slider').length > 0)
		{
			//alert('test');
			var label_width = 0;
			var $label = jQuery(this).find('label');
            if ($label.length > 0) var label_width = $label.width();
			
			jQuery(this).width(jQuery(this).width() - label_width);
		}
    });
}

function shipon_send(id)
{
    jQuery('#shipon_submit_order').css('opacity',0.7);
    jQuery('#shipon_edit_order').css('opacity',0.7);
    jQuery('#shipon_submit_loader').css('display','inline');
    var data = jQuery('form').serialize();
    data += '&view=bol&view_action=sub_shipment';
    data += '&shipment_id=' + id;   
    load_content('view', data);
}

function shipon_get_rates(skip_check)
{
	var hash = window.location.hash.split("#");
	if (hash[2]) {
		var method = hash[2].split('-');
		if (method[0] === 'edit') {
			jQuery('#shipon_id').val(method[1]);
		}	
	}
	
    if (typeof skip_check === 'undefined') skip_check = false;
    
	// has a shipment service been selected?
	if(!check_service_selected() && !skip_check)
	{
	   alert("You must select a shipment service before getting a rate estimate.");
	   return false;
	}

    var count = jQuery('#shipon_goods_table').rowCount();

    var hasReturn = false;
    for (i = 0; i < count; i++)
    {
        var name = "#shipon\\[goods\\]\\[" + i + "\\]\\[return_good\\]";
        val = jQuery(name).attr("checked");
        if(val == 'checked') hasReturn = true;
    }

    if(hasReturn == true && jQuery('#shipon_return_service').val() == '')
    {
       alert("You must select a return service before getting a rate estimate.");
       return false;
    }
        
	check_payer_enabled();
	var data = jQuery('form').serialize();
	data += '&view=bol';
	data += '&view_action=get_rates';

	var before = jQuery("#shipon_rates_table_container").html();
	jQuery("#shipon_rates_table_container").prepend('<div id="shipon_rates_table_progress"><img src="shiponline/theme/loadanim.gif" /></div>');

	load_content('view', data, function() 
	{
		    jQuery("tbody tr:nth-child(even)").addClass("shipon_table_odd");
    		jQuery("tbody tr:nth-child(odd)").addClass("shipon_table_even");
			check_payer_enabled();
	});
}

function shipon_goods_add()
{
	var max_rows = jQuery("#shipon_goods_max_rows").val();

	if(jQuery('#shipon_goods_table tbody tr').size() <= max_rows)
	{
		var row = "<tr>" + jQuery("#shipon_goods_table tbody tr:eq(0)").html() + "</tr>";
	   
		jQuery("#shipon_goods_table tbody").append(row);

		shipon_goods_fix_attrs();
		
		jQuery("#shipon_goods_table tr:last td:last").append("&nbsp;&nbsp;<a class='shipon_icon' onclick='shipon_goods_remove(this)'><img src='shiponline/theme/btnRemPkg.png'></a>");

		if (typeof window['custom_goods_add'] === "function")
        	window['custom_goods_add']();
		
	}
}

function shipon_goods_remove(reference)
{
    var max_rows = jQuery("#shipon_goods_max_rows").val();
    jQuery(reference).parent().parent().remove();
	jQuery("#shipon_goods_table tbody tr").removeAttr('class');
	shipon_goods_fix_attrs();

    
/*	
	if(jQuery('#shipon_goods_table tbody tr').size() <= max_rows)
	{
		jQuery('#shipon_goods_table tbody tr').each(function ()
		{	
			jQuery("#shipon_goods_table tbody tr:nth-child(odd)").addClass("shipon_table_even");
			jQuery("#shipon_goods_table tbody tr:nth-child(even)").addClass("shipon_table_odd");
		});	
	}
    
*/	
    calculate_total_pieces();
    calculate_total_weight();
}

function shipon_goods_fix_attrs()
{
    var i = 0;

    jQuery('#shipon_goods_table tbody tr').each(function ()
    {
		if (i%2 == 0) {
			jQuery(this).addClass('shipon_table_even');
		} else {
			jQuery(this).addClass('shipon_table_odd');
		}
        jQuery(this).find('td').each(function ()
        {
            jQuery(this).children('div').children().each(function ()
            {
                var attr = jQuery(this).attr('id');
                if (attr)
                {
                    var split_attr = attr.split("][");
		
					jQuery(this).attr('id', split_attr[0] + '][' + i + '][' + split_attr[2]);
					jQuery(this).attr('name', split_attr[0] + '][' + i + '][' + split_attr[2]);
					//check for pieces and appends emptyCheck dummy class with number for validation.
				    	// if (split_attr[2] === 'pieces]') {
					//	jQuery(this).attr('class','shipon_input shipon_table_input emptyCheck'+i);
					// }
					jQuery(this).trigger('change');
                }
            });
        });

        i++;
    });
}

function shipon_address_delete(reference)
{
    disable_all_addresses();
    var div = jQuery(reference).parent().parent().parent().find('.shipon_address_data');
	
    jQuery(reference).parent().html(jQuery('#shipon_yes_no').html());
    var w = div.width();
    var h = div.height();
    jQuery(".shipon_delete_message").width(w);
    jQuery(".shipon_delete_message").height(h);
    jQuery(".shipon_delete_message").position({
        my: "left top",
        at: "left top",
        of: div
    });
}

function shipon_address_delete_yes(reference)
{
    var id = jQuery(reference).parent().parent().parent().find('input:hidden').val();
    var post = {address_id: id};

    load_content('delete_address', post, function () { jQuery(window).trigger('hashchange'); });
}

function shipon_address_edit(reference)
{
	//var is_default = jQuery(reference).parent().parent().parent().parent().hasClass('shipon_default_address') == true ? true : false;
    var inputs = jQuery(reference).parent().parent().parent().find('input,textarea,select');

    jQuery(reference).parent().html(jQuery('#shipon_save_cancel').html());
    disable_all_addresses();

    inputs.each(function ()
    {
		//if(!is_default && jQuery(this).attr('name') != 'shipon_country')
		//{
			var search = 'input[name=' + jQuery(this).attr('name') + ']:hidden';
			jQuery("#shipon_address_backup").find(search).val(jQuery(this).val());
	
			jQuery(this).removeAttr('disabled');
			jQuery(this).removeClass('shipon_disabled');
		//}
    });
}

function shipon_address_edit_save(reference)
{
    var id = jQuery(reference).parent().parent().parent().find('input:hidden').val();
    var post = get_address_data(reference, 'edit');
    post += "&address_id=" + id;
    load_content('save_address', post);
    shipon_address_leave(reference);
}

function shipon_address_save(reference)
{   
    var post = get_address_data(reference, 'save');

    load_content('save_address', post, function (data)
    {
        jQuery(reference).parent().find('span').html(data);
    });
}

function shipon_address_new_save(reference)
{   
    var post = get_address_data(reference, 'new');
    load_content('save_address', post, function (data)
    {
        if (data === undefined || data == null)
        {
            jQuery('#shipon_new_address_popup').dialog('close');
            jQuery(window).trigger('hashchange');
        }
        else
		    jQuery(reference).parent().find('span').html(data);
    });
}

function get_address_data(reference, type)
{
    var post = "";
    
    jQuery(reference).parent().parent().parent().find('.shipon_fieldblock').each(function ()
    {
        var attr = jQuery(this).find('input').attr('name');
        var value = jQuery(this).find('input').val();

        if (!attr)
        {
            var attr = jQuery(this).find('select').attr('name');
            var value = jQuery(this).find('select').val();
        }

        if (attr)
        {
            var array = attr.split('_');
            var name = array[array.length - 1];

            post += name + "=" + encodeURIComponent(value) + "&";
        }
    });

    post += "save_type=" + type;
    return post;
}

function shipon_address_leave(reference)
{
    jQuery(reference).parent().parent().parent().find('input,select').each(function ()
    {
        jQuery(this).attr('disabled', true);
        jQuery(this).addClass('shipon_disabled');
    });
	
	jQuery(reference).parent().html(jQuery('#shipon_delete_edit').html());
}

function shipon_address_restore(reference)
{
    jQuery(reference).parent().parent().parent().find('input,select').each(function ()
    {
        var search = 'input[name=' + jQuery(this).attr('name') + ']:hidden, select[name=' + jQuery(this).attr('name') + ']';

        jQuery(this).attr('disabled', true);
        jQuery(this).addClass('shipon_disabled');
        jQuery(this).val(jQuery("#shipon_address_backup").find(search).val());
    });
}

function address_clear(reference)
{
    jQuery(reference).parent().parent().parent().find('.shipon_fieldblock').each(function ()
    {
        jQuery(this).find('input').val('');
        jQuery(this).find('select').val('');
        jQuery(this).find('input').keyup();

    });
}

function disable_all_addresses()
{
    jQuery("#shipon_address_book").find('tr').each(function ()
    {
        var delete_length = jQuery(this).find(".shipon_delete_message").length;
        if (jQuery(this).find(".shipon_fieldblock").length > 0)
            if (!jQuery(this).find(".shipon_fieldblock").first().children()[1].disabled || delete_length)
            {
                var buttons = jQuery(this).find(".shipon_addressbook_button").first();
                if (!delete_length) shipon_address_restore(buttons);
                buttons.parent().html(jQuery('#shipon_delete_edit').html());
                return false;
            }
    });
}

function switch_addresses(reference)
{
    jQuery(reference).parent().parent().find('.shipon_fieldblock').each(function ()
    {
        var attr = jQuery(this).children()[1].name;
		if(attr)
		{
			var value = jQuery("#" + attr).val();
	
			var array = attr.split('_');
			var name = array[array.length - 1];
			var attr2 = "#shipon_cons_" + name;
	
			jQuery("#" + attr).val(jQuery(attr2).val());
	
			jQuery(attr2).val(value);
	
			jQuery("#" + attr).keyup();
	
			jQuery(attr2).keyup();
		}
    });
}

function calculate_total_pieces()
{
    jQuery('#shipon_pieces').val(calculate_total_from_inputs('#shipon_goods_table', 'pieces', ''));
    jQuery('#shipon_pieces').change();
	
	calculate_total_weight();
}

function calculate_total_from_inputs(table_id, input_id, modifier_id)
{
    var total = 0;
    var count = jQuery(table_id).find('tbody').children().size();

    for (i = 0; i < count; i++)
    {   
		var checkReturn = "#shipon\\[goods\\]\\[" + i + "\\]\\[return_good\\]";
		if (!$(checkReturn).is(':checked')) {
			var name = "#shipon\\[goods\\]\\[" + i + "\\]\\[" + input_id + "\\]";
			var mod = "#shipon\\[goods\\]\\[" + i + "\\]\\[" + modifier_id + "\\]";
			var mod_val = jQuery(mod).val() ? jQuery(mod).val() : 1;
			total += Number(jQuery(name).val() * mod_val);
		}	
    }

    if (isNaN(total)) total = "Only numbers are allowed";

    return total;
}

function setup_autocomplete()
{
    jQuery(".shipon_autocomplete").autocomplete(
	{
	    source: function (request, response)
	    {
			var dest = this.element.attr('autocomplete_type');			
			var mod_id = this.element.attr('modifier');
			var mod = jQuery(this.element).parent().parent().find("#" + mod_id).val();

	        jQuery.ajax(
			{
			    url: "shiponline/api?action=autocomplete", type: "POST", dataType: "json",
			    data: { 
					term: request.term, 
					type: 'source',
					dest: dest,
					mod: mod
				},
			    success: function (data)
			    {
			        response(jQuery.map(data, function (item)
			        {
			            return {
			                id: item.id,
			                label: item.label,
			                value: item.value,
			                add_id: item.add_id
			            }
			        }));
			    }
			});
	    },
	    select: function (event, ui)
	    {
	        var input_id = jQuery(this).attr('id');
			var dest = jQuery(this).attr('autocomplete_type');

	        jQuery.ajax(
			{
			    url: "shiponline/api?action=autocomplete", type: "POST", dataType: "json",
			    data: {
			        add_id: ui.item.add_id,
					type: 'select',
					dest: dest,
					input_id: input_id
			    },
			    success: function (data)
			    {
			        jQuery.each(data.data, function (i, item)
			        {  
			            jQuery(i).val(item);
			            jQuery(i).keyup();
			        });
				}
			});
	    }
	});
}

function fill_goods_table(data)
{

    if(window.goods_set_division != 'LTL')
        fetch_goods_select(window.goods_set_division);

    if(window.goods_set_uom == 'metric') {
      jQuery('#shipon_unit_imperial').removeAttr('checked');
      jQuery('#shipon_unit_metric').attr('checked','checked');
      handleUnitChange();
    }

    var form_data = jQuery.parseJSON(data);

	for (var i in form_data)
	{
        if(i > 0) shipon_goods_add();

	    for(var j in form_data[i])
		{
			if(j == 'id' || j == 'shipment_id') continue;
            var name = "shipon\\[goods\\]\\[" + i + "\\]\\[" + j + "\\]";

			if(j == 'length' || j == 'width' || j == 'height' || j == 'weight')
			  form_data[i][j] = parseFloat(form_data[i][j]);

			if(form_data[i][j] < 0.05)
			  form_data[i][j] = '';

            jQuery("#" + name).val(form_data[i][j]);

            if(j == 'return_good' && form_data[i][j] == 1)
                if(jQuery("#" + name).length > 0)
                    jQuery("#" + name).attr('checked','checked').val('').removeAttr('value');
		}
	}  
	calculate_total_pieces();
	calculate_total_weight();
}

function dump(obj)
{
    var out = '';
    for (var i in obj)
    {
        out += "[" + i + "]: " + obj[i] + "\n";
    }

    alert(out);
}

	function show_overlay(div)
	{
       
	   if(jQuery("#shipon_content").html() == "")
	     var offset = "1 72";
	   else
	     var offset = "1 72";

	    jQuery("#shipon_overlay").show();
	    jQuery("#shipon_loading").show();

	    var w = jQuery("#shipon_content").width() + 1; // for some reason it's too short by 1 pixel
	    var h = jQuery("#shipon_content").height();

		jQuery("#shipon_overlay").width(w);
	    jQuery("#shipon_overlay").height(h);
		
		var info = jQuery('#shipon_content').position();
		
	    jQuery("#shipon_overlay").position({
		my: "left top",
		at: "left top",
		of: div,
		collision: 'none none'
	    });
		//jQuery('#shipon_overlay').css('top',285);
     
	    jQuery("#shipon_overlay").css('top',info.top + 'px');
	    jQuery("#shipon_loading").width(70);
	    jQuery("#shipon_loading").height(30);
	    jQuery("#shipon_loading").position({
		my: "center",
		at: "center",
		of: div
	    });
	}
	

	function hide_overlay()
	{   
	    jQuery("#shipon_overlay").hide();
	    jQuery("#shipon_loading").hide();
	}

function validate_form(onSuccess)
{
    show_overlay("#shipon_content");
    jQuery("#shipon_BOL").validate(
	{
	    errorClass: 'shipon_error',
	    validClass: 'shipon_valid',
	    errorPlacement: function (error, element)
	    {
	        // Set positioning based on the elements position in the form
	        var elem = jQuery(element),
				corners = ['top right', 'bottom right'];

	        // Check we have a valid error message
	        if (!error.is(':empty'))
	        {
	            // Apply the tooltip only if it isn't valid
	            elem.filter(':not(.shipon_valid)').qtip(
				{
				    overwrite: false,
				    content: error,
				    position: {
				        my: corners[1],
				        at: corners[0],
				        viewport: jQuery(window)
				    },
				    show: { event: 'click' },
				    hide: false,
				    style: {
				        classes: 'ui-tooltip-red' // Make it red... the classic error colour!
				    }
				})

	            // If we have a tooltip on this element already, just update its content
				.qtip('option', 'content.text', error).qtip('hide');
	        }

	        // If the error is empty, remove the qTip
	        else { elem.qtip('destroy'); }
	    },
	    success: jQuery.noop, // Odd workaround for errorPlacement not firing!
	    invalidHandler: function ()
	    {
	        hide_overlay();
	    },
	    submitHandler: function ()
	    {
	        onSuccess();
	    }
	});

    var index = jQuery("#shipon_accordion").accordion('option', 'active');
    var post = {
        section: index,
		goods_row_count: jQuery('#shipon_goods_table').rowCount(),
        view: 'bol',
        view_action: 'validate'
    };
    load_content('view', post, function ()
    {
        jQuery("#shipon_BOL").submit();
    });
}

function appointment_toggle(reference)
{
    var inputs = jQuery(reference).parent().parent().parent().parent().find('input[name*="time"]');
    if (jQuery(reference).attr('checked') == 'checked')
    {
        inputs.each(function ()
        {
            if (jQuery(this).attr('disabled') == 'disabled' && jQuery(this).attr('type') != 'checkbox') jQuery(this).removeAttr('disabled');
        });

        // if(jQuery('#shipon_del_note').length > 0)
        //    jQuery('#shipon_del_note').removeAttr('disabled');

        jQuery('#shipon_del_time_from').timeEntry('setTime', new Date());
    }
    else
    {
        inputs.each(function ()
        {
            if (!jQuery(this).prop('disabled') && jQuery(this).prop('type') != 'checkbox') jQuery(this).prop('disabled', true); 
        });

        // if(jQuery('#shipon_del_note').length > 0)
        //    jQuery('#shipon_del_note').attr('disabled', true);

         jQuery('#shipon_del_time_from').val("");
         jQuery('#shipon_del_time_to').val("");

    }
}

function adjust_payer_address(reference)
{
    var freight = jQuery('input[name=shipon_freight_charges]:checked', '#shipon_BOL').val();

    jQuery(reference).parent().parent().parent().find('.shipon_fieldblock').each(function ()
    {
        if (jQuery(this).children().length != 2) return true;

        var attr = jQuery(this).children()[1].name;
        var array = attr.split('_');
        var name = array[array.length - 1];

        switch (freight)
        {
			case '0':
                var attr2 = "#shipon_cons_" + name;
                jQuery("#" + attr).val(jQuery(attr2).val());
                jQuery("#" + attr).attr('disabled', true);
                jQuery("#clear_payer").attr('disabled', true);
                break;
				
            case '1':
                var attr2 = "#shipon_ship_" + name;
                jQuery("#" + attr).val(jQuery(attr2).val());
                jQuery("#" + attr).attr('disabled', true);
                jQuery("#clear_payer").attr('disabled', true);
                break;

            case '2':
                jQuery("#" + attr).val('');
                jQuery("#" + attr).removeAttr('disabled');
                jQuery("#clear_payer").removeAttr('disabled');
                break;
        }
    });
}

function new_account()
{
    shipon_open_popup('#shipon_account_popup');
}

function shipon_process_account(reference)
{
    var post = {
        view: 'manage_accounts',
        view_action: 'create_account',
        name : jQuery("#shipon_account_name").val(),
		phone : jQuery("#shipon_account_phone").val(),
		ext : jQuery("#shipon_account_ext").val(),
		email : jQuery("#shipon_account_email").val(),
        username: jQuery("#shipon_user_new").val(),
        password: jQuery("#shipon_pass_new").val()
    };

    load_content('view', post);
}

function delete_account(reference)
{
    jQuery('#shipon_delete_address_id').val(jQuery(reference).parent().parent().find('td').first().html());
    shipon_open_popup('#shipon_account_delete_popup');
}

function shipon_delete_account()
{
    var post = {
        view: 'manage_accounts',
        view_action: 'delete_account',
        user_id: jQuery("#shipon_delete_address_id").val()
    };

    load_content('view', post);
}

function edit_account(reference)
{
    shipon_open_popup('#shipon_edit_account_popup');

    var columns = jQuery(reference).parent().parent().find('td');

    jQuery('#shipon_user_edit').val(columns[1].innerHTML);
    jQuery('#shipon_account_name_edit').val(columns[2].innerHTML);
    jQuery('#shipon_account_phone_edit').val(columns[3].innerHTML);
    jQuery('#shipon_account_ext_edit').val(columns[4].innerHTML);
    jQuery('#shipon_account_email_edit').val(columns[5].innerHTML);

    jQuery('#shipon_user_edit').attr('disabled', 'disabled');
    jQuery('#shipon_pass_edit').focus();
}

function shipon_edit_account(reference)
{
    var post = {
        view: 'manage_accounts',	
        view_action: 'edit_account',
        edit_name: jQuery("#shipon_account_name_edit").val(),
        edit_username: jQuery("#shipon_user_edit").val(),
	edit_phone : jQuery("#shipon_account_phone_edit").val(),
	edit_ext : jQuery("#shipon_account_ext_edit").val(),
	edit_email : jQuery("#shipon_account_email_edit").val(),
        edit_pass: jQuery("#shipon_pass_edit").val()
    };

    load_content('view', post);
}

function print_pdf()
{
    var bol = jQuery("#shipon_pdf").html();

    jQuery.ajax(
		{
			url: "shiponline/api?action=get_printer_friendly_bill", type: "POST",
			data: {
			    bill: bol
			},
			success: function (data)
			{
			    jQuery("#shipon_content").html(data);
			}
	});
}

function update_tracking()
{
	window.location.hash = "#tracking";
	jQuery(window).trigger('hashchange');
}

function sort_by(reference)
{	
	var new_sort_by = reference;
	
	var sort_by = jQuery("input[id$='_sort_by']").val();
	var sort_order = jQuery("input[id$='_sort_order']").val();

	if(sort_by == new_sort_by)
	{
		if(sort_order == "DESC") sort_order = "ASC";
		else sort_order = "DESC";
	}
	else
	{
		sort_by = new_sort_by;
		sort_order = "DESC";
	}
	
	if(sort_by == "") sort_by = "0";
	if(sort_order == "") sort_order = "DESC";
	
	jQuery("input[id$='_sort_by']").val(sort_by);
	jQuery("input[id$='_sort_order']").val(sort_order);
	
	//alert(sort_by + " " + sort_order);
	
	jQuery(window).trigger('hashchange');
}

function get_tracking_query(query)
{
	jQuery('#shipon_tracking_search').attr('value',query);
    update_tracking();
}


function get_tracking_details(pbnum)
{
	var btc = jQuery('#code-' + pbnum).val();
		
	if(btc === undefined)
	  window.location.href = "#tracking#view_details-" + pbnum;
	else
	  window.location.href = "#tracking#view_details-" + pbnum + "_" + btc;
	
//	var post = {
//        view: 'tracking',
//        view_action: 'get_details',
//		pbnum: pbnum
//    };
//	
//	show_overlay();
//	load_content('view', post, adjust_details_pos);
}

function adjust_details_pos()
{
	var div = '#shipon_tracking';
	var w = jQuery(div).width();
    var h = jQuery(div).height();
    jQuery("#shipon_tracking_details").width(w);
    jQuery("#shipon_tracking_details").height(h);
    jQuery("#shipon_tracking_details").position({
        my: "top",
        at: "top",
		offset: "0 0", 
        of: div
    });
	
	hide_overlay();
}

function tracking_close()
{
	jQuery("#shipon_tracking_details_result").html('');
}

function fetch_goods_select(division)
{
    // This fixes a recall bug 
    if(typeof(division) == 'undefined')
        if(window.goods_set_division)
            division = window.goods_set_division;

    jQuery('.shipon_good_type').find('option').remove().end();
    jQuery.ajax(
		{
			url: "shiponline/api?action=view", 
			type: "POST",
            async: false,
			data: {
				division: division,
				view: 'bol',
				view_action: 'load_goods_select'
			},
			dataType: 'json',
			success: function (data)
			{
			   jQuery('.shipon_good_type').append(data.html['output']);
			}
	});
}

function on_area_change(reference)
{
	var value = jQuery(reference).val();
	
	if(value == 'Other')
		jQuery(reference).parent().parent().parent().find("input[id$='other']").prop("disabled", false);
	else
		jQuery(reference).parent().parent().parent().find("input[id$='other']").prop("disabled", true);
}

function formatTelNo(reference)
{
	var id = jQuery(reference).attr('id');
	var id_array = id.split('_');
	var country_id = "#shipon_" + id_array[1] + "_country";
	var country = (jQuery(country_id).val() === undefined) ? 'CA' : jQuery(country_id).val(); // default to North America if country not found

    if (country == 'CA' || country == 'US')
    {
        if (reference.value == "") return;
        var phone = new String(reference.value);
        phone = phone.substring(0, 14);     

        if (phone.match(".[0-9]{3}.[0-9]{3}-[0-9]{4}") == null)
        {           
            if (phone.match(".[0-9]{2}.[0-9]{3}-[0-9]{4}|" + ".[0-9].[0-9]{3}-[0-9]{4}|" +
				".[0-9]{3}.[0-9]{2}-[0-9]{4}|" + ".[0-9]{3}.[0-9]-[0-9]{4}") == null)
            {
                var phoneNumeric = phoneChar = "", i;
                for (i = 0; i < phone.length; i++)
                {
                    phoneChar = phone.substr(i, 1);
                    if (!isNaN(phoneChar) && (phoneChar != " ")) phoneNumeric = phoneNumeric + phoneChar;
                }

                phone = "";
                for (i = 0; i < phoneNumeric.length; i++)
                {
                    if (i == 0) phone = phone + "(";
                    if (i == 3) phone = phone + ") ";
                    if (i == 6) phone = phone + "-";
                    phone = phone + phoneNumeric.substr(i, 1)
                }
            }
        }
        else
        {
            phone = "(" + phone.substring(1, 4) + ") " + phone.substring(5, 8) + "-" + phone.substring(9, 13);
        }

        if (phone != reference.value) reference.value = phone;
    }
}
//------------------------------------------------------------------------
//Temporary function to test services. Lot of modification and work to be done yet
function getShiponlineDetail() {
		var data = jQuery('#shipon_BOL').serialize();
		data += '&view_action=validateAddress';
        load_content('shiponline', data, function (data)
        {
			console.log(data);
        });	
}


function getServiceShiponlineDetail() {
		var data = jQuery('#shipon_BOL').serialize();
		data += '&view_action=getServices';
        load_content('shiponline', data, function (data)
        {
			console.log(data);
        });	
}

function isValidPostalCode(postal,country){

	jQuery(postal).val(jQuery(postal).val().toUpperCase());

	if(jQuery(country).val() != 'CA')
	  return true;

	var entry  = jQuery(postal).val();
	var strlen = entry.length;

	entry = entry.toUpperCase(); // in case of lowercase

	var result = true;

	if(isNumeric(entry.charAt(0)))   { result = false; }
	if(isNumeric(entry.charAt(2)))   { result = false; }
	if(isNumeric(entry.charAt(4)))   { result = false; }

	if(! isNumeric(entry.charAt(1))) { result = false; }
	if(! isNumeric(entry.charAt(3))) { result = false; }
	if(! isNumeric(entry.charAt(5))) { result = false; }

	if(result == false) {
	  jQuery(postal).removeClass('shipon_valid');
	  jQuery(postal).addClass('shipon_error');
          return false;
	}
	else {
	  jQuery(postal).removeClass('shipon_error');
	  return true;
	}
}

function isNumeric(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}
