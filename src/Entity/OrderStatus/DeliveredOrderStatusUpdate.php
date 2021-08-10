<?php

declare(strict_types=1);

namespace Gett\MyparcelBE\Entity\OrderStatus;

use ConfigurationCore;
use Gett\MyparcelBE\Constant;
use Gett\MyparcelBE\Module\Tools\Tools;

class DeliveredOrderStatusUpdate extends AbstractOrderStatusUpdate
{
    /**
     * @return int|null
     */
    public function getNewOrderStatus(): ?int
    {
        return Tools::intOrNull(ConfigurationCore::get(Constant::DELIVERED_ORDER_STATUS_CONFIGURATION_NAME));
    }
}
