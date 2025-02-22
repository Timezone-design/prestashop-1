<?php

namespace Gett\MyparcelBE\Logger;

use Configuration;
use Gett\MyparcelBE\Constant;
use PrestaShopLogger;

class Logger
{
    /**
     * @param        $message
     * @param  bool  $is_exception
     * @param  false $allowDuplicate
     * @param  int   $severity
     * @param  null  $errorCode
     */
    public static function addLog(
        $message,
        bool $is_exception = false,
        $allowDuplicate = false,
        $severity = 1,
        $errorCode = null
    ) {
        if ($is_exception || Configuration::get(Constant::API_LOGGING_CONFIGURATION_NAME)) {
            PrestaShopLogger::addLog(
                '[MYPARCEL] ' . $message,
                $severity,
                $errorCode,
                null,
                null,
                $allowDuplicate
            );
        }
    }
}
