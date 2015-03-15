<?php
/**
 * Legacy Order Import
 *
 * @category    Guidance
 * @package     Guidance_LegacyOrderImport
 * @copyright   Copyright (c) 2015 Guidance Solutions
 * @author      Guidance Magento Team <magento@guidance.com>
 */
class Guidance_LegacyOrderImport_Model_Resource_Import_Data extends Mage_ImportExport_Model_Resource_Import_Data
{
    protected  $_entity_type_code;

    /**
     * Retrieve an external iterator
     *
     * @return IteratorIterator
     */
    public function getIterator()
    {
        $adapter = $this->_getWriteAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), array('data'))
            ->where('entity = ?', $this->getEntityTypeCode())
            ->order('id ASC');
        $stmt = $adapter->query($select);

        $stmt->setFetchMode(Zend_Db::FETCH_NUM);
        if ($stmt instanceof IteratorAggregate) {
            $iterator = $stmt->getIterator();
        } else {
            // Statement doesn't support iterating, so fetch all records and create iterator ourselves
            $rows = $stmt->fetchAll();
            $iterator = new ArrayIterator($rows);
        }

        return $iterator;
    }

    /**
     * Clean all bunches from table.
     *
     * @return Varien_Db_Adapter_Interface
     */
    public function cleanBunches()
    {
        return $this->_getWriteAdapter()->delete($this->getMainTable(), array('entity = ?' => $this->getEntityTypeCode()));
    }

    /**
     * Get next bunch of validated rows.
     *
     * @return array|null
     */
    public function getNextBunch()
    {
        if (null === $this->_iterator) {
            $this->_iterator = $this->getIterator();
            $this->_iterator->rewind();
        }
        if ($this->_iterator->valid()) {
            $dataRow = $this->_iterator->current();
            $dataRow = Mage::helper('core')->jsonDecode($dataRow[0]);
            $this->_iterator->next();
        } else {
            $this->_iterator = null;
            $dataRow = null;
        }
        return $dataRow;
    }

    public function setEntityTypeCode($type)
    {
        $this->_entity_type_code = $type;
    }

    public function getEntityTypeCode()
    {
        return $this->_entity_type_code;
    }

    public function getBehavior()
    {
        $adapter = $this->_getReadAdapter();
        $behaviors = array_unique($adapter->fetchCol(
            $adapter->select()
                ->from($this->getMainTable(), array('behavior'))
                ->where('entity = ?', $this->getEntityTypeCode())
        ));
        if(count($behaviors) === 0) {
            throw new DomainException(Mage::helper('importexport')->__('Import stopped because file contains no data'));
        } elseif (count($behaviors) != 1) {
            Mage::throwException(Mage::helper('importexport')->__('Error in data structure: behaviors are mixed'));
        }
        return $behaviors[0];
    }
}
