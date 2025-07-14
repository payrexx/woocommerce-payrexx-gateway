jQuery(function ($) {
    // Handle "Test Connection" or "Connect" button click
    $('#payrexx-connect-button').on('click', function () {

        const button = $(this);
        const connectSpinner = $('#connectSpinner');
        const platformUrl = $('#payrexx_configs_platform').val();

        createPopup(platformUrl);

        button.prop('disabled', true);
        connectSpinner.addClass('is-active').show();

        const notice = `
        <div class="notice notice-info is-dismissible">
            <p><strong>Payrexx values successfully imported.</strong> Don't forget to save the settings.</p>
        </div>
        `;

        $('#mainform').before(notice);
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

        $.post(payrexxAjax.ajax_url, {
            action: 'payrexx_store_connect_settings',
            nonce: payrexxAjax.nonce,
            api_key: apiKey,
            instance: instance,
        }, function (response) {

            const button = $(this);
            const resultSpan = $('#connectResult');
            const connectSpinner = $('#connectSpinner');

            button.prop('disabled', false);
            connectSpinner.removeClass('is-active').hide();

            if (response.success) {
                resultSpan.text('✅ ' + response.data.message).css('color', 'green');
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

    const width = window.innerWidth
        ? window.innerWidth
        : document.documentElement.clientWidth
            ? document.documentElement.clientWidth
            : screen.width;

    const height = window.innerHeight
        ? window.innerHeight
        : document.documentElement.clientHeight
            ? document.documentElement.clientHeight
            : screen.height;

    const left = dualScreenLeft + (width - popupWidth) / 2;
    const top = dualScreenTop + (height - popupHeight) / 2;

    const params = `width=${popupWidth},height=${popupHeight},top=${top},left=${left},resizable=no,scrollbars=yes`
    const url = `https://login.${platformUrl}?action=connect&plugin_id=1`;
    return window.open(
        url,
        'Connect Payrexx',
        params
    );
}
