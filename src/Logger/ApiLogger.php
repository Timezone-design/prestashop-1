<?php

namespace Gett\MyparcelBE\Logger;

use ConfigurationCore;
use Gett\MyparcelBE\Constant;

/**
 * Only logs data if API Logging is enabled in the MyParcel settings.
 */
class ApiLogger extends Logger
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
        if (ConfigurationCore::get(Constant::API_LOGGING_CONFIGURATION_NAME)) {
            parent::addLog($message, $is_exception, $allowDuplicate, $severity, $errorCode);
        }
    }
}
