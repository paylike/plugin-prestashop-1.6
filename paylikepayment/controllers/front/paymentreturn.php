<?php
/**
 *
 * @author    DerikonDevelopment <ionut@derikon.com>
 * @copyright Copyright (c) permanent, DerikonDevelopment
 * @license   Addons PrestaShop license limitation
 * @link      http://www.derikon.com/
 *
 */

if ( ! class_exists( 'Paylike\\Client' ) ) {
	require_once( 'modules/paylikepayment/api/Client.php' );
}

// use Paylike;

class PaylikepaymentPaymentReturnModuleFrontController extends ModuleFrontController {
	public function __construct() {
		parent::__construct();
		$this->display_column_right = false;
		$this->display_column_left = false;
		$this->context = Context::getContext();
	}

	public function init() {
		parent::init();
		$cart = $this->context->cart;
		if ( $cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || ! $this->module->active ) {
			Tools::redirect( 'index.php?controller=order&step=1' );
		}

		$authorized = false;
		foreach ( Module::getPaymentModules() as $module ) {
			if ( $module['name'] == 'paylikepayment' ) {
				$authorized = true;
				break;
			}
		}

		if ( ! $authorized ) {
			die( $this->module->l( 'Paylike payment method is not available.', 'paymentreturn' ) );
		}


		if ( Configuration::get( 'PAYLIKE_CHECKOUT_MODE' ) == 'delayed' ) {
			$this->fetch();
		} else {
			$this->capture();
		}
	}

	public function fetch() {
		$cart = $this->context->cart;
		$customer = new Customer( $cart->id_customer );
		if ( ! Validate::isLoadedObject( $customer ) ) {
			Tools::redirect( 'index.php?controller=order&step=1' );
		}

		Paylike\Client::setKey( Configuration::get( 'PAYLIKE_SECRET_KEY' ) );
		$cart_total = $cart->getOrderTotal( true, Cart::BOTH );
		$currency = new Currency( (int) $cart->id_currency );
		$currency_multiplier = $this->module->getPaylikeCurrencyMultiplier( $currency->iso_code );
		$cart_amount = $this->module->getPaylikeAmount( $cart_total, $currency->iso_code );
		$transactionid = Tools::getValue( 'transactionid' );

		$transaction_failed = false;

		$fetch = Paylike\Transaction::fetch( $transactionid );
		//print_r($fetch);

		if ( is_array( $fetch ) && isset( $fetch['error'] ) && $fetch['error'] == 1 ) {
			PrestaShopLogger::addLog( $fetch['message'] );
			$this->context->smarty->assign( array(
				'paylike_order_error'   => 1,
				'paylike_error_message' => $fetch['message']
			) );

			return $this->setTemplate( 'payment_error.tpl' );
		} else {
			if ( ! empty( $fetch['transaction'] ) ) {
				$transaction = $fetch['transaction'];
				if ( (string) $transaction['amount'] != (string) $cart_amount || $transaction['currency'] != $currency->iso_code ) {
					PrestaShopLogger::addLog( 'Invalid transaction.' );
					$this->context->smarty->assign( array(
						'paylike_order_error'   => 1,
						'paylike_error_message' => 'Invalid transaction.'
					) );

					return $this->setTemplate( 'payment_error.tpl' );
				} else {
					$message = 'Trx ID: ' . $transactionid . '
                        Authorized Amount: ' . ( $fetch['transaction']['amount'] / $currency_multiplier ) . '
                        Captured Amount: ' . ( $fetch['transaction']['capturedAmount'] / $currency_multiplier ) . '
                        Order time: ' . $fetch['transaction']['created'] . '
                        Currency code: ' . $fetch['transaction']['currency'];

					$total = $fetch['transaction']['amount'] / $currency_multiplier;
					$amount = $fetch['transaction']['amount'];

					//$status_paid = Configuration::get('PS_OS_PAYMENT');
					$status_paid = 3; //Processing in progress

					if ( $this->module->validateOrder( (int) $cart->id, $status_paid, $total, $this->module->displayName, $message, array(), null, false, $customer->secure_key ) ) {
						$this->module->storeTransactionID( $transactionid, $this->module->currentOrder, $total, $captured = 'NO' );

						$redirectLink = __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key;
						Tools::redirectLink( $redirectLink );
					} else {
						//Paylike\Transaction::void($transactionid, ['amount' => $amount]); //Cancel Order
						Paylike\Transaction::void( $transactionid, array( 'amount' => $fetch['transaction']['amount'] ) ); //Cancel Order

						PrestaShopLogger::addLog( 'Invalid transaction.' );
						$this->context->smarty->assign( array(
							'paylike_order_error'   => 1,
							'paylike_error_message' => 'Invalid transaction.'
						) );

						return $this->setTemplate( 'payment_error.tpl' );
					}
				}
			} else {
				if ( ! empty( $fetch[0]['message'] ) ) {
					PrestaShopLogger::addLog( $fetch[0]['message'] );
					$this->context->smarty->assign( array(
						'paylike_order_error'   => 1,
						'paylike_error_message' => $fetch[0]['message']
					) );

					return $this->setTemplate( 'payment_error.tpl' );
				} else {
					PrestaShopLogger::addLog( 'Invalid transaction.' );
					$this->context->smarty->assign( array(
						'paylike_order_error'   => 1,
						'paylike_error_message' => 'Invalid transaction.'
					) );

					return $this->setTemplate( 'payment_error.tpl' );
				}
			}
		}
	}

