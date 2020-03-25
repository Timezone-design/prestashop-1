<?php

if (!defined('_PS_VERSION_')) {
    exit;
}
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
use Gett\MyParcel\Sdk\src\Services\WebhookService;
use Gett\MyParcel\Sdk\src\Model\Webhook\Subscription;

class MyParcel extends CarrierModule
{
    use \Gett\MyParcel\Module\Configuration\ApiForm;
    use \Gett\MyParcel\Module\Configuration\GeneralForm;
    use \Gett\MyParcel\Module\Configuration\LabelForm;
    use \Gett\MyParcel\Module\Configuration\OrderForm;
    use \Gett\MyParcel\Module\Configuration\CustomsForm;

    const POSTNL_DEFAULT_CARRIER = 'MYPARCEL_DEFAULT_CARRIER';

    const MENU_API_SETTINGS = 0;
    const MENU_GENERAL_SETTINGS = 1;
    const MENU_LABEL_SETTINGS = 2;
    const MENU_ORDER_SETTINGS = 3;
    const MENU_CUSTOMS_SETTINGS = 4;

    protected $baseUrl;
    /** @var string $baseUrlWithoutToken */
    protected $baseUrlWithoutToken;

    public function __construct()
    {;
        $this->name = 'MyParcel';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Gett';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        if (!empty(Context::getContext()->employee->id)) {
            $this->baseUrlWithoutToken = $this->getAdminLink(
                'AdminModules',
                false,
                array(
                    'configure'   => $this->name,
                    'tab_module'  => $this->tab,
                    'module_name' => $this->name,
                )
            );
            $this->baseUrl = $this->getAdminLink(
                'AdminModules',
                true,
                array(
                    'configure'   => $this->name,
                    'tab_module'  => $this->tab,
                    'module_name' => $this->name,
                )
            );
        }
        $this->displayName = $this->l('MyParcel');
        $this->description = $this->l('PrestaShop module to intergratie with MyParcel NL and MyParcel BE');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $this->addCarrier('PostNL', static::POSTNL_DEFAULT_CARRIER);
//        $this->addCarrier('PostNL Brievenbuspakje', static::POSTNL_DEFAULT_MAILBOX_PACKAGE_CARRIER);
//        $this->addCarrier('PostNL Briefpost', static::POSTNL_DEFAULT_DIGITAL_STAMP_CARRIER);

