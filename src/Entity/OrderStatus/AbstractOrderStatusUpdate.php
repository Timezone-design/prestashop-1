<?php

declare(strict_types=1);

namespace Gett\MyparcelBE\Entity\OrderStatus;

use ConfigurationCore;
use Exception;
use Gett\MyparcelBE\Constant;
use Gett\MyparcelBE\Entity\OrderLabel;
use Gett\MyparcelBE\Entity\OrderStatusUpdateInterface;
use Gett\MyparcelBE\Logger\Logger;

abstract class AbstractOrderStatusUpdate implements OrderStatusUpdateInterface
{
    /**
     * @var bool
     */
    protected $sendMail;

    /**
     * @var int
     */
    protected $shipmentId;

    public function __construct(int $shipmentId, bool $sendMail = false)
    {
        $this->shipmentId = $shipmentId;
        $this->sendMail   = $sendMail;
    }

    public function onExecute(): void
    {
        OrderLabel::setOrderStatus($this->shipmentId, $this->getNewOrderStatus());
    }

    /**
     * @param  string $status
     */
    protected function sendEmail(string $status): void
    {
        $setting            = ConfigurationCore::get(Constant::ORDER_NOTIFICATION_AFTER_CONFIGURATION_NAME);
        $sendAfterFirstScan = $status === $setting;

        if ($sendAfterFirstScan && $this->sendMail) {
            try {
                OrderLabel::sendShippedNotification($this->shipmentId);
            } catch (Exception $e) {
                Logger::addLog($e, true);
            }
        }
    }
}
