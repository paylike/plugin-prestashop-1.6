<?php
if (!defined('_PS_VERSION_'))
    exit;

if ( ! class_exists( 'Paylike\\Client' ) ) {
    require_once('api/Client.php');
}
//use Paylike;

class PaylikePayment extends PaymentModule
{
    private $_html = '';
    protected $statuses_array = array();

    public function __construct()
    {
        $this->name = 'paylikepayment';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Pravin';
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Paylike');
        $this->description = $this->l('Receive payment with Paylike');
        $this->confirmUninstall = $this->l('Are you sure about removing Paylike?');
        $popupDescription = $this->l('Secure payment with credit card via © Paylike');
    }

    public function install()
    {
        $popup_title = (!empty(Configuration::get('PS_SHOP_NAME'))) ? Configuration::get('PS_SHOP_NAME') : 'Payment';
        $language_code = $this->context->language->iso_code;

        Configuration::updateValue('PAYLIKE_LANGUAGE_CODE', $language_code);
        Configuration::updateValue($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE', 'Credit card');
        Configuration::updateValue('PAYLIKE_PAYMENT_METHOD_LOGO', 'visa.svg');
        Configuration::updateValue($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC', 'Secure payment with credit card via © Paylike');
        Configuration::updateValue($language_code.'_PAYLIKE_POPUP_TITLE', $popup_title);
        Configuration::updateValue('PAYLIKE_SHOW_POPUP_DESC', 'no');
        Configuration::updateValue($language_code.'_PAYLIKE_POPUP_DESC', '');
        Configuration::updateValue('PAYLIKE_TRANSACTION_MODE', 'test');
        Configuration::updateValue('PAYLIKE_TEST_PUBLIC_KEY', '');
        Configuration::updateValue('PAYLIKE_TEST_SECRET_KEY', '');
        Configuration::updateValue('PAYLIKE_LIVE_PUBLIC_KEY', '');
        Configuration::updateValue('PAYLIKE_LIVE_SECRET_KEY', '');
        Configuration::updateValue('PAYLIKE_CHECKOUT_MODE', 'delayed');
        Configuration::updateValue('PAYLIKE_ORDER_STATUS', Configuration::get('PAYLIKE_ORDER_STATUS'));
        Configuration::updateValue('PAYLIKE_STATUS', 'enabled');
        Configuration::updateValue('PAYLIKE_SECRET_KEY', '');
        return (parent::install()
            && $this->registerHook('header')
            && $this->registerHook('payment')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('DisplayAdminOrder')
            && $this->registerHook('BackOfficeHeader')
            && $this->installDb());
    }

    public function installDb()
    {
        return (
            Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'paylike_admin` (
                `id`				int(11) NOT NULL AUTO_INCREMENT,
                `paylike_tid`		varchar(255) NOT NULL,
                `order_id`			int(11) NOT NULL,
                `payed_at`			datetime NOT NULL,
                `payed_amount`		DECIMAL(20,6) NOT NULL,
                `refunded_amount`	DECIMAL(20,6) NOT NULL,
                `captured`		    varchar(255) NOT NULL,
                PRIMARY KEY			(`id`)
                ) ENGINE=InnoDB		DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;')

            && Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'paylike_logos` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `slug` varchar(255) NOT NULL,
                `file_name` varchar(255) NOT NULL,
                `default_logo` int(11) NOT NULL DEFAULT 1 COMMENT "1=Default",
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`)
                ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;')

            && Db::getInstance()->insert('paylike_logos', array(
                    array(
                        'id' => 1,
                        'name' => pSQL('VISA'),
                        'slug' => pSQL('visa'),
                        'file_name' => pSQL('visa.svg'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ),
                    array(
                        'id' => 2,
                        'name' => pSQL('VISA Electron'),
                        'slug' => pSQL('visa-electron'),
                        'file_name' => pSQL('visa-electron.svg'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ),
                    array(
                        'id' => 3,
                        'name' => pSQL('Mastercard'),
                        'slug' => pSQL('mastercard'),
                        'file_name' => pSQL('mastercard.svg'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ),
                    array(
                        'id' => 4,
                        'name' => pSQL('Mastercard Maestro'),
                        'slug' => pSQL('mastercard-maestro'),
                        'file_name' => pSQL('mastercard-maestro.svg'),
                        'created_at' => date('Y-m-d H:i:s'),
                    ),
                )
            )
        );
    }

    public function uninstall()
    {
        //$sql = 'SELECT * FROM `'._DB_PREFIX_.'paylike_logos`';
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('paylike_logos', 'PL');
        $sql->where('PL.default_logo != 1');
        $logos = Db::getInstance()->executes($sql);

        foreach($logos as $logo) {
            if( file_exists(__DIR__.'/logos/'.$logo['file_name']) ) {
                unlink(__DIR__.'/logos/'.$logo['file_name']);
            }
        }

        //Fetch all languages and delete Paylike configurations which has language iso_code as prefix
        $languages = Language::getLanguages(true, $this->context->shop->id);
        foreach($languages as $language) {
            $language_code = $language['iso_code'];
            Configuration::deleteByName($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE');
            Configuration::deleteByName($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC');
            Configuration::deleteByName($language_code.'_PAYLIKE_POPUP_TITLE');
            Configuration::deleteByName($language_code.'_PAYLIKE_POPUP_DESC');
        }

        return (
            parent::uninstall()
            && Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'paylike_admin`')
            && Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'paylike_logos`')
            && Configuration::deleteByName('PAYLIKE_PAYMENT_METHOD_TITLE')
            && Configuration::deleteByName('PAYLIKE_PAYMENT_METHOD_LOGO')
            && Configuration::deleteByName('PAYLIKE_PAYMENT_METHOD_DESC')
            && Configuration::deleteByName('PAYLIKE_POPUP_TITLE')
            && Configuration::deleteByName('PAYLIKE_SHOW_POPUP_DESC')
            && Configuration::deleteByName('PAYLIKE_POPUP_DESC')
            && Configuration::deleteByName('PAYLIKE_TRANSACTION_MODE')
            && Configuration::deleteByName('PAYLIKE_TEST_PUBLIC_KEY')
            && Configuration::deleteByName('PAYLIKE_TEST_SECRET_KEY')
            && Configuration::deleteByName('PAYLIKE_LIVE_PUBLIC_KEY')
            && Configuration::deleteByName('PAYLIKE_LIVE_SECRET_KEY')
            && Configuration::deleteByName('PAYLIKE_CHECKOUT_MODE')
            && Configuration::deleteByName('PAYLIKE_ORDER_STATUS')
            && Configuration::deleteByName('PAYLIKE_STATUS')
            && Configuration::deleteByName('PAYLIKE_SECRET_KEY')
        );
    }