	public function capture() {
		$cart = $this->context->cart;
		$customer = new Customer( $cart->id_customer );
		if ( ! Validate::isLoadedObject( $customer ) ) {
			Tools::redirect( 'index.php?controller=order&step=1' );
		}

		Paylike\Client::setKey( Configuration::get( 'PAYLIKE_SECRET_KEY' ) );
		$cart_total = $cart->getOrderTotal( true, Cart::BOTH );
		$currency = new Currency( (int) $cart->id_currency );
		$currency_multiplier = $this->module->getPaylikeCurrencyMultiplier( $currency->iso_code );
		$cart_amount = $this->module->getPaylikeAmount( $cart_total, $currency->iso_code );
		//$status_paid = (int)Configuration::get('PAYLIKE_ORDER_STATUS');
		//$status_paid = Configuration::get('PS_OS_PAYMENT');
		$transactionid = Tools::getValue( 'transactionid' );

		Paylike\Client::setKey( Configuration::get( 'PAYLIKE_SECRET_KEY' ) );
		$fetch = Paylike\Transaction::fetch( $transactionid );

		if ( is_array( $fetch ) && isset( $fetch['error'] ) && $fetch['error'] == 1 ) {
			PrestaShopLogger::addLog( $fetch['message'] );
			$this->context->smarty->assign( array(
				'paylike_order_error'   => 1,
				'paylike_error_message' => $fetch['message']
			) );

			return $this->setTemplate( 'payment_error.tpl' );
		} else {
			if ( ! empty( $fetch['transaction'] ) ) {
				$transaction = $fetch['transaction'];
				if ( (string) $transaction['amount'] != (string) $cart_amount || $transaction['currency'] != $currency->iso_code ) {
					PrestaShopLogger::addLog( 'Invalid transaction.' );
					$this->context->smarty->assign( array(
						'paylike_order_error'   => 1,
						'paylike_error_message' => 'Invalid transaction.'
					) );

					return $this->setTemplate( 'payment_error.tpl' );
				} else {
					$status_paid = (int) Configuration::get( 'PAYLIKE_ORDER_STATUS' );

					$data = array(
						'currency' => $currency->iso_code,
						'amount'   => $cart_amount,
					);
					$capture = Paylike\Transaction::capture( $transactionid, $data );

					if ( is_array( $capture ) && ! empty( $capture['error'] ) && $capture['error'] == 1 ) {
						PrestaShopLogger::addLog( $capture['message'] );
						$this->context->smarty->assign( array(
							'paylike_order_error'   => 1,
							'paylike_error_message' => $capture['message']
						) );

						return $this->setTemplate( 'payment_error.tpl' );
					} else if ( ! empty( $capture['transaction'] ) ) {

						$total = $capture['transaction']['amount'] / $currency_multiplier;
						$amount = $capture['transaction']['amount'];

						$validOrder = $this->module->validateOrder( (int) $cart->id, $status_paid, $total, $this->module->displayName, null, array(), null, false, $customer->secure_key );

						$message = 'Trx ID: ' . $transactionid . '
                            Authorized Amount: ' . ( $capture['transaction']['amount'] / $currency_multiplier ) . '
                            Captured Amount: ' . ( $capture['transaction']['capturedAmount'] / $currency_multiplier ) . '
                            Order time: ' . $capture['transaction']['created'] . '
                            Currency code: ' . $capture['transaction']['currency'];

						$msg = new Message();
						$message = strip_tags( $message, '<br>' );
						if ( Validate::isCleanHtml( $message ) ) {
							$msg->message = $message;
							$msg->id_cart = (int) $cart->id;
							$msg->id_customer = (int) $cart->id_customer;
							$msg->id_order = (int) $this->module->currentOrder;
							$msg->private = 1;
							$msg->add();
						}

						$this->module->storeTransactionID( $transactionid, $this->module->currentOrder, $total, $captured = 'YES' );
						$redirectLink = __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key;
						Tools::redirectLink( $redirectLink );
					} else {
						Paylike\Transaction::void( $transactionid, array( 'amount' => $fetch['transaction']['amount'] ) ); //Cancel Order
						PrestaShopLogger::addLog( 'Invalid transaction.' );
						$this->context->smarty->assign( array(
							'paylike_order_error'   => 1,
							'paylike_error_message' => 'Invalid transaction.'
						) );

						return $this->setTemplate( 'payment_error.tpl' );
					}
				}
			} else {
				if ( ! empty( $fetch[0]['message'] ) ) {
					PrestaShopLogger::addLog( $fetch[0]['message'] );
					$this->context->smarty->assign( array(
						'paylike_order_error'   => 1,
						'paylike_error_message' => $fetch[0]['message']
					) );

					return $this->setTemplate( 'payment_error.tpl' );
				} else {
					PrestaShopLogger::addLog( 'Invalid transaction.' );
					$this->context->smarty->assign( array(
						'paylike_order_error'   => 1,
						'paylike_error_message' => 'Invalid transaction.'
					) );

					return $this->setTemplate( 'payment_error.tpl' );
				}
			}
		}
	}
}
