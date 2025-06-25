jQuery(function($) {
    $('#validateApiCredentials').on('click', function(e) {
        e.preventDefault();

        const button = $(this);
        const resultSpan = $('#validationResult');

        const apiKey = $('#payrexx_configs_api_key').val()
        const instance = $('#payrexx_configs_instance').val()
        const platform = $('#payrexx_configs_platform').val()

        // Show loading state
        button.prop('disabled', true);
        $('#validationSpinner').addClass('is-active').show();

        $.post(payrexxAjax.ajax_url, {
            action: 'payrexx_validate_api',
            nonce: payrexxAjax.nonce,
            api_key: apiKey,
            instance: instance,
            platform: platform
        }, function(response) {

            // Remove loading state
            $('#validationSpinner').removeClass('is-active').hide();
            button.prop('disabled', false);

            if (response.success) {
                resultSpan.text('✅ ' + response.data.message).css('color', 'green');
                return;
            }

            resultSpan.text('❌ ' + response.data.message).css('color', 'red');
        });
    });
});
