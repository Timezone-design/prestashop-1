<?php

namespace Gett\MyparcelBE\Service\Order;

use Configuration;
use Gett\MyparcelBE\Module\Tools\Tools;

class OrderTotalWeight
{
    /**
     * Returns the weight of the order in grams.
     *
     * @param float|int $OrderWeight
     *
     * @return int
     */
    public function convertWeightToGrams($OrderWeight): int
    {
        if ($OrderWeight > 0) {
            $weightUnit = strtolower(Configuration::get('PS_WEIGHT_UNIT'));
            switch ($weightUnit) {
                case 't':
                    $weight = Tools::ps_round($OrderWeight * 1000000);
                    break;
                case 'kg':
                    $weight = Tools::ps_round($OrderWeight * 1000);
                    break;
                case 'lbs':
                    $weight = Tools::ps_round($OrderWeight * 453.59237);
                    break;
                case 'oz':
                    $weight = Tools::ps_round($OrderWeight * 28.3495231);
                    break;
                default:
                    $weight = $OrderWeight;
                    break;
            }
        }

        return (int)ceil($weight);
    }
}
