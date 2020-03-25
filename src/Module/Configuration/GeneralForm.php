<?php

namespace Gett\MyParcel\Module\Configuration;

trait GeneralForm
{

    protected function displayGeneralSettingsForm()
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
                'menu'        => static::MENU_GENERAL_SETTINGS,
            )
        );
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value'                   => $this->getGeneralSettingsFormValues()
        );
        $forms = array(
            $this->getGeneralSettingsForm()
        );

        return $helper->generateForm($forms);
    }

    protected function getGeneralSettingsForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('General settings'),
                    'icon'  => 'icon-shield',
                ),
                'input'  => array(
                    [
                        'type' => 'switch',
                        'label' => $this->l('Share customer email with MyParcel'),
                        'name' => 'MY_PARCEL_SHARE_CUSTOMER_EMAIL',
                        'required' => false,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Disabled'),
                            ),
                        ),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Share customer phone with MyParcel'),
                        'name' => 'MY_PARCEL_SHARE_CUSTOMER_PHONE',
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
    }

    protected function postProcessGeneralSettingsPage()
    {
        foreach (array_keys($this->getGeneralSettingsFormValues()) as $key) {
            if (\Tools::isSubmit($key)) {
                \Configuration::updateValue($key, \Tools::getValue($key));
            }
        }
    }

    protected function getGeneralSettingsFormValues()
    {
        return array(
            "MY_PARCEL_SHARE_CUSTOMER_EMAIL"        => \Configuration::get("MY_PARCEL_SHARE_CUSTOMER_EMAIL"),
            "MY_PARCEL_SHARE_CUSTOMER_PHONE"        => \Configuration::get("MY_PARCEL_SHARE_CUSTOMER_PHONE")
        );
    }
}
