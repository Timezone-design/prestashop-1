<?php

namespace Gett\MyParcel\Sdk\src\Model;

class MyParcelRequest extends \MyParcelNL\Sdk\src\Model\MyParcelRequest
{
    /**
     * API headers
     */
    const REQUEST_HEADER_WEBHOOK  = 'Content-type: application/json; charset=utf-8';

    /**
     * Supported request types.
     */
    const REQUEST_TYPE_WEBHOOK      = 'webhook_subscriptions';
}
