jQuery(function ($) {
    // Handle "Connect" button click
    $('#payrexx-connect-button').on('click', function () {

        const button = $(this);
        const connectSpinner = $('#connectSpinner');
        const platformUrl = $('#payrexx_configs_platform').val();

        let popup = createPopup(platformUrl);

        // Check if the popup is closed manually (i.e., without receiving a message)
        let popupCheck;
        popupCheck = setInterval(() => {
            if (popup.closed) {
                popup = null;
                clearInterval(popupCheck);
                button.prop('disabled', false);
                connectSpinner.removeClass('is-active').hide();
            }
        }, 500)

        button.prop('disabled', true);
        connectSpinner.addClass('is-active').show();
    });

    // Receive postMessage from popup
    window.addEventListener('message', function (event) {
        if (!event.data || !event.data.instance) {
            return;
        }

        const apiKey = event.data.instance.apikey;
        const instance = event.data.instance.name;

        $('#payrexx_configs_instance').val(instance);
        $('#payrexx_configs_api_key').val(apiKey);

        $.post(payrexxConnectAjax.ajax_url, {
            action: 'payrexx_store_connect_settings', nonce: payrexxConnectAjax.nonce, api_key: apiKey, instance: instance,
        }, function (response) {

            const button = $(this);
            const resultSpan = $('#connectResult');
            const connectSpinner = $('#connectSpinner');

            button.prop('disabled', false);
            connectSpinner.removeClass('is-active').hide();

            if (response.success) {
                resultSpan.text('✅ ' + response.data.message).css('color', 'green');

                const notice = `
                    <div class="notice notice-info is-dismissible">
                        <p><strong>Payrexx values successfully imported.</strong> Don't forget to save the settings.</p>
                    </div>
                    `;

                $('#mainform').before(notice);
                return;
            }

            resultSpan.text('❌ ' + response.data.message).css('color', 'red');
        });
    });
});

const createPopup = (platformUrl) => {
    const popupWidth = 900;
    const popupHeight = 900;

    // Get the parent window's size and position
    const dualScreenLeft = window.screenLeft !== undefined ? window.screenLeft : window.screenX;
    const dualScreenTop = window.screenTop !== undefined ? window.screenTop : window.screenY;

    const width = window.innerWidth ? window.innerWidth : document.documentElement.clientWidth ? document.documentElement.clientWidth : screen.width;

    const height = window.innerHeight ? window.innerHeight : document.documentElement.clientHeight ? document.documentElement.clientHeight : screen.height;

    const left = dualScreenLeft + (width - popupWidth) / 2;
    const top = dualScreenTop + (height - popupHeight) / 2;

    const params = `width=${popupWidth},height=${popupHeight},top=${top},left=${left},resizable=no,scrollbars=yes`
    const url = `https://login.${platformUrl}?action=connect&plugin_id=1`;
    return window.open(url, 'Connect Payrexx', params);
}
