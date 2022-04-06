/// <reference types="cypress" />

'use strict';

import { PaylikeTestHelper } from './test_helper.js';

export var TestMethods = {

    /** Admin & frontend user credentials. */
    StoreUrl: (Cypress.env('ENV_ADMIN_URL').match(/^(?:http(?:s?):\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/im))[0],
    AdminUrl: Cypress.env('ENV_ADMIN_URL'),
    RemoteVersionLogUrl: Cypress.env('REMOTE_LOG_URL'),

    /** Construct some variables to be used bellow. */
    ShopName: 'prestashop16',
    PaylikeName: 'paylike',
    OrderStatusForCapture: '',
    /** We need to have "&module_name=paylikepayment" at the end because permission error accessing the link. */
    PaymentMethodAdminUrl: '/index.php?controller=AdminModules&configure=paylikepayment&module_name=paylikepayment',
    ModulesAdminUrl: '/index.php?controller=AdminModules&filterCategory=payments_gateways',
    OrdersPageAdminUrl: '/index.php?controller=AdminOrders',

    /**
     * Login to admin backend account
     */
    loginIntoAdminBackend() {
        cy.goToPage(this.AdminUrl);
        cy.loginIntoAccount('input[name=email]', 'input[name=passwd]', 'admin');
    },
    /**
     * Login to client|user frontend account
     */
    loginIntoClientAccount() {
        cy.goToPage(this.StoreUrl + '/login?&back=my-account');
        cy.loginIntoAccount('input[id=email]', 'input[name=passwd]', 'client');
    },

    /**
     * Modify Paylike settings
     * @param {String} captureMode
     */
    changePaylikeCaptureMode(captureMode) {
        /** Go to Paylike payment method. */
        this.goToPageAndIgnoreWarning(this.PaymentMethodAdminUrl);

        /**
         * Get order statuses to be globally used.
         */
        this.getPaylikeOrderStatuses();

        /** Select capture mode. */
        cy.get('#PAYLIKE_CHECKOUT_MODE').select(captureMode);

        /** Save. */
        cy.get('#module_form_submit_btn').click();
    },

    /**
     * Make payment with specified currency and process order
     *
     * @param {String} currency
     * @param {String} paylikeAction
     * @param {Boolean} partialAmount
     */
     payWithSelectedCurrency(currency, paylikeAction, partialAmount = false) {
        /** Make an instant payment. */
        it(`makes a Paylike payment with "${currency}"`, () => {
            this.makePaymentFromFrontend(currency);
        });

        /** Process last order from admin panel. */
        it(`process (${paylikeAction}) an order from admin panel`, () => {
            this.processOrderFromAdmin(paylikeAction,  currency, partialAmount);
        });
    },

    /**
     * Make an instant payment
     * @param {String} currency
     */
    makePaymentFromFrontend(currency) {
        /** Go to store frontend. */
        cy.goToPage(this.StoreUrl);

        /** Change currency. */
        this.changeShopCurrency(currency);

        cy.wait(300);

        /** Select random product. */
        var randomInt = PaylikeTestHelper.getRandomInt(/*max*/ 6);
        cy.get('.replace-2x.img-responsive').eq(randomInt).click();

        /** Add to cart. */
        cy.get('#add_to_cart').click();

        /** Wait to add to cart. */
        cy.wait(1000);

        /** Go to checkout. */
        cy.goToPage(this.StoreUrl + '/quick-order');

        /** Agree Terms & Conditions. */
        cy.get('#cgv', {timeout: 10000}).click();

        /** Wait until amount can be accessed. */
        cy.wait(2000);

        /** Check amount. */
        cy.get('#total_price').then($grandTotal => {
            var expectedAmount = PaylikeTestHelper.filterAndGetAmountInMinor($grandTotal, currency);
            cy.window().then(win => {
                expect(expectedAmount).to.eq(Number(win.amount));
            });
        });

        /** Show Paylike popup. */
        cy.get('.paylike-payment').click();

        /**
         * Fill in Paylike popup.
         */
         PaylikeTestHelper.fillAndSubmitPaylikePopup();

        cy.get('#center_column', {timeout: 10000}).should('contain', 'Congratulations');
    },

    /**
     * Process last order from admin panel
     * @param {String} paylikeAction
     * @param {Boolean} partialAmount
     */
    processOrderFromAdmin(paylikeAction,  currency = '', partialAmount = false) {
        /** Go to admin orders page. */
        cy.goToPage(this.OrdersPageAdminUrl);

        PaylikeTestHelper.setPositionRelativeOn('#header_infos');
        PaylikeTestHelper.setPositionRelativeOn('.page-head');

        /** Click on first (latest in time) order from orders table. */
        cy.get('table.order tbody tr').first().click();

        /**
         * Take specific action on order
         */
        this.paylikeActionOnOrderAmount(paylikeAction,  currency, partialAmount);
    },

    /**
     * Capture an order amount
     * @param {String} paylikeAction
     * @param {Boolean} partialAmount
     */
     paylikeActionOnOrderAmount(paylikeAction,  currency = '', partialAmount = false) {
         /** Select paylike action. */
        cy.get('#paylike_action').select(paylikeAction);

        if ('refund' === paylikeAction) {
            cy.get('#total_order  .amount').then(($totalAmount) => {
                var majorAmount = PaylikeTestHelper.filterAndGetAmountInMajorUnit($totalAmount, currency);
                /**
                 * Subtract 2 from total amount.
                 * Assume that we have products with total amount > 2 units
                 */
                if (partialAmount) {
                    majorAmount -= 2
                }
                cy.get('input[name=paylike_amount_to_refund]').clear().type(`${majorAmount}`);
                cy.get('input[name=paylike_refund_reason]').clear().type('automatic refund');
            });
        }

        /** Submit and check errors not exists. */
        cy.get('#submit_paylike_action').click();
        cy.wait(1000);
        cy.get('#alert.alert-info').should('not.exist');
        cy.get('#alert.alert-warning').should('not.exist');
        cy.get('#alert.alert-danger').should('not.exist');

    },

    /**
     * Change shop currency in frontend
     */
    changeShopCurrency(currency) {
        cy.get('#setCurrency div.current').click();
        cy.get('ul[id="first-currencies"] li a').contains(currency).click();
    },

    /**
     * Get Paylike order statuses from settings
     */
     getPaylikeOrderStatuses() {
        /** Get order status for capture. */
        cy.get('#PAYLIKE_ORDER_STATUS > option[selected=selected]').then($captureStatus => {
            this.OrderStatusForCapture = $captureStatus.text();
        });
    },

    /**
     * Get Shop & Paylike versions and send log data.
     */
    logVersions() {
        /** Get framework version. */
        cy.get('#shop_version').then($frameworkVersion => {
            var frameworkVersion = ($frameworkVersion.text()).replace(/[^0-9.]/g, '');
            cy.wrap(frameworkVersion).as('frameworkVersion');
        });

        this.goToPageAndIgnoreWarning(this.ModulesAdminUrl);

        /** Get Paylike version. */
        cy.get('#anchorPaylikepayment .module_name').invoke('text').then($pluginVersion => {
            var pluginVersion = $pluginVersion.replace(/[^0-9.]/g, '');
            cy.wrap(pluginVersion).as('pluginVersion');
        });

        /** Get global variables and make log data request to remote url. */
        cy.get('@frameworkVersion').then(frameworkVersion => {
            cy.get('@pluginVersion').then(pluginVersion => {

                cy.request('GET', this.RemoteVersionLogUrl, {
                    key: frameworkVersion,
                    tag: this.ShopName,
                    view: 'html',
                    ecommerce: frameworkVersion,
                    plugin: pluginVersion
                }).then((resp) => {
                    expect(resp.status).to.eq(200);
                });
            });
        });
    },

    /**
     * Go to page & ignore token warning
     */
    goToPageAndIgnoreWarning(pageUri) {
        cy.goToPage(pageUri);
        /**
         * Accept token warning.
         * This warning show up even if we set the token on url.
         * So, we do not set it and click on the button.
         */
         cy.get(`a[href*="${pageUri}"]`).click();
    },
}