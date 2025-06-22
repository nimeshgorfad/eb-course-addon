jQuery(document).ready(function($) {
    $('form').on('submit', function(e) {
        e.preventDefault();

        let userId = $('#new-enrollment-student').val();
        let courseIds = $('#new-enrollment-courses').val();
        var formData = $(this).serialize(); 
            /*  data: {
                action: 'enroll_user_to_courses',
                'eb-manage-user-enrol': enrollAjax.nonce,
                'new-enrollment-student': userId,
                'new-enrollment-courses': courseIds
            }, 
            */
        $.ajax({
            url: enrollAjax.ajax_url,
            method: 'POST',
            data: formData, 
            success: function(response) {
                if (response.success) {
                    alert(response.data);
                    window.location.reload();

                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('AJAX request failed.');
            }
        });
    });

     $('.nkg-unenrol').click(function (e) {
        var row = jQuery(this);
       
        var userId = jQuery(this).data('user-id');
        var recordId = jQuery(this).data('record-id');
        var courseId = jQuery(this).data('course-id');
        
       
        $.ajax({
            method: "post",
            url: enrollAjax.ajax_url,
            dataType: "json",
            data: {
                'action': 'wdm_eb_user_manage_unenroll_unenroll_user',
                'user_id': userId,
                'course_id': courseId,
                'admin_nonce': enrollAjax.admin_nonce,
            },
            success: function (response) {
                 
                var message = "";
                if (response['success'] == true) {
                    var msg = response['data'];

                     alert(msg);
                     jQuery(row).remove();
                     window.location.reload();
 

                } else {
                    var msg = response['data'];
                       alert(msg);
                     
                }
                 
            },
            error: function (error) {
                
            }
        });
    });
    
});
