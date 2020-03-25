<?php

namespace Gett\MyParcel\Module\Configuration;

use Gett\MyParcel\Sdk\src\Services\WebhookService;

trait ApiForm
{
    protected function displayApiSettingsForm()
{
    $helper = new \HelperForm();

    $helper->show_toolbar = false;
    $helper->table = $this->table;
    $helper->module = $this;
    $helper->default_form_language = $this->context->language->id;
    $helper->allow_employee_form_lang = \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

    $helper->identifier = $this->identifier;
    $helper->submit_action = 'submit'.$this->name.'status';
    $helper->currentIndex = $this->getAdminLink(
        'AdminModules',
        false,
        array(
            'configure'   => $this->name,
            'tab_module'  => $this->tab,
            'module_name' => $this->name,
            'menu'        => static::MENU_API_SETTINGS,
        )
    );
    $helper->token = \Tools::getAdminTokenLite('AdminModules');
    $helper->tpl_vars = array(
        'fields_value'                   => $this->getApiSettingsFormValues()
    );
    $forms = array(
        $this->getApiSettingsForm()
    );

    return $helper->generateForm($forms);
}

    protected function getApiSettingsForm()
    {
        $form =  array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Api settings'),
                    'icon'  => 'icon-shield',
                ),
                'input'  => array(
                    [
                        'type'    => 'text',
                        'label'   => $this->l("Your API key"),
                        'name'    => 'MY_PARCEL_API_KEY',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Api logging'),
                        'name' => 'MY_PARCEL_API_LOGGING',
                        'required' => false,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ]
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        if (\Tools::getValue("MY_PARCEL_WEBHOOK_ID")) {
            $form['buttons'] = [
                'submit' => array(
                    'title' => $this->l('Reset hook'),
                    'class' => 'btn btn-default  pull-left',
                    'type'  => 'submit',
                    'name'  => 'resetHook',
                    'icon'  => 'process-icon-save',
                ),
                'save-and-stay' => array(
                    'title' => $this->l('Delete hook'),
                    'name' => 'deleteHook',
                    'type' => 'submit',
                    'class' => 'btn btn-default pull-left',
                    'icon' => 'process-icon-save',
                ),
            ];
        }

        return $form;
    }

    protected function getApiSettingsFormValues()
    {
        return array(
            "MY_PARCEL_API_KEY"        => \Configuration::get("MY_PARCEL_API_KEY"),
            "MY_PARCEL_API_LOGGING"        => \Configuration::get("MY_PARCEL_API_LOGGING"),
            "MY_PARCEL_WEB_HOOK"        => \Configuration::get("MY_PARCEL_WEB_HOOK"),
        );
    }

    protected function postProcessApiSettingsPage()
    {
        if ((\Tools::isSubmit("MY_PARCEL_API_KEY") && \Tools::getValue("MY_PARCEL_API_KEY") != \Configuration::get("MY_PARCEL_API_KEY")) || \Tools::isSubmit("resetHook")) {
            $service = new WebhookService(\Tools::getValue("MY_PARCEL_API_KEY"));
            $result = $service->addSubscription(
            new Subscription(
                Subscription::SHIPMENT_STATUS_CHANGE_HOOK_NAME,
                rtrim((Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://').Tools::getShopDomainSsl().__PS_BASE_URI__, '/')."/index.php?fc=module&module={$this->name}&controller=hook"
            ));

            if (isset($result['data']['ids'][0]['id'])) {
                \Configuration::updateValue("MY_PARCEL_WEBHOOK_ID", \Tools::getValue($result['data']['ids'][0]['id']));
            }
        }
        if (\Tools::isSubmit("deleteHook")) {
            $service = new WebhookService(\Tools::getValue("MY_PARCEL_API_KEY"));
            $service->deleteSubscription(\Tools::getValue("MY_PARCEL_WEBHOOK_ID"));
            \Configuration::updateValue("MY_PARCEL_WEBHOOK_ID", "");
        }

        foreach (array_keys($this->getApiSettingsFormValues()) as $key) {
            if (\Tools::isSubmit($key)) {
                \Configuration::updateValue($key, \Tools::getValue($key));
            }
        }
    }
}
