(function($) {
	
	/**
	 *
	 * @author: Nick Ryall, nick@randb.com.au
	 * @source: http://github.com/nick-ryall/eventarc
	 */
	$(document).ready(function() {
	
		var root = Symphony.Context.get('root');
		
		if(!$('input[name=\'fields[g-id]\']').length) return;	
		
		//Make call to API to populate the Group Ids
		var data = {
            "apikey": u_apikey,
            "uname": u_name 
		}		
		$.ajax({
		  url: root + '/extensions/eventarc/lib/groups.php',
		  type: 'GET',
		  dataType: 'json',
		  data: data,
		  success: function(data) {
		  	var $g = $('input[name=\'fields[g-id]\']');
		  	var g_id = $g.val();
			$sel = $('<select id="group_id"><select>');
	  	    for (var i=0; i<data.length; i++) {
	  	      if(data[i].g_id == g_id) {
	  	      	$sel.append('<option selected value="' + data[i].g_id + '">' + data[i].g_name + '</option>');
	  	      } else {
	  	      	$sel.append('<option value="' + data[i].g_id + '">' + data[i].g_name + '</option>');
	  	      }
	  	    } 
	  	    if($g.val() == "") {
	  	    	$g.val($sel.val());
	  	    }
	  	    $sel.insertAfter($g).live('change', function() {
	  	    	$g.val($(this).val());
	  	    });
	  	    $g.hide();
		  }
		});
	
	});
	
})(jQuery.noConflict());