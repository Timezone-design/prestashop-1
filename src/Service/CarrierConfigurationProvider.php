<?php

namespace Gett\MyparcelBE\Service;

use Db;
use Validate;

class CarrierConfigurationProvider
{
    public static $configuration;

    public static function get(int $carrier_id, string $name, $default = null)
    {
        if (!isset(static::$configuration[$carrier_id][$name])) {
            $result = Db::getInstance()->executeS('SELECT name,value FROM ' . _DB_PREFIX_ . "myparcelbe_carrier_configuration WHERE id_carrier = '{$carrier_id}' ");

            foreach ($result as $item) {
                static::$configuration[$carrier_id][$item['name']] = $item['value'];
            }
        }

        return isset(static::$configuration[$carrier_id][$name]) && static::$configuration[$carrier_id][$name] ? static::$configuration[$carrier_id][$name] : $default;
    }

    public static function updateValue(int $carrier_id, string $name, string $value)
    {
        Db::getInstance()->update(
            'myparcelbe_carrier_configuration',
            ['value' => pSQL($value)],
            'id_carrier = ' . (int) $carrier_id . ' AND name = "' . pSQL($name) . '" '
        );
    }
}
