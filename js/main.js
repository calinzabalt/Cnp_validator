jQuery(function(){
    jQuery('#cnp-form').submit(function(event) {
        event.preventDefault();
        const cnpValue = jQuery('#cnp-input').val();

        jQuery.ajax({
            url: ajaxurl,
            method: 'POST',
            data: { 
              action: 'isCnpValid', 
              cnp: cnpValue 
            },
            dataType: 'json',
            success: function(response) {
              console.log(response);
            },
            error: function() {
              
            }
        });
    });
});