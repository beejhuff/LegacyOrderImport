<?php
/**
 * Legacy Order Import
 *
 * @category    Guidance
 * @package     Guidance_LegacyOrderImport
 * @copyright   Copyright (c) 2015 Guidance Solutions
 * @author      Guidance Magento Team <magento@guidance.com>
 */
class Guidance_LegacyOrderImport_Model_Import extends Enterprise_ImportExport_Model_Import
{
    public function importSource()
    {
        $this->setData(array(
            'entity'   => $this->_getEntityTypeCode(),
            'behavior' => $this->_getBehavior(),
        ));
        $this->addLogComment(Mage::helper('importexport')->__('Begin import of "%s" with "%s" behavior', $this->getEntity(), $this->getBehavior()));
        $result = $this->_getEntityAdapter()->importData();
        $this->addLogComment(array(
            Mage::helper('importexport')->__('Checked rows: %d, checked entities: %d, invalid rows: %d, total errors: %d', $this->getProcessedRowsCount(), $this->getProcessedEntitiesCount(), $this->getInvalidRowsCount(), $this->getErrorsCount()),
            Mage::helper('importexport')->__('Import has been done successfuly.')
        ));

        return $result;
    }

    public static function getDataSourceModel()
    {
        return Mage::getSingleton('guidance_loi/resource_import_data');
    }

    protected function _getEntityTypeCode()
    {
        if (isset($_REQUEST) && isset($_REQUEST['entity']) && !empty($_REQUEST['entity'])) {
            return $_REQUEST['entity'];
        }

        if ($entity = $this->getData('entity')) {
            return $entity;
        }

        return self::getDataSourceModel()->getEntityTypeCode();
    }

    protected function _getBehavior()
    {
        if (isset($_REQUEST) && isset($_REQUEST['behavior']) && !empty($_REQUEST['behavior'])) {
            return $_REQUEST['behavior'];
        }

        if ($entity = $this->getData('behavior')) {
            return $entity;
        }

        return self::getDataSourceModel()->getBehavior();
    }
}
