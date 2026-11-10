define(['jquery', 'Magento_Ui/js/modal/confirm', 'mage/translate'], function ($, confirm, $t) {
    'use strict';

    return function (config, element) {
        $(element).on('submit', 'form[data-garage-delete]', function (event) {
            var form = this;
            event.preventDefault();
            confirm({
                title: $t('Delete saved vehicle'),
                content: $t('Remove this vehicle from the customer’s garage? The default and selected vehicles will be reassigned if necessary.'),
                actions: {confirm: function () { form.submit(); }}
            });
        });
    };
});