        Configuration::updateValue('MYPARCEL_LIVE_MODE', false);

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader');
    }

    protected function addCarrier($name, $key = self::POSTNL_DEFAULT_CARRIER)
    {
        $carrier = Carrier::getCarrierByReference(Configuration::get($key));
        if (Validate::isLoadedObject($carrier)) {
            return false; // Already added to DB
        }

        $carrier = new Carrier();

        $carrier->name = $name;
        $carrier->delay = array();
        $carrier->is_module = true;
        $carrier->active = 0;
        $carrier->need_range = 1;
        $carrier->shipping_external = true;
        $carrier->range_behavior = 1;
        $carrier->external_module_name = $this->name;
        $carrier->shipping_handling = false;
        $carrier->shipping_method = 2;

        foreach (Language::getLanguages() as $lang) {
            $idLang = (int) $lang['id_lang'];
            $carrier->delay[$idLang] = '-';
        }

        if ($carrier->add()) {
            /*
             * Use the Carrier ID as id_reference! Only the `id` prop has been set at this time and since it is
             * the first time this carrier is used the Carrier ID = `id_reference`
             */
            $this->addGroups($carrier);
            $this->addZones($carrier);
            $this->addPriceRange($carrier);
            Db::getInstance()->update(
                'delivery',
                array(
                    'price' => $key == static::POSTNL_DEFAULT_CARRIER ? (4.99 / 1.21) : (3.50 / 1.21),
                ),
                '`id_carrier` = '.(int) $carrier->id
            );

            $carrier->setTaxRulesGroup((int) TaxRulesGroup::getIdByName('NL Standard Rate (21%)'), true);

            @copy(
                dirname(__FILE__).'/views/img/postnl-thumb.jpg',
                _PS_SHIP_IMG_DIR_.DIRECTORY_SEPARATOR.(int) $carrier->id.'.jpg'
            );

            Configuration::updateGlobalValue($key, (int) $carrier->id);
            $deliverySetting = new MyParcelCarrierDeliverySetting();
            $deliverySetting->id_reference = $carrier->id;

            $deliverySetting->monday_cutoff = '15:30:00';
            $deliverySetting->tuesday_cutoff = '15:30:00';
            $deliverySetting->wednesday_cutoff = '15:30:00';
            $deliverySetting->thursday_cutoff = '15:30:00';
            $deliverySetting->friday_cutoff = '15:30:00';
            $deliverySetting->saturday_cutoff = '15:30:00';
            $deliverySetting->sunday_cutoff = '15:30:00';
            $deliverySetting->timeframe_days = 1;
            $deliverySetting->daytime = true;
            $deliverySetting->morning = false;
            $deliverySetting->morning_pickup = false;
            $deliverySetting->evening = false;
            $deliverySetting->signed = false;
            $deliverySetting->recipient_only = false;
            $deliverySetting->signed_recipient_only = false;
            $deliverySetting->dropoff_delay = 0;
            $deliverySetting->id_shop = $this->getShopId();
            $deliverySetting->morning_fee_tax_incl = 0;
            $deliverySetting->morning_pickup_fee_tax_incl = 0;
            $deliverySetting->default_fee_tax_incl = 0;
            $deliverySetting->evening_fee_tax_incl = 0;
            $deliverySetting->signed_fee_tax_incl = 0;
            $deliverySetting->recipient_only_fee_tax_incl = 0;
            $deliverySetting->signed_recipient_only_fee_tax_incl = 0;
            $deliverySetting->monday_enabled = false;
            $deliverySetting->tuesday_enabled = false;
            $deliverySetting->wednesday_enabled = false;
            $deliverySetting->thursday_enabled = false;
            $deliverySetting->friday_enabled = false;
            $deliverySetting->saturday_enabled = false;
            $deliverySetting->sunday_enabled = false;
            $deliverySetting->pickup = false;
            $deliverySetting->delivery = false;
            $deliverySetting->mailbox_package = false;
            $deliverySetting->digital_stamp = false;
            if ($key === static::POSTNL_DEFAULT_CARRIER) {
                $deliverySetting->monday_enabled = true;
                $deliverySetting->tuesday_enabled = true;
                $deliverySetting->wednesday_enabled = true;
                $deliverySetting->thursday_enabled = true;
                $deliverySetting->friday_enabled = true;
                $deliverySetting->saturday_enabled = false;
                $deliverySetting->sunday_enabled = false;
                $deliverySetting->delivery = true;
                $deliverySetting->pickup = true;
            } elseif ($key === static::POSTNL_DEFAULT_MAILBOX_PACKAGE_CARRIER) {
                $deliverySetting->mailbox_package = true;
            } else {
                $deliverySetting->digital_stamp = true;
            }
            try {
                $deliverySetting->add();
            } catch (PrestaShopException $e) {
                Logger::addLog(
                    sprintf(
                        "{$this->l('MyParcel: unable to save carrier settings for carrier with reference %d')}: {$e->getMessage()}",
                        $carrier->id
                    )
                );
            }

            return $carrier;
        }

        return false;
    }

    public function uninstall()
    {
        Configuration::deleteByName('MYPARCEL_LIVE_MODE');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->context->smarty->assign(array(
            'menutabs' => $this->initNavigation(),
            'ajaxUrl'  => $this->baseUrlWithoutToken,
        ));

        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitMyParcelstatus')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $output = '';
        $output .= $this->display(__FILE__, 'views/templates/admin/navbar.tpl');

        switch (Tools::getValue('menu')) {
            case static::MENU_API_SETTINGS:
                $this->menu = static::MENU_API_SETTINGS;

                return $output.$this->displayApiSettingsForm();
            case static::MENU_GENERAL_SETTINGS:
                $this->menu = static::MENU_GENERAL_SETTINGS;
                return $output.$this->displayGeneralSettingsForm();
            case static::MENU_LABEL_SETTINGS:
                $this->menu = static::MENU_LABEL_SETTINGS;
            return $output.$this->displayLabelSettingsForm();
            case static::MENU_ORDER_SETTINGS:
                $this->menu = static::MENU_ORDER_SETTINGS;
            return $output.$this->displayOrderSettingsForm();
            case static::MENU_CUSTOMS_SETTINGS:
                $this->menu = static::MENU_CUSTOMS_SETTINGS;
            return $output.$this->displayCustomsSettingsForm();
            default:
                $this->menu = static::MENU_GENERAL_SETTINGS;

                #return $output.$this->displayMainSettingsPage();
        }

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMyParcelModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'MYPARCEL_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'MYPARCEL_ACCOUNT_EMAIL',
                        'label' => $this->l('Email'),
                    ),
                    array(
                        'type' => 'password',
                        'name' => 'MYPARCEL_ACCOUNT_PASSWORD',
                        'label' => $this->l('Password'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        switch (Tools::getValue('menu')) {
            case static::MENU_API_SETTINGS:
                $this->postProcessApiSettingsPage();
                break;
            case static::MENU_GENERAL_SETTINGS:
                $this->postProcessGeneralSettingsPage();
                break;
            case static::MENU_LABEL_SETTINGS:
                $this->postProcessLabelSettingsPage();
                break;
            case static::MENU_ORDER_SETTINGS:
                $this->postProcessOrderSettingsPage();
                break;
            case static::MENU_CUSTOMS_SETTINGS:
                $this->postProcessCustomsSettingsPage();
                break;
            default:
        }
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        if (Context::getContext()->customer->logged == true)
        {
            $id_address_delivery = Context::getContext()->cart->id_address_delivery;
            $address = new Address($id_address_delivery);

            /**
             * Send the details through the API
             * Return the price sent by the API
             */
            return 10;
        }

        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params)
    {
        return true;
    }

    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group)
            $groups_ids[] = $group['id_group'];

        $carrier->setGroups($groups_ids);
    }

    protected function addRanges($carrier)
    {
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = '0';
        $range_price->delimiter2 = '10000';
        $range_price->add();

        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = '0';
        $range_weight->delimiter2 = '10000';
        $range_weight->add();
    }

    protected function addZones($carrier)
    {
        $zones = Zone::getZones();

        foreach ($zones as $zone)
            $carrier->addZone($zone['id_zone']);
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * Initialize navigation
     *
     * @return array Menu items
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    protected function initNavigation()
    {
        $menu = array(
            'main'            => array(
                'short'  => $this->l('API'),
                'desc'   => $this->l('API settings'),
                'href'   => static::appendQueryToUrl($this->baseUrl, array('menu' => (string) static::MENU_API_SETTINGS)),
                'active' => false,
                'icon'   => 'icon-gears',
            ),
            'defaultsettings' => array(
                'short'  => $this->l('General settings'),
                'desc'   => $this->l('General module settings'),
                'href'   => static::appendQueryToUrl($this->baseUrl, array('menu' => (string) static::MENU_GENERAL_SETTINGS)),
                'active' => false,
                'icon'   => 'icon-truck',
            ),
            'labeloptions' => array(
                'short'  => $this->l('Label options'),
                'desc'   => $this->l('Label options'),
                'href'   => static::appendQueryToUrl($this->baseUrl, array('menu' => (string) static::MENU_LABEL_SETTINGS)),
                'active' => false,
                'icon'   => 'icon-shopping-cart',
            ),
            'orderoptions' => array(
                'short'  => $this->l('Order options'),
                'desc'   => $this->l('Order options'),
                'href'   => static::appendQueryToUrl($this->baseUrl, array('menu' => (string) static::MENU_ORDER_SETTINGS)),
                'active' => false,
                'icon'   => 'icon-shopping-cart',
            ),
            'customsoptions' => array(
                'short'  => $this->l('Customs options'),
                'desc'   => $this->l('Customs options'),
                'href'   => static::appendQueryToUrl($this->baseUrl, array('menu' => (string) static::MENU_CUSTOMS_SETTINGS)),
                'active' => false,
                'icon'   => 'icon-shopping-cart',
            ),
        );

        switch (Tools::getValue('menu')) {
            case static::MENU_API_SETTINGS:
                $this->menu = static::MENU_API_SETTINGS;
                $menu['main']['active'] = true;
                break;
            case static::MENU_GENERAL_SETTINGS:
                $this->menu = static::MENU_GENERAL_SETTINGS;
                $menu['defaultsettings']['active'] = true;
                break;
            case static::MENU_LABEL_SETTINGS:
                $this->menu = static::MENU_LABEL_SETTINGS;
                $menu['labeloptions']['active'] = true;
                break;
            case static::MENU_ORDER_SETTINGS:
                $this->menu = static::MENU_ORDER_SETTINGS;
                $menu['orderoptions']['active'] = true;
                break;
            case static::MENU_CUSTOMS_SETTINGS:
                $this->menu = static::MENU_CUSTOMS_SETTINGS;
                $menu['customsoptions']['active'] = true;
                break;
            default:
                $this->menu = static::MENU_API_SETTINGS;
                $menu['main']['active'] = true;
                break;
        }

        return $menu;
    }

    /**
     * Append query array to url string
     *
     * @param string $urlString
     * @param array  $query
     *
     * @return string
     *
     * @since 2.3.0
     */
    public static function appendQueryToUrl($urlString, $query = array())
    {
        $url = mypa_parse_url($urlString);
        $url['query'] = isset($url['query']) ? $url['query'] : '';
        parse_str($url['query'], $oldQuery);
        if (version_compare(phpversion(), '5.4.0', '>=')) {
            $url['query'] = http_build_query($oldQuery + $query, PHP_QUERY_RFC1738);
        } else {
            $url['query'] = http_build_query($oldQuery + $query);
        }


        return mypa_stringify_url($url);
    }

    /**
     * Get admin link (PS 1.5/1.6 + 1.7 hybrid)
     *
     * @param string $controller
     * @param bool   $withToken
     * @param array  $params
     *
     * @return string
     *
     * @throws PrestaShopException
     *
     * @since 2.3.0
     */
    public function getAdminLink($controller, $withToken = true, $params = array())
    {
        $url = mypa_parse_url($this->context->link->getAdminLink($controller, $withToken));
        $url['query'] = isset($url['query']) ? $url['query'] : '';
        parse_str($url['query'], $query);
        if (version_compare(phpversion(), '5.4.0', '>=')) {
            $url['query'] = http_build_query($query + $params, PHP_QUERY_RFC1738);
        } else {
            $url['query'] = http_build_query($query + $params);
        }


        return mypa_stringify_url($url);
    }
}
