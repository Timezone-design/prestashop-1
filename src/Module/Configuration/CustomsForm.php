<?php

namespace Gett\MyParcel\Module\Configuration;

trait CustomsForm
{
    protected function displayCustomsSettingsForm()
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
                'menu'        => static::MENU_CUSTOMS_SETTINGS,
            )
        );
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getCustomsSettingsFormValues()
        );

        $forms = array(
            $this->getCustomSettingsForm()
        );

        return $helper->generateForm($forms);
    }

    protected function getCustomSettingsForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Custom settings'),
                    'icon'  => 'icon-shield',
                ),
                'input'  => array(
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Default customs form'),
                        'name'    => 'MY_PARCEL_CUSTOMS_FORM',
                        'options' => array(
                            'query' => [
                                ['id' => 'No', 'name' => 'No'],
                                ['id' => 'Add', 'name' => 'ADD'],
                                ['id' => 'Skip', 'name' => 'Skip'],
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type'    => 'text',
                        'label'   => $this->l("Default customs code"),
                        'name'    => 'MY_PARCEL_DEFAULT_CUSTOMS_CODE',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Default customs origin'),
                        'name' => 'MY_PARCEL_DEFAULT_CUSTOMS_ORIGIN',
                        'options' => array(
                            'query' => \Country::getCountries($this->context->language->id),
                            'id' => 'id_country',
                            'name' => 'name',
                        ),
                    ],
                    [
                        'type'    => 'text',
                        'label'   => $this->l("Default customs age check"),
                        'name'    => 'MY_PARCEL_DEFAULT_CUSTOMS_AGE_CHECK',
                    ],
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getCustomsSettingsFormValues()
    {
        return array(
            "MY_PARCEL_CUSTOMS_FORM"        => \Configuration::get("MY_PARCEL_CUSTOMS_FORM"),
            "MY_PARCEL_DEFAULT_CUSTOMS_CODE"        => \Configuration::get("MY_PARCEL_DEFAULT_CUSTOMS_CODE"),
            "MY_PARCEL_DEFAULT_CUSTOMS_ORIGIN"        => \Configuration::get("MY_PARCEL_DEFAULT_CUSTOMS_ORIGIN"),
            "MY_PARCEL_DEFAULT_CUSTOMS_AGE_CHECK"        => \Configuration::get("MY_PARCEL_DEFAULT_CUSTOMS_AGE_CHECK"),
        );
    }

    protected function postProcessCustomsSettingsPage()
    {
        foreach (array_keys($this->getCustomsSettingsFormValues()) as $key) {
            if (\Tools::isSubmit($key)) {
                \Configuration::updateValue($key, \Tools::getValue($key));
            }
        }
    }
}
