<?php

namespace Gett\MyParcel\Module\Configuration;

trait OrderForm
{
    protected function displayOrderSettingsForm()
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
                'menu'        => static::MENU_ORDER_SETTINGS,
            )
        );
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getOrderSettingsFormValues()
        );
        $forms = array(
            $this->getOrderSettingsForm()
        );

        return $helper->generateForm($forms);
    }

    protected function getOrderSettingsForm()
    {
        $order_states = [["id_order_state" => 0, "name" => "Off"]] + \OrderState::getOrderStates((int)$this->context->language->id);
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Order settings'),
                    'icon'  => 'icon-shield',
                ),
                'input'  => array(
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Order status when label created'),
                        'name'    => 'MY_PARCEL_LABEL_CREATED_ORDER_STATUS',
                        'options' => array(
                            'query' => $order_states,
                            'id'    => 'id_order_state',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Order status when label scanned'),
                        'name'    => 'MY_PARCEL_LABEL_SCANNED_ORDER_STATUS',
                        'default_value' => "0",
                        'options' => array(
                            'query' => $order_states,
                            'id'    => 'id_order_state',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Order status when delivered'),
                        'name'    => 'MY_PARCEL_DELIVERED_ORDER_STATUS',
                        'options' => array(
                            'query' => $order_states,
                            'id'    => 'id_order_state',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Ignore order statuses'),
                        'name'    => 'MY_PARCEL_IGNORE_ORDER_STATUS',
                        'options' => array(
                            'query' => $order_states,
                            'id'    => 'id_order_state',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Order status mail'),
                        'name' => 'MY_PARCEL_STATUS_CHANGE_MAIL',
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
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Send notification after'),
                        'name'    => 'MY_PARCEL_ORDER_NOTIFICATION_AFTER',
                        'options' => array(
                            'query' => [
                                ["id" => 'first_scan', 'name' => $this->l('Label has passed first scan')],
                                ["id" => 'printed', 'name' => $this->l('Label is printed')]
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Order status mail'),
                        'name' => 'MY_PARCEL_SENT_ORDER_STATE_FOR_DIGITAL_STAMPS',
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

    protected function getOrderSettingsFormValues()
    {
        return array(
            "MY_PARCEL_LABEL_CREATED_ORDER_STATUS"        => \Configuration::get("MY_PARCEL_LABEL_CREATED_ORDER_STATUS"),
            "MY_PARCEL_LABEL_SCANNED_ORDER_STATUS"        => \Configuration::get("MY_PARCEL_LABEL_SCANNED_ORDER_STATUS"),
            "MY_PARCEL_DELIVERED_ORDER_STATUS"        => \Configuration::get("MY_PARCEL_DELIVERED_ORDER_STATUS"),
            "MY_PARCEL_IGNORE_ORDER_STATUS"        => \Configuration::get("MY_PARCEL_IGNORE_ORDER_STATUS"),
            "MY_PARCEL_STATUS_CHANGE_MAIL"        => \Configuration::get("MY_PARCEL_STATUS_CHANGE_MAIL"),
            "MY_PARCEL_ORDER_NOTIFICATION_AFTER"        => \Configuration::get("MY_PARCEL_ORDER_NOTIFICATION_AFTER"),
            "MY_PARCEL_SENT_ORDER_STATE_FOR_DIGITAL_STAMPS"        => \Configuration::get("MY_PARCEL_SENT_ORDER_STATE_FOR_DIGITAL_STAMPS"),

        );
    }

    protected function postProcessOrderSettingsPage()
    {
        foreach (array_keys($this->getOrderSettingsFormValues()) as $key) {
            if (\Tools::isSubmit($key)) {
                \Configuration::updateValue($key, \Tools::getValue($key));
            }
        }
    }
}
