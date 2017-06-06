<?php
if ( ! class_exists( 'Paylike\\Client' ) ) {
    require_once('modules/paylikepayment/api/Client.php');
}

use Paylike;




class PaylikepaymentPaymentReturnModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->display_column_right = false;
        $this->display_column_left = false;
        $this->context = Context::getContext();
    }

    public function init()
    {
        parent::init();
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
            Tools::redirect('index.php?controller=order&step=1');

        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'paylikepayment')
            {
                $authorized = true;
                break;
            }

        if (!$authorized)
            die($this->module->l('Paylike payment method is not available.', 'paymentreturn'));

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        Paylike\Client::setKey(Configuration::get('PAYLIKE_SECRET_KEY'));
        $total = $cart->getOrderTotal(true, Cart::BOTH);
        $currency = new Currency((int)$cart->id_currency);
        $amount = Tools::ps_round($total, 2) * 100;
        //$status_paid = (int)Configuration::get('PAYLIKE_ORDER_STATUS');
        //$status_paid = Configuration::get('PS_OS_PAYMENT');
        $transactionid = Tools::getValue('transactionid');

        $transaction_failed = false;

        if (Configuration::get('PAYLIKE_CHECKOUT_MODE') == 'delayed') {
            $fetch = Paylike\Transaction::fetch( $transactionid );

            if(is_array($fetch) && isset($fetch['error']) && $fetch['error'] == 1) {
                PrestaShopLogger::addLog($fetch['message']);
                $this->context->smarty->assign(array(
                    'paylike_order_error' => 1,
                    'paylike_error_message' => $fetch['message']
                ));
                return $this->setTemplate('payment_error.tpl');

            } 
            //else if (is_array($fetch) && $fetch['transaction']['currency'] == $currency->iso_code && $fetch['transaction']['custom']['cartId'] == $cart->id && (int)$fetch['transaction']['amount'] == (int)$amount) {
            else if (is_array($fetch) && $fetch['transaction']['currency'] == $currency->iso_code && $fetch['transaction']['custom']['cartId'] == $cart->id) {
                $message = 'Trx ID: '.$transactionid.'
                    Authorized Amount: '.($fetch['transaction']['amount'] / 100).'
                    Captured Amount: '.($fetch['transaction']['capturedAmount'] / 100).'
                    Order time: '.$fetch['transaction']['created'].'
                    Currency code: '.$fetch['transaction']['currency'];
                
                //$status_paid = Configuration::get('PS_OS_PAYMENT');
                $status_paid = 3; //Processing in progress

                if ($this->module->validateOrder((int)$cart->id, $status_paid, $total, $this->module->displayName, $message, array(), null, false, $customer->secure_key)) {
                    $this->module->storeTransactionID($transactionid, $this->module->currentOrder, $total, $captured='NO');
                    Tools::redirectLink(__PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);

                } else {
                    $transaction_failed = true;
                    Paylike\Transaction::void($transactionid, ['amount' => $amount]); //Cancel Order
                }

            } else {
                $transaction_failed = true;
            }
            

        } else {
            $status_paid = (int)Configuration::get('PAYLIKE_ORDER_STATUS');
            
            $validOrder = $this->module->validateOrder((int)$cart->id, $status_paid, $total, $this->module->displayName, null, array(), null, false, $customer->secure_key);

            if ($validOrder) {
                $data = [
                    'currency' => $currency->iso_code,
                    'descriptor' => "Order #".$this->module->currentOrder,
                    'amount' => $amount,
                ];

                $capture = Paylike\Transaction::capture($transactionid, $data);

                if(is_array($capture) && !empty($capture['error']) && $capture['error'] == 1) {
                    PrestaShopLogger::addLog($capture['message']);
                    $this->context->smarty->assign(array(
                        'paylike_order_error' => 1,
                        'paylike_error_message' => $capture['message']
                    ));
                    return $this->setTemplate('payment_error.tpl');

                } else if(!empty($capture['transaction'])) {
                    $message = 'Trx ID: '.$transactionid.'
                        Authorized Amount: '.($capture['transaction']['amount'] / 100).'
                        Captured Amount: '.($capture['transaction']['capturedAmount'] / 100).'
                        Order time: '.$capture['transaction']['created'].'
                        Currency code: '.$capture['transaction']['currency'];

                    $msg = new Message();
                    $message = strip_tags($message, '<br>');
                    if (Validate::isCleanHtml($message))
                    {
                        $msg->message = $message;
                        $msg->id_cart = (int)$cart->id;
                        $msg->id_customer = (int)$cart->id_customer;
                        $msg->id_order = (int)$this->module->currentOrder;
                        $msg->private = 1;
                        $msg->add();
                    }

                    $this->module->storeTransactionID($transactionid, $this->module->currentOrder, $total, $captured='YES');
                    $redirectLink = __PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key;
                    Tools::redirectLink($redirectLink);

                } else
                    $transaction_failed = true;

            } else
                $transaction_failed = true;
        }

        if ($transaction_failed) {
            $this->context->smarty->assign('paylike_order_error', 1);
            return $this->setTemplate('payment_error.tpl');
        }
    }

}