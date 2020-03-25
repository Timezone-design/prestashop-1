<?php

namespace Gett\MyParcel\Module\Configuration;

trait LabelForm
{
    protected function getLabelSettingsForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Label settings'),
                    'icon'  => 'icon-shield',
                ),
                'input'  => array(
                    [
                        'type'    => 'text',
                        'label'   => $this->l("Label description"),
                        'name'    => 'MY_PARCEL_LABEL_DESCRIPTION',
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Default label size'),
                        'name'    => 'MY_PARCEL_LABEL_SIZE',
                        'options' => array(
                            'query' => [
                                ['id' => 'A4', 'name' => 'A4'],
                                ['id' => 'A6', 'name' => 'A6'],
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Default label position'),
                        'name'    => 'MY_PARCEL_LABEL_POSITION',
                        'options' => array(
                            'query' => [
                                ['id' => '1', 'name' => $this->l('Top left')],
                                ['id' => '3', 'name' => $this->l('Top right')],
                                ['id' => '2', 'name' => $this->l('Bottom left')],
                                ['id' => '4', 'name' => $this->l('Bottom right')],
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type'    => 'select',
                        'label'   => $this->l('Open or download label'),
                        'name'    => 'MY_PARCEL_LABEL_OPEN_DOWNLOAD',
                        'options' => array(
                            'query' => [
                                ['id' => 'open', 'name' => $this->l('Open')],
                                ['id' => 'download', 'name' => $this->l('Download')]
                            ],
                            'id'    => 'id',
                            'name'  => 'name',
                        )
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Prompt for label position'),
                        'name' => 'MY_PARCEL_LABEL_PROMPT_POSITION',
                        'required' => false,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
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

    protected function displayLabelSettingsForm()
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
                'menu'        => static::MENU_LABEL_SETTINGS,
            )
        );
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value'                   => $this->getLabelSettingsFormValues()
        );
        $forms = array(
            $this->getLabelSettingsForm()
        );

        return $helper->generateForm($forms);
    }

    protected function getLabelSettingsFormValues()
    {
        return array(
            "MY_PARCEL_LABEL_DESCRIPTION"        => \Configuration::get("MY_PARCEL_LABEL_DESCRIPTION"),
            "MY_PARCEL_LABEL_SIZE"        => \Configuration::get("MY_PARCEL_LABEL_SIZE"),
            "MY_PARCEL_LABEL_POSITION"        => \Configuration::get("MY_PARCEL_LABEL_POSITION"),
            "MY_PARCEL_LABEL_OPEN_DOWNLOAD"        => \Configuration::get("MY_PARCEL_LABEL_OPEN_DOWNLOAD"),
            "MY_PARCEL_LABEL_PROMPT_POSITION"        => \Configuration::get("MY_PARCEL_LABEL_PROMPT_POSITION"),
        );
    }

    protected function postProcessLabelSettingsPage()
    {
        foreach (array_keys($this->getLabelSettingsFormValues()) as $key) {
            if (\Tools::isSubmit($key)) {
                \Configuration::updateValue($key, \Tools::getValue($key));
            }
        }
    }
}
