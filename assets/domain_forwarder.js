$(function() {
	toggle_protocol();
	
	$('#fd_type_id').on('change', function (){
		toggle_protocol();	
	});
	
	$('#btn_create_forward').on('click', function (e){
		e.preventDefault();		
		$('#add_domain_forward_msg').hide();
							
		if(!$('#fd_name').val()){
			$('#add_domain_forward_msg').html(form_msgs['empty_domain']);
			$('#add_domain_forward_msg').show();
			return false;
		}
					
		if($('#domain_id').val() < 1){
			$('#add_domain_forward_msg').html(form_msgs['empty_domain_to']);
			$('#add_domain_forward_msg').show();
			return false;
		}
		
		Sentora.loader.showLoader();
		
		$('#frm_forward_domain').submit();
		return true;
	});
	
	
	$('#btn_update_forward').on('click', function (e){
		e.preventDefault();		
		$('#edit_domain_forward_msg').hide();
							
		if(!$('#fd_name').val()){
			$('#edit_domain_forward_msg').html(form_msgs['empty_domain']);
			$('#edit_domain_forward_msg').show();
			return false;
		}
					
		if($('#domain_id').val() < 1){
			$('#edit_domain_forward_msg').html(form_msgs['empty_domain_to']);
			$('#edit_domain_forward_msg').show();
			return false;
		}
		
		Sentora.loader.showLoader();
		
		$('#frm_edit_domain_forward').submit();
		return true;
	});
	
	$('.btn-delete-forward').on('click', function (){
		var _id = $(this).attr('data-id');
		//Confirm Domain Transfer
		Sentora.dialog.confirm({
			title: form_msgs['domain_forward_delete_dialog_title'],
			message: form_msgs['domain_forward_delete_confirm_msg'],
			width: 300,
			cancelButton: {
			    text: form_msgs['domain_forward_cancel_btn_label'],
			    show: true,
			    class: 'btn-default'
			},
			okButton: {
			    text: form_msgs['domain_forward_ok_btn_label'],
			    show: true,
			    class: 'btn-primary'
			},
			cancelCallback: function() { return false; },
			okCallback: function() { 
				$('#frm_forwarded_domains').append('<input type="hidden" name="fd_id" value="'+_id+'">');
				Sentora.loader.showLoader();
				_change_action('frm_forwarded_domains','DeleteForward');
					//$('#frm_forwarded_domains').submit(); 
			}
		});
	});
});


function _change_action(fid, _action, item_id){
        var _attr = './?module=domain_forwarder';
        if(!_action){
            _action = '';
        }
        
        if(!item_id){
            item_id = 0;
        }

        if(item_id){
            $('#item_id').val(item_id);
        }
        if(_action){
            _attr += '&action='+_action;
        }
        
        $('#'+fid).attr('action', _attr);
        $('#'+fid).submit();
    }
    
    function toggle_protocol(){
        var _fd_type = $('#fd_type_id').val();
        
        $('#fd_protocol').hide();
        
        if(_fd_type == 1){
           $('#fd_protocol').show();
        }
    }
