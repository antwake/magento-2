define([
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
    ],
    function ($, Component) {
        'use strict';

        var self;

        return Component.extend({

            defaults: {
                template: 'Blockchain_Icanpay/payment/icanpay'
            },

            initialize: function () {
                self = this;
                this._super();
            },

            context: function() {
                return this;
            },

            getCode: function() {
                return 'blockchain_icanpay';
            },

            isActive: function() {
                return true;
            }
        });
    }
);