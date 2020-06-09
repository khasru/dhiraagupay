/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/url-builder',
    'mage/storage',
    'Dhimart_Dhiraagupay/js/form-builder',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/place-order',
    'Magento_Customer/js/customer-data'
], function (
    $,
    Component,
    quote,
    customer,
    urlBuilder,
    storage,
    formBuilder,
    errorProcessor,
    fullScreenLoader,
    placeOrderService,
    customerData
) {
    'use strict';

    return Component.extend({

        defaults: {
            template: 'Dhimart_Dhiraagupay/payment/dhiraagupay-form'
        },

        /** Open window with  */
        showAcceptanceWindow: function (data, event) {
            window.open(
                $(event.target).attr('href'),
                'olcwhatispaypal',
                'toolbar=no, location=no,' +
                ' directories=no, status=no,' +
                ' menubar=no, scrollbars=yes,' +
                ' resizable=yes, ,left=0,' +
                ' top=0, width=400, height=350'
            );

            return false;
        },

        sendOtp: function () {

            var mobile = $('#dhiraagupay_mobile').val();               
            
            if(mobile.length==0){
                alert('Please enter dhiraagupay number!!!');
                return false;
            }
            if(mobile.length<7){
                alert('Please enter valid dhiraagupay number!');
                return false;
             }
            var otpUrl= window.checkoutConfig.payment[this.getCode()].transactionDataUrl;

            jQuery.ajax({
                url : otpUrl,
                data : {"mobile":mobile},
                type : "POST",
                success : function (response) {
                    if(response.status== 1){
                      // $('#getotpbymobile').hide();  
                       $('.field.otp').show();  
                       $('#dhiraagupay-actions-toolbar').show();
                       return true;
                    }else{
                        alert(response.message);
                       $('#getotpbymobile').show();  
                       $('.field.otp').hide();  
                       $('#dhiraagupay-actions-toolbar').hide();  
                       return false;
                    }
                   
                }
            });
        },

        getData: function() {
            return {
                'method': this.item.method,
                'additional_data': {
                    'mobile': $('#dhiraagupay_mobile').val(),
                    'otp': $('#dhiraagupay_otp').val()
                }
            };
        },

        validate: function() {
            var $form = $('#' + this.getCode() + '-form');
            var otp= $('#' + this.getCode() +'_otp').val();
            /*if(otp.length==0){
                alert('Please Enter OTP');
            }*/
            return $form.validation() && $form.validation('isValid');
        },

     /** @inheritdoc */
        getGrandTotal: function () {
            return window.checkoutConfig.payment[this.getCode()].grandTotal;
        },


        /** Returns payment acceptance mark image path */
        getPaymentAcceptanceMarkSrc: function () {
            return window.checkoutConfig.payment[this.getCode()].paymentAcceptanceMarkSrc;
        },

         /** Returns payment otp request ajax url */
        getOTPRequestUrl: function () {
            return window.checkoutConfig.payment[this.getCode()].transactionDataUrl;
        },

        getTermsAndConditions: function() {
            return window.checkoutConfig.payment[this.getCode()].paymentTermsAndConditions;
        },

        /**
         * Payment method code getter
         * @returns {String}
         */
        getCode: function () {
            return 'dhiraagupay';
        }
    });
});
