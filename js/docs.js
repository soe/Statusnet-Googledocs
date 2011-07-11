$(window).load(function() {

    // move the google doc button just below the attachment button
    // a workaround for now
    $('.google_docs_attach').each(function() {
        $(this).appendTo($(this).prev().children('#input_form_status').find('.form_notice'));
        $(this).show('fast'); 
    });
    
    // move the google docs list div just before the end of notice content
    // a workaround as currently it is not possible
    $('.google_docs_list').each(function() {
        if($(this).find('li').length) {
            $(this).insertBefore($(this).siblings('.notice-options').prev());
            $(this).show('fast');
        } else {
            $(this).remove();
        }         
    });
 
    // docs browser as a jQuery UI dialog
    $('#google_docs_browser').dialog({
        title: 'Select Google Docs',
        autoOpen: false,
        height: 410,
        width: 600,
        modal: false,
        position: ['middle', 'center'],
        resizable: false,
        buttons: {
            "Cancel": function() {                
                $(this).dialog('close');
            },
            "Attach File(s)": function() {
                // append the selected file to Notice Form
                // then create hidden field inside the form, then clear the queue list
                var caller_form_id = $('#google_docs_browser #caller_form_id').val();
                $('#google_docs_browser #browser_queue li').each(function() {
                    var wrapper = $('<div class="googledocs_status_wrapper notice-status"><button class="close remove" style="float:right">&#215;</button><div class="info"></div></div>');
                    wrapper.find('.info').append($(this).find('a').html());
                    wrapper.find('.info').append('<input type="hidden" name="googledocs[id][]" value="'+$(this).find('input').val()+'" />');
                    wrapper.find('.info').append('<input type="hidden" name="googledocs[title][]" value="'+$(this).find('a').html()+'" />');
                    wrapper.find('.info').append('<input type="hidden" name="googledocs[filetype][]" value="'+$(this).find('.filetype').attr('class').replace('filetype ', '')+'" />');
                    $('#'+caller_form_id).append(wrapper);          
                });   
             
                $(this).dialog('close');
                
            }
        },
        close: function() {
            // clear file list and queue
            $('#google_docs_browser #browser_queue ul').html('');
            $('#google_docs_browser #browser_files :checked').each(function() {
                $(this).removeAttr('checked').parent().removeClass('selected');            
            });
            
            return false;
        }
    });

    // authorization popup as a jQuery UI dialog
    $('#google_docs_authorizer').dialog({
        title: 'Authenticate with your Google Accounts',
        autoOpen: false,
        height: 120,
        width: 320,
        modal: false,
        position: ['middle', 'center'],
        resizable: false,
        close: function() {
            return false;
        }
    });
    
    $('button.close.remove').live('click', function() {
        $(this).parent().remove();
        return false;
    });
    
    // open browser dialog
    $('.google_docs_attach').click(function() {
        
        // if not authenticated - open authorizer dialog
        if($('#google_docs_authorizer').hasClass('authorize')) {
            $('#google_docs_authorizer').show();
            $('#google_docs_authorizer').dialog('open');
            
            return false;
        }
        
        // @fixme workaround not to hide the notice form by adding a space
        if($(this).parents('form').find('textarea').val() == '')
            $(this).parents('form').find('textarea').val(' ');
            
        $('#google_docs_browser').show();
        $('#google_docs_browser').dialog('open');
        
        $('#google_docs_browser #caller_form_id').val($(this).parents('.form_notice').attr('id'));
        
        // load file list via ajax if it is empty
        if($('#google_docs_browser #browser_files').hasClass('unloaded')) {
            $.get($('#common_local_url').val() + '/googledocs/list', function(data) {
                $('#google_docs_browser #browser_files').removeClass('unloaded');
                $('#google_docs_browser #browser_files .loading').hide();
                $('#google_docs_browser #browser_files ul').append(data);
            });
        }
        
    });
    
    // @fixme - todo
    // clear the previous google docs appended list inside the form upon status update success
    // how to extend FormNoticeXHR -> ajax success?
    
    // load_more clicked, load more!!!
    $('#google_docs_browser #load_more.enabled').live('click', function() {
        // to ensure that clicking is not possible during ajax load
        if($(this).hasClass('enabled')) {
            
            // check if all documents have been loaded already
            if($('#google_docs_browser #browser_files .stop_load_more').html())
                return false;
                
            $(this).removeClass('enabled').html('Loading...').next().show();
            var startIndex = parseInt($('#start_index').val()) + 1;
            var query = $('#google_docs_browser #search_field').val();
            
            // load ajax
            $.get($('#common_local_url').val() + '/googledocs/list?q='+query+'&p='+startIndex, function(data) {
                $('#google_docs_browser #browser_files .wrapper').append(data);
            
                $('#start_indext').val(startIndex);
                $('#google_docs_browser #load_more').addClass('enabled').html('Load more...').next().hide();
            });
        }
    });
    
    // upon checkbox checked, add to queue list, hightlight
    // upon checkbox unchecked, remove from queue list, unhighlight
    $('#browser_files :checkbox').live('click', function() {       
        if($(this).is(':checked')) {
            $(this).parent().addClass('selected');
            
            // clone to the selected file
            $(this).parent().clone().appendTo('#browser_queue ul');
        } else {
            $(this).parent().removeClass('selected');
            
            // remove the reference from the selected file
            $('#browser_queue .'+ $(this).attr('class')).parent().remove();  
        }
    });
    
    // upon checkbox unchecked in queue list, remove, then unhighlight in file list
    $('#browser_queue :checkbox').live('click', function() { 
        $(this).parent().remove();
        $('#browser_files .'+ $(this).attr('class')).removeAttr('checked').parent().removeClass('selected');
    });
    
    // search clicked - reset start_indext, then make ajax call
    $('#google_docs_browser #search_button').click(function() {
        if($('#google_docs_browser #search_field').val()) {
            
            $('#google_docs_browser #browser_files ul').html('');
            $('#google_docs_browser #browser_files .loading').show();
                      
            $.get($('#common_local_url').val() + '/googledocs/list?q='+escape($('#google_docs_browser #search_field').val()), function(data) {
                $('#google_docs_browser #browser_files .loading').hide();
                $('#google_docs_browser #browser_files ul').append(data);
                
                // reset start_indext
                $('#start_indext').val(1);
                
            });
        }
        
    });
    
    // @fixme - optional - a function to check against files in the queue with newly appended files
});
