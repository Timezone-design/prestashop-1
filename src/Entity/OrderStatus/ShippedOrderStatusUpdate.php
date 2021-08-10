<?php

declare(strict_types=1);

namespace Gett\MyparcelBE\Entity\OrderStatus;

use ConfigurationCore;
use Gett\MyparcelBE\Constant;
use Gett\MyparcelBE\Module\Configuration\OrderForm;
use Gett\MyparcelBE\Module\Tools\Tools;

class ShippedOrderStatusUpdate extends AbstractOrderStatusUpdate
{
    /**
     * @return int|null
     */
    public function getNewOrderStatus(): ?int
    {
        return Tools::intOrNull(ConfigurationCore::get(Constant::LABEL_SCANNED_ORDER_STATUS_CONFIGURATION_NAME));
    }

    public function onExecute(): void
    {
        parent::onExecute();
        $this->sendEmail(OrderForm::SEND_NOTIFICATION_AFTER_FIRST_SCAN);
    }
}
