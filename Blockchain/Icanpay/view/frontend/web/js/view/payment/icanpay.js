define([
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (Component, rendererList) {
        'use strict';

        rendererList.push(
            {
                type: 'blockchain_icanpay',
                component: 'Blockchain_Icanpay/js/view/payment/method-renderer/icanpay'
            }
        );

        /** Add view logic here if needed */
        return Component.extend({});
    });
