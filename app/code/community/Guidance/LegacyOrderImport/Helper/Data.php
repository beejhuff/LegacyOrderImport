<?php
/**
 * Legacy Order Import
 *
 * @category    Guidance
 * @package     Guidance_LegacyOrderImport
 * @copyright   Copyright (c) 2015 Guidance Solutions
 * @author      Guidance Magento Team <magento@guidance.com>
 */
class Guidance_LegacyOrderImport_Helper_Data extends Mage_Core_Helper_Data
{
    public function formatDecimalValue($qty)
    {
        if (!is_numeric($qty)) {
            return 0.0000;
        }

        return round((float)$qty, 4);
    }

    public function formatDatetime($date)
    {
        $formatted = date(
            Varien_Date::DATE_PHP_FORMAT,
            Mage::getModel('core/date')->gmtTimestamp(strtotime($date))
        );
        return $formatted ? $formatted : date(Varien_Date::DATE_PHP_FORMAT);
    }

    public function formatTimestamp($datetime)
    {
        $formatted = date(
            Varien_Date::DATETIME_PHP_FORMAT,
            Mage::getModel('core/date')->gmtTimestamp(strtotime($datetime))
        );
        return $formatted ? $formatted : date(Varien_Date::DATETIME_PHP_FORMAT);
    }
}