    public function getContent()
    {
        $this->_html = '';
        if (Tools::isSubmit('submitPaylike'))
        {
            $language_code = Configuration::get('PAYLIKE_LANGUAGE_CODE');
            $valid = true;

            $PAYLIKE_PAYMENT_METHOD_TITLE = !empty(Tools::getvalue($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE')) ? Tools::getvalue($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE') : '';
            $PAYLIKE_PAYMENT_METHOD_DESC = !empty(Tools::getvalue($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC')) ? Tools::getvalue($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC') : '';
            $PAYLIKE_POPUP_TITLE = (!empty(Tools::getvalue($language_code.'_PAYLIKE_POPUP_TITLE'))) ? Tools::getvalue($language_code.'_PAYLIKE_POPUP_TITLE') : '';
            $_PAYLIKE_POPUP_DESC = (!empty(Tools::getvalue($language_code.'_PAYLIKE_POPUP_DESC'))) ? Tools::getvalue($language_code.'_PAYLIKE_POPUP_DESC') : '';

            if( empty($PAYLIKE_PAYMENT_METHOD_TITLE) ) {
                $this->context->controller->errors[$language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE'] = $this->l('Payment method title required!');
                $PAYLIKE_PAYMENT_METHOD_TITLE = (!empty(Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE'))) ? Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE') : '';
                $valid = false;
            }

            if(count(Tools::getvalue('PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO')) > 1){
                $creditCardLogo = implode(',', Tools::getvalue('PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO'));
            } else {
                $creditCardLogo = Tools::getvalue('PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO');
            }


            if (Tools::getvalue('PAYLIKE_TRANSACTION_MODE') == 'test') {
                if(!Tools::getvalue('PAYLIKE_TEST_PUBLIC_KEY')) {
                    $this->context->controller->errors['PAYLIKE_TEST_PUBLIC_KEY'] = $this->l('Test mode Public Key required!');
                    $PAYLIKE_TEST_PUBLIC_KEY = (!empty(Configuration::get('PAYLIKE_TEST_PUBLIC_KEY'))) ? Configuration::get('PAYLIKE_TEST_PUBLIC_KEY') : '';
                    $valid = false;
                } else {
                    $PAYLIKE_TEST_PUBLIC_KEY = (!empty(Tools::getvalue('PAYLIKE_TEST_PUBLIC_KEY'))) ? Tools::getvalue('PAYLIKE_TEST_PUBLIC_KEY') : '';
                }

                if(!Tools::getvalue('PAYLIKE_TEST_SECRET_KEY')) {
                    $this->context->controller->errors['PAYLIKE_TEST_SECRET_KEY'] = $this->l('Test mode App Key required!');
                    $PAYLIKE_TEST_SECRET_KEY = (!empty(Configuration::get('PAYLIKE_TEST_SECRET_KEY'))) ? Configuration::get('PAYLIKE_TEST_SECRET_KEY') : '';
                    $valid = false;
                } else {
                    $PAYLIKE_TEST_SECRET_KEY = (!empty(Tools::getvalue('PAYLIKE_TEST_SECRET_KEY'))) ? Tools::getvalue('PAYLIKE_TEST_SECRET_KEY') : '';
                }

            } else if (Tools::getvalue('PAYLIKE_TRANSACTION_MODE') == 'live') {
                if(!Tools::getvalue('PAYLIKE_LIVE_PUBLIC_KEY')) {
                    $this->context->controller->errors['PAYLIKE_LIVE_PUBLIC_KEY'] = $this->l('Live mode Public Key required!');
                    $PAYLIKE_LIVE_PUBLIC_KEY = (!empty(Configuration::get('PAYLIKE_LIVE_PUBLIC_KEY'))) ? Configuration::get('PAYLIKE_LIVE_PUBLIC_KEY') : '';
                    $valid = false;
                } else {
                    $PAYLIKE_LIVE_PUBLIC_KEY = (!empty(Tools::getvalue('PAYLIKE_LIVE_PUBLIC_KEY'))) ? Tools::getvalue('PAYLIKE_LIVE_PUBLIC_KEY') : '';
                }

                if(!Tools::getvalue('PAYLIKE_LIVE_SECRET_KEY')) {
                    $this->context->controller->errors['PAYLIKE_LIVE_SECRET_KEY'] = $this->l('Live mode App Key required!');
                    $PAYLIKE_LIVE_SECRET_KEY = (!empty(Configuration::get('PAYLIKE_LIVE_SECRET_KEY'))) ? Configuration::get('PAYLIKE_LIVE_SECRET_KEY') : '';
                    $valid = false;
                } else {
                    $PAYLIKE_LIVE_SECRET_KEY = (!empty(Tools::getvalue('PAYLIKE_LIVE_SECRET_KEY'))) ? Tools::getvalue('PAYLIKE_LIVE_SECRET_KEY') : '';
                }
            }

            Configuration::updateValue('PAYLIKE_TRANSACTION_MODE', $language_code);
            Configuration::updateValue($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE', $PAYLIKE_PAYMENT_METHOD_TITLE);
            Configuration::updateValue('PAYLIKE_PAYMENT_METHOD_LOGO', $creditCardLogo);
            Configuration::updateValue($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC', $PAYLIKE_PAYMENT_METHOD_DESC);
            Configuration::updateValue($language_code.'_PAYLIKE_POPUP_TITLE', $PAYLIKE_POPUP_TITLE);
            Configuration::updateValue('PAYLIKE_SHOW_POPUP_DESC', Tools::getvalue('PAYLIKE_SHOW_POPUP_DESC'));
            Configuration::updateValue($language_code.'_PAYLIKE_POPUP_DESC', $_PAYLIKE_POPUP_DESC);
            Configuration::updateValue('PAYLIKE_TRANSACTION_MODE', Tools::getvalue('PAYLIKE_TRANSACTION_MODE'));
            Configuration::updateValue('PAYLIKE_TEST_PUBLIC_KEY', $PAYLIKE_TEST_PUBLIC_KEY);
            Configuration::updateValue('PAYLIKE_TEST_SECRET_KEY', $PAYLIKE_TEST_SECRET_KEY);
            Configuration::updateValue('PAYLIKE_LIVE_PUBLIC_KEY', $PAYLIKE_LIVE_PUBLIC_KEY);
            Configuration::updateValue('PAYLIKE_LIVE_SECRET_KEY', Tools::getvalue('PAYLIKE_LIVE_SECRET_KEY'));
            Configuration::updateValue('PAYLIKE_CHECKOUT_MODE', Tools::getValue('PAYLIKE_CHECKOUT_MODE'));
            Configuration::updateValue('PAYLIKE_ORDER_STATUS', Tools::getValue('PAYLIKE_ORDER_STATUS'));
            Configuration::updateValue('PAYLIKE_STATUS', Tools::getValue('PAYLIKE_STATUS'));

            if($valid) {
                $this->context->controller->confirmations[] = $this->l('Settings saved successfully');
            }
        }

        //Set style for some fields of configuration form
        $style = '<style>
            .help-icon {
                font-size: 14px;
                float: right;
                width: 14px;
                height: 14px;
                margin-top: 2px;
                margin-left: 4px;
                color: #1E91CF;
            }
            .creditcard-logo {
                display: inline-block !important;
            }
            .add-more-btn {
                display: inline-block !important;
                text-decoration: none !important;
            }
        </style>';
        $this->_html .= $style;

        //Get configuration form
        $this->_html .= $this->renderForm();

        //Set script for configuration form
        $script = '<script>
            $(document).ready(function(){
                var html = \'<a href="#" class="add-more-btn" data-toggle="modal" data-target="#logoModal"><i class="process-icon-plus" data-toggle="tooltip" title="Add your own logo"></i></a>\';
                $(\'select[name="PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO[]"]\').parent(\'div\').append(html);

                $(\'[data-toggle="tooltip"]\').tooltip();

                $(\'.paylike-config\').each(function(index, item){
                    if( $(item).hasClass(\'has-error\') ) {
                        $(item).parents(\'.form-group\').addClass(\'has-error\');
                    }
                });

                //$(\'.form-group\').addClass(\'has-error\');

                $(\'.paylike-language\').bind(\'change\', paylikeLanguageChange);
            });

            function paylikeLanguageChange(e){
                var lang_code = $(e.currentTarget).val();
                window.location = "'.$this->context->link->getAdminLink('AdminOrders', false)."&change_language&lang_code=".'"+lang_code;
            }
        </script>';
        $this->_html .= $script;

        $this->_html .= $this->getModalForAddMoreLogo();

        return $this->_html;
    }

    public function renderForm()
    {
        $this->languages_array = array();
        $this->statuses_array = array();
        $this->logos_array = array();

        $language_code = Configuration::get('PAYLIKE_LANGUAGE_CODE');

        //Fetch all active languages
        $languages = Language::getLanguages(true, $this->context->shop->id);
        foreach($languages as $language) {
            $data = array(
                'id_option' => $language['iso_code'],
                'name' => $language['name']
            );
            array_push($this->languages_array, $data);
        }

        //Fetch Status list
        $valid_statuses = array('2','3','4','5','12');
        $statuses = OrderState::getOrderStates((int)$this->context->language->id);
        foreach ($statuses as $status) {
            //$this->statuses_array[$status['id_order_state']] = $status['name'];
            if(in_array($status['id_order_state'], $valid_statuses)) {
                $data = array(
                    'id_option' => $status['id_order_state'],
                    'name' => $status['name']
                );
                array_push($this->statuses_array, $data);
            }
        }

        //$sql = 'SELECT * FROM `'._DB_PREFIX_.'paylike_logos`';
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('paylike_logos');
        $logos = Db::getInstance()->executes($sql);

        foreach($logos as $logo) {
            $data = array(
                'id_option' => $logo['file_name'],
                'name' => $logo['name']
            );
            array_push($this->logos_array, $data);
        }

        //Set configuration form fields
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Paylike Payments Settings'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    /*array(
                        'type' => 'select',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Language').'">'.$this->l('Language').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_LANGUAGE_CODE',
                        'class' => 'paylike-config paylike-language',
                        'options' => array(
                            'query' => $this->languages_array,
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                    ),*/
                    array(
                        'type' => 'text',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Payment method title').'">'.$this->l('Payment method title').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => $language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE',
                        'class' => 'paylike-config',
                        'required' => true
                    ),
                    array(
                        'type' => 'select',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Choose a logo to show in frontend checkout page.').'">'.$this->l('Payment method credit card logos').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO[]',
                        'class' => 'paylike-config creditcard-logo',
                        'multiple' => true,
                        'options' => array(
                            'query' => $this->logos_array,
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Payment method description').'">'.$this->l('Payment method description').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => $language_code.'_PAYLIKE_PAYMENT_METHOD_DESC',
                        'class' => 'paylike-config',
                        //'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('The text shown in the popup where the customer inserts the card details').'">'.$this->l('Payment popup title').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => $language_code.'_PAYLIKE_POPUP_TITLE',
                        'class' => 'paylike-config',
                        //'required' => true
                    ),
                    array(
                        'type' => 'select',
                        'lang' => true,
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('If this is set to no the product list will be shown').'">'.$this->l('Show payment popup description').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_SHOW_POPUP_DESC',
                        'class' => 'paylike-config',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 'yes',
                                    'name' => 'Yes'
                                ),
                                array(
                                    'id_option' => 'no',
                                    'name' => 'No'
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Text description that shows up on the payment popup.').'">'.$this->l('Popup description').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => $language_code.'_PAYLIKE_POPUP_DESC',
                        'class' => 'paylike-config'
                    ),
                    array(
                        'type' => 'select',
                        'lang' => true,
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('In test mode, you can create a successful transaction with the card number 4100 0000 0000 0000 with any CVC and a valid expiration date.').'">'.$this->l('Transaction mode').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_TRANSACTION_MODE',
                        'class' => 'paylike-config',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 'test',
                                    'name' => 'Test'
                                ),
                                array(
                                    'id_option' => 'live',
                                    'name' => 'Live'
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Get it from your Paylike dashboard').'">'.$this->l('Test mode Public Key').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_TEST_PUBLIC_KEY',
                        'class' => 'paylike-config',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Get it from your Paylike dashboard').'">'.$this->l('Test mode App Key').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_TEST_SECRET_KEY',
                        'class' => 'paylike-config',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Get it from your Paylike dashboard').'">'.$this->l('Live mode Public Key').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_LIVE_PUBLIC_KEY',
                        'class' => 'paylike-config',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('Get it from your Paylike dashboard').'">'.$this->l('Live mode App Key').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_LIVE_SECRET_KEY',
                        'class' => 'paylike-config',
                        'required' => true
                    ),
                    array(
                        'type' => 'select',
                        'lang' => true,
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('If you deliver your product instantly (e.g. a digital product), choose Instant mode. If not, use Delayed').'">'.$this->l('Capture mode').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_CHECKOUT_MODE',
                        'class' => 'paylike-config',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 'delayed',
                                    'name' => $this->l('Delayed')
                                ),
                                array(
                                    'id_option' => 'instant',
                                    'name' => $this->l('Instant')
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        ),
                        'required' => true,
                        // 'desc' => $this->l('Instant capture: Amount is captured as soon as the order is confirmed by customer.').'<br>'.$this->l('Delayed capture: Amount is captured after order status is changed to shipped.')
                    ),
                    array(
                        'type' => 'select',
                        'lang' => true,
                        'label' => '<span data-toggle="tooltip" title="'.$this->l('The transaction will be captured once the order has the chosen status').'">'.$this->l('Capture on order status (delayed mode)').'<i class="process-icon-help-new help-icon" aria-hidden="true"></i></span>',
                        'name' => 'PAYLIKE_ORDER_STATUS',
                        'class' => 'paylike-config',
                        'options' => array(
                            'query' => $this->statuses_array,
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'select',
                        'lang' => true,
                        'name' => 'PAYLIKE_STATUS',
                        'label' => $this->l('Status'),
                        'class' => 'paylike-config',
                        'options' => array(
                            'query' => array(
                                array(
                                    'id_option' => 'enabled',
                                    'name' => 'Enabled'
                                ),
                                array(
                                    'id_option' => 'disabled',
                                    'name' => 'Disabled'
                                ),
                            ),
                            'id' => 'id_option',
                            'name' => 'name'
                        )
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = array();

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPaylike';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );

        $errors = $this->context->controller->errors;
        foreach($fields_form['form']['input'] as $key => $field) {
            if( array_key_exists($field['name'], $errors) ) {
                $fields_form['form']['input'][$key]['class'] = !empty($fields_form['form']['input'][$key]['class']) ? $fields_form['form']['input'][$key]['class'].' has-error': 'has-error';
            }
        }

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        $language_code = Configuration::get('PAYLIKE_LANGUAGE_CODE');

        $creditCardLogo = explode(',', Configuration::get('PAYLIKE_PAYMENT_METHOD_LOGO'));

        $payment_method_title = (!empty(Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE'))) ? Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE') : (!empty(Configuration::get('en_PAYLIKE_PAYMENT_METHOD_TITLE')) ? Configuration::get('en_PAYLIKE_PAYMENT_METHOD_TITLE') : '');
        $payment_method_desc = (!empty(Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC'))) ? Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC') : (!empty(Configuration::get('en_PAYLIKE_PAYMENT_METHOD_DESC')) ? Configuration::get('en_PAYLIKE_PAYMENT_METHOD_DESC') : '');
        $popup_title = (!empty(Configuration::get($language_code.'_PAYLIKE_POPUP_TITLE'))) ? Configuration::get($language_code.'_PAYLIKE_POPUP_TITLE') : (!empty(Configuration::get('en_PAYLIKE_POPUP_TITLE')) ? Configuration::get('en_PAYLIKE_POPUP_TITLE') : '');
        $popup_description = (!empty(Configuration::get($language_code.'_PAYLIKE_POPUP_DESC'))) ? Configuration::get($language_code.'_PAYLIKE_POPUP_DESC') : (!empty(Configuration::get('en_PAYLIKE_POPUP_DESC')) ? Configuration::get('en_PAYLIKE_POPUP_DESC') : '');

        if( empty($payment_method_title) ) {
            $this->context->controller->errors[$language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE'] = $this->l('Payment method title required!');
        }

        if (Configuration::get('PAYLIKE_TRANSACTION_MODE') == 'test') {
            if(!Configuration::get('PAYLIKE_TEST_PUBLIC_KEY')) {
                $this->context->controller->errors['PAYLIKE_TEST_PUBLIC_KEY'] = $this->l('Test mode Public Key required!');
            }
            if(!Configuration::get('PAYLIKE_TEST_SECRET_KEY')) {
                $this->context->controller->errors['PAYLIKE_TEST_SECRET_KEY'] = $this->l('Test mode App Key required!');
            }
        } else if (Configuration::get('PAYLIKE_TRANSACTION_MODE') == 'live') {
            if(!Configuration::get('PAYLIKE_LIVE_PUBLIC_KEY')) {
                $this->context->controller->errors['PAYLIKE_LIVE_PUBLIC_KEY'] = $this->l('Live mode Public Key required!');
            }
            if(!Configuration::get('PAYLIKE_LIVE_SECRET_KEY')) {
                $this->context->controller->errors['PAYLIKE_LIVE_SECRET_KEY'] = $this->l('Livemode App Key required!');
            }
        }
        //print_r($this->context->controller->errors);
        //die(Configuration::get('PAYLIKE_TRANSACTION_MODE'));

        return array(
            'PAYLIKE_LANGUAGE_CODE' => Configuration::get('PAYLIKE_LANGUAGE_CODE'),
            $language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE' => $payment_method_title,
            'PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO[]' => $creditCardLogo,
            $language_code.'_PAYLIKE_PAYMENT_METHOD_DESC' => $payment_method_desc,
            $language_code.'_PAYLIKE_POPUP_TITLE' => $popup_title,
            'PAYLIKE_SHOW_POPUP_DESC' => Configuration::get('PAYLIKE_SHOW_POPUP_DESC'),
            $language_code.'_PAYLIKE_POPUP_DESC' => $popup_description,
            'PAYLIKE_TRANSACTION_MODE' => Configuration::get('PAYLIKE_TRANSACTION_MODE'),
            'PAYLIKE_TEST_PUBLIC_KEY' => Configuration::get('PAYLIKE_TEST_PUBLIC_KEY'),
            'PAYLIKE_TEST_SECRET_KEY' => Configuration::get('PAYLIKE_TEST_SECRET_KEY'),
            'PAYLIKE_LIVE_PUBLIC_KEY' => Configuration::get('PAYLIKE_LIVE_PUBLIC_KEY'),
            'PAYLIKE_LIVE_SECRET_KEY' => Configuration::get('PAYLIKE_LIVE_SECRET_KEY'),
            'PAYLIKE_CHECKOUT_MODE' => Configuration::get('PAYLIKE_CHECKOUT_MODE'),
            'PAYLIKE_ORDER_STATUS' => Configuration::get('PAYLIKE_ORDER_STATUS'),
            'PAYLIKE_STATUS' => Configuration::get('PAYLIKE_STATUS'),
        );
    }

    public function getModalForAddMoreLogo()
    {
        $html = '';
        $html .= '<script>
            $(\'document\').ready(function(){
                $(\'#logo_form\').on(\'submit\', ajaxSaveLogo);
            });

            function ajaxSaveLogo(e) {
                e.preventDefault();
                $(\'#save_logo\').button(\'loading\');
                $(\'#alert\').html("").hide();
                var url = $(\'#logo_form\').attr(\'action\');
                console.log(url);
                //grab all form data
                var formData = new FormData($(this)[0]);
                $.ajax({
                    url : url,
                    type : \'POST\',
                    data : formData,
                    dataType : \'json\',
                    async: false,
                    cache: false,
                    contentType: false,
                    processData: false,
                    success : function(response) {
                        console.log(response);
                        $(\'#save_logo\').button(\'reset\');
                        if(response.status == 0) {
                            var html = "<strong>Error !</strong> " + response.message;
                            $(\'#alert\').html(html)
                                .show()
                                .removeClass(\'alert-success\')
                                .removeClass(\'alert-danger\')
                                .addClass(\'alert-danger\');
                        } else if(response.status == 1) {
                            var html = "<strong>Seccess !</strong> " + response.message;
                            $(\'#alert\').html(html)
                                .show()
                                .removeClass(\'alert-success\')
                                .removeClass(\'alert-danger\')
                                .addClass(\'alert-success\');

                            window.location = window.location;
                        }
                    },
                    error : function(response) {
                        console.log(response);
                    },
                });

                return false;
            }
        </script>';

        $html .= '<div id="logoModal" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <!-- Modal content-->
                <div class="modal-content">
                    <form id="logo_form" name="logo_form" action="'.$this->context->link->getAdminLink('AdminOrders', false).'&upload_logo" method="post" enctype="multipart/form-data">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">New logo</h4>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-success" id="alert" style="display:none;"></div>
                            <div class="form-group">
                                <label for="logo_name" class="control-label required">Logo name</label>
                                <input type="text" class="form-control" id="logo_name" name="logo_name" placeholder="Enter logo name">
                            </div>
                            <div class="form-group">
                                <input type="file" class="form-control" id="logo_file" name="logo_file">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-default" id="save_logo" data-loading-text="Saving logo ...">Save</button>
                            <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>';

        return $html;
    }

    public function hookHeader()
    {
        /*if(Configuration::get('PAYLIKE_STATUS') == 'enabled' && $this->context->controller->php_self == 'order') {
            $this->context->controller->addJs('https://sdk.paylike.io/3.js');
        }*/
    }

    public function hookPayment($params)
    {
        $language_code = Configuration::get('PAYLIKE_LANGUAGE_CODE');

        //ensure paylike key is set
        if (Configuration::get('PAYLIKE_TRANSACTION_MODE') == 'test') {
            if(!Configuration::get('PAYLIKE_TEST_PUBLIC_KEY') || !Configuration::get('PAYLIKE_TEST_SECRET_KEY')) {
                return false;
            }else {
                $PAYLIKE_PUBLIC_KEY = Configuration::get('PAYLIKE_TEST_PUBLIC_KEY');
                Configuration::updateValue('PAYLIKE_SECRET_KEY', Configuration::get('PAYLIKE_TEST_SECRET_KEY'));
            }
        }

        if (Configuration::get('PAYLIKE_TRANSACTION_MODE') == 'live') {
            if(!Configuration::get('PAYLIKE_LIVE_PUBLIC_KEY') || !Configuration::get('PAYLIKE_LIVE_SECRET_KEY')) {
                return false;
            }else {
                $PAYLIKE_PUBLIC_KEY = Configuration::get('PAYLIKE_LIVE_PUBLIC_KEY');
                Configuration::updateValue('PAYLIKE_SECRET_KEY', Configuration::get('PAYLIKE_LIVE_SECRET_KEY'));
            }
        }

        if (!Configuration::get('PAYLIKE_TEST_PUBLIC_KEY') && !Configuration::get('PAYLIKE_TEST_SECRET_KEY') && !Configuration::get('PAYLIKE_LIVE_PUBLIC_KEY') && !Configuration::get('PAYLIKE_LIVE_SECRET_KEY')) {
            return false;
        }

        $products = $params['cart']->getProducts();
        $products_array = array();
        $products_label = array();
        $p = 0;
        foreach ($products as $product)
        {
            $products_array[] = array(
                $this->l('ID') => $product['id_product'],
                $this->l('Name') => $product['name'],
                $this->l('Quantity') => $product['cart_quantity']
            );
            $products_label[$p] = $product['quantity'].'x '.$product['name'];
            $p++;
        }

        $payment_method_title = (!empty(Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE'))) ? Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_TITLE') : (!empty(Configuration::get('en_PAYLIKE_PAYMENT_METHOD_TITLE')) ? Configuration::get('en_PAYLIKE_PAYMENT_METHOD_TITLE') : '');
        $payment_method_desc = (!empty(Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC'))) ? Configuration::get($language_code.'_PAYLIKE_PAYMENT_METHOD_DESC') : (!empty(Configuration::get('en_PAYLIKE_PAYMENT_METHOD_DESC')) ? Configuration::get('en_PAYLIKE_PAYMENT_METHOD_DESC') : '');
        $popup_title = (!empty(Configuration::get($language_code.'_PAYLIKE_POPUP_TITLE'))) ? Configuration::get($language_code.'_PAYLIKE_POPUP_TITLE') : (!empty(Configuration::get('en_PAYLIKE_POPUP_TITLE')) ? Configuration::get('en_PAYLIKE_POPUP_TITLE') : '');

        if(Configuration::get('PAYLIKE_SHOW_POPUP_DESC') == 'yes') {
            $popup_description = (!empty(Configuration::get($language_code.'_PAYLIKE_POPUP_DESC'))) ? Configuration::get($language_code.'_PAYLIKE_POPUP_DESC') : (!empty(Configuration::get('en_PAYLIKE_POPUP_DESC')) ? Configuration::get('en_PAYLIKE_POPUP_DESC') : '');
        } else {
            $popup_description = implode(", & ", $products_label);
        }

        $amount = $params['cart']->getOrderTotal() * 100;//paid amounts with 100 to handle paylike's decimals
        $currency = new Currency((int)$params['cart']->id_currency);
        $currency_code = $currency->iso_code;
        $customer = new Customer((int)$params['cart']->id_customer);
        $name = $customer->firstname . ' ' . $customer->lastname;
        $email = $customer->email;
        $customer_address = new Address((int)($params['cart']->id_address_delivery));
        $telephone = !empty($customer_address->phone) ? $customer_address->phone : !empty($customer_address->phone_mobile) ? $customer_address->phone_mobile : '';
        $address = $customer_address->address1.', '.$customer_address->address2.', '.$customer_address->city.', '.$customer_address->country.' - '.$customer_address->postcode;
        $ip = Tools::getRemoteAddr();
        $locale = $this->context->language->iso_code;
        $platform_version = _PS_VERSION_;
        $ecommerce = 'prestashop';
        $module_version = $this->version;

        $redirect_url = $this->context->link->getModuleLink('paylikepayment', 'paymentreturn', [], true, (int)$this->context->language->id);

        if (Configuration::get('PS_REWRITING_SETTINGS') == 1)
            $redirect_url = Tools::strReplaceFirst('&', '?', $redirect_url);

        $this->context->smarty->assign(array(
            'PAYLIKE_PUBLIC_KEY'	            => $PAYLIKE_PUBLIC_KEY,
            'PS_SSL_ENABLED'		            => (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http'),
            'http_host'				            => Tools::getHttpHost(),
            'shop_name'				            => $this->context->shop->name,
            'payment_method_title'              => $payment_method_title,
            'payment_method_creditcard_logo'    => explode(',', Configuration::get('PAYLIKE_PAYMENT_METHOD_LOGO')),
            'payment_method_desc'               => $payment_method_desc,
            'paylike_status'                    => Configuration::get('PAYLIKE_STATUS'),
            'popup_title'			            => $popup_title,
            'popup_description'		            => $popup_description,
            'currency_code'			            => $currency_code,
            'amount'				            => $amount,
            'id_cart'				            => Tools::jsonEncode($params['cart']->id),
            'products'	                        => Tools::jsonEncode($products_array),
            'name'                              => $name,
            'email'                             => $email,
            'telephone'                         => $telephone,
            'address'                           => $address,
            'ip'                                => $ip,
            'locale'                            => $locale,
            'platform_version'                  => $platform_version,
            'ecommerce'                         => $ecommerce,
            'module_version'                    => $module_version,
            'redirect_url'			            => $redirect_url,
            'qry_str'				            => (Configuration::get('PS_REWRITING_SETTINGS')? '?' : '&'),
            'base_uri'				            => __PS_BASE_URI__,
            'this_path_paylike'                 => $this->_path,
        ));
        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    public function hookpaymentReturn($params)
    {
        if (!$this->active || !isset($params['objOrder']) || $params['objOrder']->module != $this->name)
            return false;

        if (isset($params['objOrder']) && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid) && isset($params['objOrder']->reference))
        {
            $this->smarty->assign(
                'paylike_order', array(
                    'id' => $params['objOrder']->id,
                    'reference' => $params['objOrder']->reference,
                    'valid' => $params['objOrder']->valid
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/payment-return.tpl');
        }
    }

    public function storeTransactionID($paylike_id_transaction, $order_id, $total, $captured='NO')
    {
        $query = 'INSERT INTO '._DB_PREFIX_.'paylike_admin (`paylike_tid`, `order_id`, `payed_amount`, `payed_at`, `captured`) VALUES ("'.pSQL($paylike_id_transaction).'", "'.pSQL($order_id).'", "'.pSQL($total).'" , NOW(), "'.pSQL($captured).'")';

        return Db::getInstance()->Execute($query);
    }

    public function updateTransactionID($paylike_id_transaction, $order_id, $fields=array())
    {
        if($paylike_id_transaction && $order_id && !empty($fields)) {
            $fieldsStr = '';
            $fieldCount = count($fields);
            $counter = 0;

            foreach($fields as $field => $value) {
                $counter++;
                $fieldsStr .= '`' . $field . '` = "'.pSQL($value) . '"';

                if($counter < $fieldCount) {
                    $fieldsStr .= ', ';
                }
            }

            $query = 'UPDATE ' . _DB_PREFIX_ . 'paylike_admin SET ' . $fieldsStr . ' WHERE `paylike_tid`="' . $paylike_id_transaction . '" AND `order_id`="' . $order_id . '"';
            return Db::getInstance()->Execute($query);

        } else
            return false;
    }

    public function hookDisplayAdminOrder($params)
    {
        $id_order = $params['id_order'];
        $order = new Order((int)$id_order);
        if ($order->module == $this->name)
        {
            $order_token = Tools::getAdminToken('AdminOrders'.(int)Tab::getIdFromClassName('AdminOrders').(int)$this->context->employee->id);
            $payliketransaction = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'paylike_admin WHERE order_id = '.(int)$id_order);
            $this->context->smarty->assign(array(
                'ps_version' => _PS_VERSION_,
                'id_order' => $id_order,
                'order_token' => $order_token,
                'payliketransaction' => $payliketransaction
            ));
            return $this->display(__FILE__, 'views/templates/hook/admin-order.tpl');
        }
    }

    public function hookBackOfficeHeader()
    {
        if (Tools::getIsset('vieworder') && Tools::getIsset('id_order') && Tools::getIsset('paylike_action')) {
            $paylike_action = Tools::getValue('paylike_action');
            $id_order = (int)Tools::getValue('id_order');
            $order = new Order((int)$id_order);
            $payliketransaction = Db::getInstance()->getRow('SELECT * FROM '._DB_PREFIX_.'paylike_admin WHERE order_id = '.(int)$id_order);
            $transactionid = $payliketransaction['paylike_tid'];
            Paylike\Client::setKey(Configuration::get('PAYLIKE_SECRET_KEY'));
            $fetch = Paylike\Transaction::fetch( $transactionid );

            switch ( $paylike_action ) {
                case "capture":
                    if($payliketransaction['captured'] == 'YES') {
                        $response = array(
                            'warning' => 1,
                            'message' => Tools::displayError('Transaction already Captured.'),
                        );

                    } else if (isset($payliketransaction)) {
                        $amount = (!empty($fetch['transaction']['pendingAmount'])) ? (int) $fetch['transaction']['pendingAmount'] : 0;
                        $currency = new Currency((int)$order->id_currency);
                        if($amount) {
                            //Capture transaction
                            $data = [
                                'currency' => $currency->iso_code,
                                'descriptor' => "Order #" . (int)$id_order,
                                'amount' => $amount,
                            ];
                            $capture = Paylike\Transaction::capture($transactionid, $data);

                            if (is_array($capture) && !empty($capture['error']) && $capture['error'] == 1) {
                                PrestaShopLogger::addLog($capture['message']);
                                $response = array(
                                    'error' => 1,
                                    'message' => Tools::displayError($capture['message']),
                                );

                            } else {
                                if (!empty($capture['transaction'])) {
                                    //Update order status
                                    $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'), $this->context->employee->id);

                                    //Update transaction details
                                    $fields = array(
                                        'captured' => 'YES',
                                    );
                                    $this->updateTransactionID($transactionid, (int)$id_order, $fields);

                                    //Set message
                                    $message = 'Trx ID: ' . $transactionid . '
                                    Authorized Amount: ' . ($capture['transaction']['amount'] / 100) . '
                                    Captured Amount: ' . ($capture['transaction']['capturedAmount'] / 100) . '
                                    Order time: ' . $capture['transaction']['created'] . '
                                    Currency code: ' . $capture['transaction']['currency'];

                                    $msg = new Message();
                                    $message = strip_tags($message, '<br>');
                                    if (Validate::isCleanHtml($message)) {
                                        $msg->message = $message;
                                        $msg->id_cart = (int)$order->id_cart;
                                        $msg->id_customer = (int)$order->id_customer;
                                        $msg->id_order = (int)$order->id;
                                        $msg->private = 1;
                                        $msg->add();
                                    }

                                    //Set response
                                    $response = array(
                                        'success' => 1,
                                        'message' => Tools::displayError('Transaction successfully Captured.'),
                                    );

                                } else {
                                    if(!empty($capture[0]['message'])) {
                                        $response = array(
                                            'warning' => 1,
                                            'message' => Tools::displayError($capture[0]['message']),
                                        );
                                    } else {
                                        $response = array(
                                            'error' => 1,
                                            'message' => Tools::displayError('Opps! An error occured while Capture.'),
                                        );
                                    }
                                }
                            }

                        } else {
                            $response = array(
                                'error' => 1,
                                'message' => Tools::displayError('Invalid amount to Capture.'),
                            );
                        }

                    } else {
                        $response = array(
                            'error' => 1,
                            'message' => Tools::displayError('Invalid Paylike Transaction.'),
                        );
                    }

                    break;

                case "refund":
                    if($payliketransaction['captured'] == 'NO') {
                        $response = array(
                            'warning' => 1,
                            'message' => Tools::displayError('You need to Captured Transaction prior to Refund.'),
                        );

                    }
                    else if (isset($payliketransaction)) {
                        $paylike_amount_to_refund = Tools::getValue('paylike_amount_to_refund');
                        $paylike_refund_reason = Tools::getValue('paylike_refund_reason');

                        if (!Validate::isPrice($paylike_amount_to_refund)) {
                            $response = array(
                                'error' => 1,
                                'message' => Tools::displayError('Invalid amount to Refund.'),
                            );

                        } else {
                            //Refund transaction
                            $amount = Tools::ps_round($paylike_amount_to_refund, 2) * 100;
                            $data = [
                                'descriptor' => $paylike_refund_reason,
                                'amount' => $amount,
                            ];
                            $refund = Paylike\Transaction::refund( $transactionid, $data );

                            if (is_array($refund) && !empty($refund['error']) && $refund['error'] == 1) {
                                PrestaShopLogger::addLog($refund['message']);
                                $response = array(
                                    'error' => 1,
                                    'message' => Tools::displayError($refund['message']),
                                );

                            } else {
                                if (!empty($refund['transaction'])) {
                                    //Update order status
                                    $order->setCurrentState((int)Configuration::get('PS_OS_REFUND'), $this->context->employee->id);

                                    //Update transaction details
                                    $fields = array(
                                        'refunded_amount' => $payliketransaction['refunded_amount'] + $paylike_amount_to_refund,
                                    );
                                    $this->updateTransactionID($transactionid, (int)$id_order, $fields);

                                    //Set message
                                    $message = 'Trx ID: ' . $transactionid . '
                                        Authorized Amount: ' . ($refund['transaction']['amount'] / 100) . '
                                        Refunded Amount: ' . ($refund['transaction']['refundedAmount'] / 100) . '
                                        Order time: ' . $refund['transaction']['created'] . '
                                        Currency code: ' . $refund['transaction']['currency'];

                                    $msg = new Message();
                                    $message = strip_tags($message, '<br>');
                                    if (Validate::isCleanHtml($message)) {
                                        $msg->message = $message;
                                        $msg->id_cart = (int)$order->id_cart;
                                        $msg->id_customer = (int)$order->id_customer;
                                        $msg->id_order = (int)$order->id;
                                        $msg->private = 1;
                                        $msg->add();
                                    }

                                    //Set response
                                    $response = array(
                                        'success' => 1,
                                        'message' => Tools::displayError('Transaction successfully Refunded.'),
                                    );

                                } else {
                                    if (!empty($refund[0]['message'])) {
                                        $response = array(
                                            'warning' => 1,
                                            'message' => Tools::displayError($refund[0]['message']),
                                        );
                                    } else {
                                        $response = array(
                                            'error' => 1,
                                            'message' => Tools::displayError('Opps! An error occured while Refund.'),
                                        );
                                    }
                                }
                            }
                        }

                    } else {
                        $response = array(
                            'error' => 1,
                            'message' => Tools::displayError('Invalid Paylike Transaction.'),
                        );
                    }

                    break;

                case "void":
                    if($payliketransaction['captured'] == 'YES') {
                        $response = array(
                            'warning' => 1,
                            'message' => Tools::displayError('You can\'t Void transaction now . It\'s already Captured, try to Refund.'),
                        );

                    } else if (isset($payliketransaction)) {
                        //Void transaction
                        $amount = (int) $fetch['transaction']['amount'] - $fetch['transaction']['refundedAmount'];
                        $data = [
                            'amount' => $amount,
                        ];
                        $void = Paylike\Transaction::void( $transactionid, $data );

                        if (is_array($void) && !empty($void['error']) && $void['error'] == 1) {
                            PrestaShopLogger::addLog($void['message']);
                            $response = array(
                                'error' => 1,
                                'message' => Tools::displayError($void['message']),
                            );

                        } else {
                            if (!empty($void['transaction'])) {
                                //Update order status
                                $order->setCurrentState((int)Configuration::get('PS_OS_CANCEL'), $this->context->employee->id);

                                //Set message
                                $message = 'Trx ID: ' . $transactionid . '
                                        Authorized Amount: ' . ($void['transaction']['amount'] / 100) . '
                                        Refunded Amount: ' . ($void['transaction']['refundedAmount'] / 100) . '
                                        Order time: ' . $void['transaction']['created'] . '
                                        Currency code: ' . $void['transaction']['currency'];

                                $msg = new Message();
                                $message = strip_tags($message, '<br>');
                                if (Validate::isCleanHtml($message)) {
                                    $msg->message = $message;
                                    $msg->id_cart = (int)$order->id_cart;
                                    $msg->id_customer = (int)$order->id_customer;
                                    $msg->id_order = (int)$order->id;
                                    $msg->private = 1;
                                    $msg->add();
                                }

                                //Set response
                                $response = array(
                                    'success' => 1,
                                    'message' => Tools::displayError('Transaction successfully Voided.'),
                                );

                            } else {
                                if (!empty($void[0]['message'])) {
                                    $response = array(
                                        'warning' => 1,
                                        'message' => Tools::displayError($void[0]['message']),
                                    );
                                } else {
                                    $response = array(
                                        'error' => 1,
                                        'message' => Tools::displayError('Opps! An error occured while refund.'),
                                    );
                                }
                            }
                        }

                    } else {
                        $response = array(
                            'error' => 1,
                            'message' => Tools::displayError('Invalid paylike transaction.'),
                        );
                    }

                    break;
            }

            die(Tools::jsonEncode($response));
        }

        if (Tools::getIsset('upload_logo')) {
            $logo_name = Tools::getValue('logo_name');

            if(empty($logo_name)) {
                $response = array(
                    'status' => 0,
                    'message' => 'Please give logo name.'
                );
                die(Tools::jsonEncode($response));
            }

            $logo_slug = strtolower(str_replace(' ','-',$logo_name));
            $sql = new DbQuery();
            $sql->select('*');
            $sql->from('paylike_logos', 'PL');
            $sql->where('PL.slug = "'. $logo_slug .'"');
            $logos = Db::getInstance()->executes($sql);
            if(!empty($logos)) {
                $response = array(
                    'status' => 0,
                    'message' => 'This name already exists.'
                );
                die(Tools::jsonEncode($response));
            }

            if (!empty($_FILES['logo_file']['name'])) {
                $target_dir = __DIR__.'/logos/';
                $name = basename($_FILES['logo_file']["name"]);
                $path_parts = pathinfo($name);
                $extension = $path_parts['extension'];
                $file_name = $logo_slug . '.' . $extension;
                $target_file = $target_dir . basename($file_name);
                $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);

                /*$check = getimagesize($_FILES['logo_file']["tmp_name"]);
                if($check === false) {
                    $response = array(
                        'status' => 0,
                        'message' => 'File is not an image. Please upload JPG, JPEG, PNG or GIF file.'
                    );
                    die(Tools::jsonEncode($response));
                }*/

                // Check if file already exists
                if (file_exists($target_file)) {
                    $response = array(
                        'status' => 0,
                        'message' => 'Sorry, file already exists.'
                    );
                    die(Tools::jsonEncode($response));
                }

                // Allow certain file formats
                if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
                    && $imageFileType != "gif" && $imageFileType != "svg" ) {
                    $response = array(
                        'status' => 0,
                        'message' => 'Sorry, only JPG, JPEG, PNG, GIF & SVG files are allowed.'
                    );
                    die(Tools::jsonEncode($response));
                }

                if (move_uploaded_file($_FILES['logo_file']["tmp_name"], $target_file)) {
                    $query = 'INSERT INTO '._DB_PREFIX_.'paylike_logos (`name`, `slug`, `file_name`, `default_logo`, `created_at`) VALUES ("'.pSQL($logo_name).'", "'.pSQL($logo_slug).'", "'.pSQL($file_name).'", 0, NOW())';
                    if( Db::getInstance()->Execute($query) ) {
                        $response = array(
                            'status' => 1,
                            'message' => "The file " . basename($file_name) . " has been uploaded."
                        );
                        //Configuration::updateValue('PAYLIKE_PAYMENT_METHOD_CREDITCARD_LOGO', basename($file_name));
                        die(Tools::jsonEncode($response));
                    } else {
                        unlink($target_file);
                        $response = array(
                            'status' => 0,
                            'message' => "Oops! An error occured while save logo."
                        );
                        die(Tools::jsonEncode($response));
                    }

                } else {
                    $response = array(
                        'status' => 0,
                        'message' => 'Sorry, there was an error uploading your file.'
                    );
                    die(Tools::jsonEncode($response));
                }
            } else {
                $response = array(
                    'status' => 0,
                    'message' => 'Please select a file for upload.'
                );
                die(Tools::jsonEncode($response));
            }
        }

        if (Tools::getIsset('change_language')) {
            $language_code = (!empty(Tools::getvalue('lang_code'))) ? Tools::getvalue('lang_code') : Configuration::get('PAYLIKE_LANGUAGE_CODE');;
            Configuration::updateValue('PAYLIKE_LANGUAGE_CODE', $language_code);
            $token = Tools::getAdminToken('AdminModules'.(int)Tab::getIdFromClassName('AdminModules').(int)$this->context->employee->id);
            $link = $this->context->link->getAdminLink('AdminModules').'&token='.$token.'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
            Tools::redirectAdmin($link);
        }
    }

}