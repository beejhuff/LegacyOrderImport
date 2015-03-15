<?php
/**
 * Legacy Order Import
 *
 * @category    Guidance
 * @package     Guidance_LegacyOrderImport
 * @copyright   Copyright (c) 2015 Guidance Solutions
 * @author      Guidance Magento Team <magento@guidance.com>
 */
class Guidance_LegacyOrderImport_Model_Resource_OrderImport extends Mage_Core_Model_Resource_Db_Abstract
{
    private $_attrCodesToAttr = array();

    protected function _construct()
    {
        $this->_init('sales/order', 'entity_id');
    }

    public function getWriteConnection()
    {
        return $this->_getWriteAdapter();
    }

    public function getProductSkusToIds()
    {
        /** @var $adapter Magento_Db_Adapter_Pdo_Mysql */
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()->from(
            $this->getTable('catalog/product'),
            array('sku', 'entity_id')
        );

        return (array)$adapter->fetchPairs($select);
    }

    public function getEmailsToCustomerIds()
    {
        /** @var $adapter Magento_Db_Adapter_Pdo_Mysql */
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()->from(
            $this->getTable('customer/entity'),
            array('email', 'entity_id')
        );

        return (array)$adapter->fetchPairs($select);
    }

    public function getEmailsToGroupIds()
    {
        /** @var $adapter Magento_Db_Adapter_Pdo_Mysql */
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()->from(
            $this->getTable('customer/entity'),
            array('email', 'group_id')
        );

        return (array)$adapter->fetchPairs($select);
    }

    public function getOrderIdFromLegacyOrderId($legacyOrderId)
    {
        /** @var $adapter Magento_Db_Adapter_Pdo_Mysql */
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()->from(
            $this->getMainTable(),
            array('entity_id')
        )->where('ext_order_id = ?', $legacyOrderId, 'VARCHAR');

        return (int)$adapter->fetchOne($select);
    }

    public function getIncrementIdFromLegacyOrderId($legacyOrderId)
    {
        /** @var $adapter Magento_Db_Adapter_Pdo_Mysql */
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()->from(
            $this->getMainTable(),
            array('increment_id')
        )->where('ext_order_id = ?', $legacyOrderId, 'VARCHAR');

        return (int)$adapter->fetchOne($select);
    }

    public function checkOrderExistsByLegacyOrderId($legacyOrderId)
    {
        return (bool)$this->getOrderIdFromLegacyOrderId($legacyOrderId);
    }

    public function getAttributeFromSku($attrCode, $sku)
    {
        $attribute = $this->_getAttributeByCode($attrCode);
        /** @var $adapter Magento_Db_Adapter_Pdo_Mysql */
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select();
        $select->from(
            array('product' => $this->getTable('catalog/product')),
            array()
        );
        $this->_joinAttributeToSelect($select, $attribute);
        $select->where('product.sku = ?', $sku);

        return (string)$adapter->fetchOne($select);
    }

    protected function _getAttributeByCode($code)
    {
        if (isset($this->_attrCodesToAttr[$code])) {
            return $this->_attrCodesToAttr[$code];
        }

        $entity = Mage_Catalog_Model_Product::ENTITY;
        $this->_attrCodesToAttr[$code] = Mage::getSingleton('eav/config')->getAttribute($entity, $code);

        return $this->_attrCodesToAttr[$code];
    }

    protected function _joinAttributeToSelect(Varien_Db_Select $select, $attribute)
    {
        $attrId = $attribute->getAttributeId();
        $attrCode = $attribute->getAttributeCode();
        $select->joinLeft(
            array($attrCode => $attribute->getBackendTable()),
            '(' . $attrCode . '.entity_id = product.entity_id) AND (' . $attrId . ' = ' . $attrCode . '.attribute_id) AND (' . $attrCode . '.store_id = 0)',
            array($attrCode => $attrCode . '.value')
        );

        return $select;
    }
}
