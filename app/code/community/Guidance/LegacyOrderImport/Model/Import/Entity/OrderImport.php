<?php
/**
 * Legacy Order Import
 *
 * @category    Guidance
 * @package     Guidance_LegacyOrderImport
 * @copyright   Copyright (c) 2015 Guidance Solutions
 * @author      Guidance Magento Team <magento@guidance.com>
 */
class Guidance_LegacyOrderImport_Model_Import_Entity_OrderImport extends Mage_ImportExport_Model_Import_Entity_Abstract
{
    //user defined columns for order
    const COL_LEGACY_ORDER_ID        = 'legacy_order_id';
    const COL_CHECKOUT_METHOD        = 'checkout_method';
    const COL_CUSTOMER_EMAIL         = 'customer_email';
    const COL_CUSTOMER_FIRSTNAME     = 'customer_firstname';
    const COL_CUSTOMER_LASTNAME      = 'customer_lastname';
    const COL_SHIPPING_SAME_BILLING  = 'shipping_same_as_billing';

    //user defined columns for order address
    const COL_B_EMAIL         = 'b_email';
    const COL_B_PREFIX        = 'b_prefix';
    const COL_B_FIRSTNAME     = 'b_firstname';
    const COL_B_MIDDLENAME    = 'b_middlename';
    const COL_B_LASTNAME      = 'b_lastname';
    const COL_B_SUFFIX        = 'b_suffix';
    const COL_B_COMPANY       = 'b_company';
    const COL_B_STREET        = 'b_street';
    const COL_B_CITY          = 'b_city';
    const COL_B_REGION        = 'b_region';
    const COL_B_POSTCODE      = 'b_postcode';
    const COL_B_COUNTRY_ID    = 'b_country_id';
    const COL_B_TELEPHONE     = 'b_telephone';
    const COL_B_FAX           = 'b_fax';
    const COL_S_EMAIL         = 's_email';
    const COL_S_PREFIX        = 's_prefix';
    const COL_S_FIRSTNAME     = 's_firstname';
    const COL_S_MIDDLENAME    = 's_middlename';
    const COL_S_LASTNAME      = 's_lastname';
    const COL_S_COMPANY       = 's_company';
    const COL_S_SUFFIX        = 's_suffix';
    const COL_S_STREET        = 's_street';
    const COL_S_CITY          = 's_city';
    const COL_S_REGION        = 's_region';
    const COL_S_POSTCODE      = 's_postcode';
    const COL_S_COUNTRY_ID    = 's_country_id';
    const COL_S_TELEPHONE     = 's_telephone';
    const COL_S_FAX           = 's_fax';

    //user defined columns for payment
    const COL_PAYMENT_METHOD = 'p_method';

    //user defined columns for order item
    const COL_SKU                = 'sku';
    const COL_PRODUCT_NAME       = 'product_name';
    const COL_QTY                = 'qty';
    const COL_PRICE              = 'price';
    const COL_TAX_AMOUNT         = 'i_tax_amount';
    const COL_TAX_INVOICED       = 'i_tax_invoiced';
    const COL_DISCOUNT_AMOUNT    = 'i_discount_amount';
    const COL_ROW_TOTAL          = 'row_total';
    const COL_PRICE_INCL_TAX     = 'price_incl_tax';
    const COL_ROW_TOTAL_INCL_TAX = 'row_total_incl_tax';

    //some defaults
    const DEF_STATE      = 'complete';
    const DEF_STATUS     = 'complete';
    const DEF_PAY_METHOD = 'ccsave';

    const SCOPE_DEFAULT = 1;
    const SCOPE_ITEM    = -1;

    private $_resource;
    private $_adapter;
    private $_website;
    private $_helper;
    private $_productSkusToIds;
    private $_emailsToCustomerIds;
    private $_emailsToGroupIds;
    private $_orderTable;
    private $_orderAddressTable;
    private $_orderPaymentTable;
    private $_orderItemTable;
    private $_orderGridTable;
    private $_legacyIdsToOrderIds;
    private $_legacyIdsToIncrementIds;

    protected $_errorsLimit = 100;

    public function __construct()
    {
        ini_set('memory_limit', '4096M');
        $this->_dataSourceModel = Mage::getSingleton('guidance_loi/resource_import_data');
        $this->_dataSourceModel->setEntityTypeCode($this->getEntityTypeCode());

        $this->_website    = Mage::getModel('core/website')->load('base', 'code');
        $this->_storeGroup = $this->_website->getDefaultGroup();
        $this->_store      = $this->_website->getDefaultStore();
        $this->_resource   = Mage::getResourceSingleton('guidance_loi/orderImport');
        $this->_adapter    = $this->_resource->getWriteConnection();
        $this->_helper     = Mage::helper('guidance_loi');

        $this->_requiredAttributes = array(
            self::COL_LEGACY_ORDER_ID,
            self::COL_CUSTOMER_EMAIL,
            self::COL_CHECKOUT_METHOD,
            self::COL_CUSTOMER_FIRSTNAME,
            self::COL_CUSTOMER_LASTNAME,
            self::COL_B_FIRSTNAME,
            self::COL_B_LASTNAME,
            self::COL_B_STREET,
            self::COL_B_CITY,
            self::COL_B_REGION,
            self::COL_B_POSTCODE,
            self::COL_QTY,
            self::COL_ROW_TOTAL,
        );

        $this->_initProductSkusToIds();
        $this->_initEmailsToCustomerIds();
        $this->_initEmailsToGroupIds();

        $this->_orderTable        = $this->_resource->getMainTable();
        $this->_orderAddressTable = $this->_resource->getTable('sales/order_address');
        $this->_orderPaymentTable = $this->_resource->getTable('sales/order_payment');
        $this->_orderItemTable    = $this->_resource->getTable('sales/order_item');
        $this->_orderGridTable    = $this->_resource->getTable('sales/order_grid');
    }

    public function validateRow(array $rowData, $rowNum)
    {
        if ($this->getBehavior() == Mage_ImportExport_Model_Import::BEHAVIOR_DELETE) {
            return true;
        }

        static $legacyOrderId = null;

        if (isset($this->_validatedRows[$rowNum])) {
            return !isset($this->_invalidRows[$rowNum]);
        }
        $this->_validatedRows[$rowNum] = true;

        $rowScope = $this->getRowScope($rowData);
        if (self::SCOPE_DEFAULT == $rowScope) {
            $this->_processedEntitiesCount ++;
            $legacyOrderId = $rowData[self::COL_LEGACY_ORDER_ID];
        }

        $this->_validateOrderItem($rowData, $rowNum);

        if ($rowScope == self::SCOPE_DEFAULT) {
            $this->_validateRequiredAttributes($rowData, $rowNum);
            $this->_validateCustomer($rowData, $rowNum);
            $this->_validateNotAlreadyExists($rowData, $rowNum);
            if (isset($this->_invalidRows[$rowNum])) {
                $legacyOrderId = false; // mark row as invalid for next rows
            }
        } else {
            if (null === $legacyOrderId) {
                $this->addRowError('Legacy Order ID is empty', $rowNum);
            } elseif (false === $legacyOrderId) {
                $this->addRowError('Row is invalid', $rowNum);
            }
        }

        return !isset($this->_invalidRows[$rowNum]);
    }

    public function getEntityTypeCode()
    {
        return 'guidance_loi';
    }

    public function getRowScope(array $rowData)
    {
        if (strlen(trim($rowData[self::COL_LEGACY_ORDER_ID]))) {
            return self::SCOPE_DEFAULT;
        }

        return self::SCOPE_ITEM;
    }

    protected function _initProductSkusToIds()
    {
        $this->_productSkusToIds = $this->_resource->getProductSkusToIds();
    }

    protected function _initEmailsToCustomerIds()
    {
        $this->_emailsToCustomerIds = $this->_resource->getEmailsToCustomerIds();
    }

    protected function _initEmailsToGroupIds()
    {
        $this->_emailsToGroupIds = $this->_resource->getEmailsToGroupIds();
    }

    protected function _validateRequiredAttributes(array $rowData, $rowNum)
    {
        foreach ($this->_requiredAttributes as $attr) {
            if (empty($rowData[$attr])) {
                $error = $this->_helper->__('Required column %s cannot be empty', $attr);
                $this->addRowError($error, $rowNum);
            }
        }
    }

    protected function _validateOrderItem(array $rowData, $rowNum)
    {
        if (!isset($rowData[self::COL_SKU]) || empty($rowData[self::COL_SKU])) {
            $error = $this->_helper->__('Product SKU was not specified');
            $this->addRowError($error, $rowNum);
        } else if (!array_key_exists($rowData[self::COL_SKU], $this->_productSkusToIds)) {
            $error = $this->_helper->__('Product with SKU %s does not exist', $rowData[self::COL_SKU]);
            $this->addRowError($error, $rowNum);
        }
    }

    protected function _validateCustomer(array $rowData, $rowNum)
    {
        if (!array_key_exists($rowData[self::COL_CUSTOMER_EMAIL], $this->_emailsToCustomerIds)
                && (!isset($rowData[self::COL_CHECKOUT_METHOD]) || strtolower($rowData[self::COL_CHECKOUT_METHOD]) != 'guest'))
        {
            $error = $this->_helper->__('Customer with email %s does not exist and checkout method is not specified as guest', $rowData[self::COL_CUSTOMER_EMAIL]);
            $this->addRowError($error, $rowNum);
        }
    }

    protected function _validateNotAlreadyExists(array $rowData, $rowNum)
    {
        $exists = $this->_resource->checkOrderExistsByLegacyOrderId($rowData[self::COL_LEGACY_ORDER_ID]);
        if ($exists) {
            $error = $this->_helper->__('An order with Legacy Order ID %s already exists', $rowData[self::COL_LEGACY_ORDER_ID]);
            $this->addRowError($error, $rowNum);
        }
    }

    protected function _importData()
    {
        if ($this->getBehavior() == Mage_ImportExport_Model_Import::BEHAVIOR_APPEND ||
            $this->getBehavior() == Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE)
        {
            $this->_saveData();
            return true;
        } else if ($this->getBehavior() == Mage_ImportExport_Model_Import::BEHAVIOR_DELETE) {
            $this->_deleteData();
            return true;
        }
        return false;
    }

    protected function _saveData()
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch())
        {
            $entityRow = array();
            foreach ($bunch as $rowNum => $rowData) {
                if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                    $entityRow = $rowData;
                }
                try {
                    $this->_adapter->beginTransaction();
                    if (self::SCOPE_DEFAULT == $this->getRowScope($rowData)) {
                        $this->_adapter->insertOnDuplicate($this->_orderTable, $this->_mapOrder($entityRow));
                        foreach ($this->_mapOrderAddresses($entityRow) as $data) {
                            $this->_adapter->insertOnDuplicate($this->_orderAddressTable, $data);
                        }
                        $this->_adapter->insertOnDuplicate($this->_orderPaymentTable, $this->_mapOrderPayment($entityRow));
                        $this->_adapter->insertOnDuplicate($this->_orderGridTable, $this->_mapOrderGrid($entityRow));
                    }
                    $this->_adapter->insertOnDuplicate($this->_orderItemTable, $this->_mapOrderItem($rowData, $entityRow));
                    $this->_adapter->commit();
                } catch (Exception $e) {
                    $this->addRowError($e->getMessage(), $rowNum);
                    Mage::logException($e);
                    $this->_adapter->rollback();
                }
            }
        }
    }

    protected function _deleteData()
    {
        $tables = array(
            $this->_orderTable,
            $this->_orderAddressTable,
            $this->_orderPaymentTable,
            $this->_orderItemTable,
            $this->_orderGridTable,
        );

        $this->_adapter->query('SET foreign_key_checks = 0');
        foreach ($tables as $table) {
            $this->_adapter->truncateTable($table);
        }
        $this->_adapter->query('SET foreign_key_checks = 1');
    }

    protected function _getCustomerId(array $rowData)
    {
        return strtolower(@$rowData[self::COL_CHECKOUT_METHOD]) == 'guest' ?
            null : $this->_emailsToCustomerIds[$rowData[self::COL_CUSTOMER_EMAIL]];
    }

    protected function _getOrderIdFromLegacyOrderId($legacyOrderId)
    {
        if (isset($this->_legacyIdsToOrderIds[$legacyOrderId])) {
            return $this->_legacyIdsToOrderIds[$legacyOrderId];
        }

        $this->_legacyIdsToOrderIds[$legacyOrderId] = $this->_resource->getOrderIdFromLegacyOrderId($legacyOrderId);

        return $this->_legacyIdsToOrderIds[$legacyOrderId];
    }

    protected function _getIncrementIdFromLegacyOrderId($legacyOrderId)
    {
        if (isset($this->_legacyIdsToIncrementIds[$legacyOrderId])) {
            return $this->_legacyIdsToIncrementIds[$legacyOrderId];
        }

        $this->_legacyIdsToIncrementIds[$legacyOrderId] = $this->_resource->getIncrementIdFromLegacyOrderId($legacyOrderId);

        return $this->_legacyIdsToIncrementIds[$legacyOrderId];
    }

    protected function _getStoreName()
    {
        return $this->_website->getName() . "\r\n" . $this->_storeGroup->getName() . "\r\n" . $this->_store->getName();
    }

    protected function _getProductNameFromSku($sku)
    {
        return $this->_resource->getAttributeFromSku('name', $sku);
    }

    protected function _getProductDescriptionFromSku($sku)
    {
        return $this->_resource->getAttributeFromSku('short_description', $sku);
    }

    //table mappings

    protected function _mapOrder(array $entityRow)
    {
        $orderData = array(
            'entity_id'                       => null,
            'state'                           => @$entityRow['state'] ? $entityRow['state'] : self::DEF_STATE,
            'status'                          => @$entityRow['status'] ? $entityRow['status'] : self::DEF_STATUS,
            'coupon_code'                     => @$entityRow['coupon_code'] ? $entityRow['coupon_code'] : null,
            'protect_code'                    => substr(md5(uniqid(mt_rand(), true) . ':' . microtime(true)), 5, 6),
            'shipping_description'            => @$entityRow['shipping_description'] ? $entityRow['shipping_description'] : 'Standard Shipping',
            'is_virtual'                      => @$entityRow['is_virtual'] ? $entityRow['is_virtual'] : 0,
            'store_id'                        => @$entityRow['store_id'] ? $entityRow['store_id'] : $this->_store->getId(),
            'customer_id'                     => $this->_getCustomerId($entityRow),
            'base_discount_amount'            => @$entityRow['base_discount_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_discount_amount']) : 0.0000,
            'base_discount_canceled'          => @$entityRow['base_discount_canceled'] ? $this->_helper->formatDecimalValue($entityRow['base_discount_canceled']) : null,
            'base_discount_invoiced'          => @$entityRow['base_discount_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['base_discount_invoiced']) : null,
            'base_discount_refunded'          => @$entityRow['base_discount_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_discount_refunded']) : null,
            'base_grand_total'                => @$entityRow['base_grand_total'] ? $this->_helper->formatDecimalValue($entityRow['base_grand_total']) : 0.0000,
            'base_shipping_amount'            => @$entityRow['base_shipping_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_amount']) : 0.0000,
            'base_shipping_canceled'          => @$entityRow['base_shipping_canceled'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_canceled']) : null,
            'base_shipping_invoiced'          => @$entityRow['base_shipping_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_invoiced']) : null,
            'base_shipping_refunded'          => @$entityRow['base_shipping_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_refunded']) : null,
            'base_shipping_tax_amount'        => @$entityRow['base_shipping_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_tax_amount']) : 0.0000,
            'base_shipping_tax_refunded'      => @$entityRow['base_shipping_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_tax_refunded']) : null,
            'base_subtotal'                   => @$entityRow['base_subtotal'] ? $this->_helper->formatDecimalValue($entityRow['base_subtotal']) : 0.0000,
            'base_subtotal_canceled'          => @$entityRow['base_subtotal_canceled'] ? $this->_helper->formatDecimalValue($entityRow['base_subtotal_canceled']) : null,
            'base_subtotal_refunded'          => @$entityRow['base_subtotal_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_subtotal_refunded']) : null,
            'base_tax_amount'                 => @$entityRow['base_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_tax_amount']) : 0.0000,
            'base_tax_canceled'               => @$entityRow['base_tax_canceled'] ? $this->_helper->formatDecimalValue($entityRow['base_tax_canceled']) : null,
            'base_tax_invoiced'               => @$entityRow['base_tax_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['base_tax_invoiced']) : null,
            'base_tax_refunded'               => @$entityRow['base_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_tax_refunded']) : null,
            'base_to_global_rate'             => 1.0000,
            'base_to_order_rate'              => 1.0000,
            'base_total_canceled'             => @$entityRow['base_total_canceled'] ? $this->_helper->formatDecimalValue($entityRow['base_total_canceled']) : null,
            'base_total_invoiced'             => @$entityRow['base_total_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['base_total_invoiced']) : null,
            'base_total_invoiced_cost'        => @$entityRow['base_total_invoiced_cost'] ? $this->_helper->formatDecimalValue($entityRow['base_total_invoiced_cost']) : null,
            'base_total_offline_refunded'     => @$entityRow['base_total_offline_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_total_offline_refunded']) : null,
            'base_total_online_refunded'      => @$entityRow['base_total_online_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_total_online_refunded']) : null,
            'base_total_paid'                 => @$entityRow['base_total_paid'] ? $this->_helper->formatDecimalValue($entityRow['base_total_paid']) : null,
            'base_total_qty_ordered'          => @$entityRow['base_total_qty_ordered'] ? $this->_helper->formatDecimalValue($entityRow['base_total_qty_ordered']) : null,
            'base_shipping_discount_amount'   => @$entityRow['base_shipping_discount_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_discount_amount']) : 0.0000,
            'base_subtotal_incl_tax'          => @$entityRow['base_subtotal_incl_tax'] ? $this->_helper->formatDecimalValue($entityRow['base_subtotal_incl_tax']) : 0.0000,
            'base_total_due'                  => @$entityRow['base_total_due'] ? $this->_helper->formatDecimalValue($entityRow['base_total_due']) : null,
            'base_total_refunded'             => @$entityRow['base_total_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_total_refunded']) : null,
            'base_hidden_tax_amount'          => @$entityRow['base_hidden_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_hidden_tax_amount']) : 0.0000,
            'base_shipping_hidden_tax_amnt'   => @$entityRow['base_shipping_hidden_tax_amnt'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_hidden_tax_amnt']) : 0.0000,
            'base_hidden_tax_invoiced'        => @$entityRow['base_hidden_tax_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['base_hidden_tax_invoiced']) : null,
            'base_hidden_tax_refunded'        => @$entityRow['base_hidden_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_hidden_tax_refunded']) : null,
            'base_shipping_incl_tax'          => @$entityRow['base_shipping_incl_tax'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_incl_tax']) : 0.0000,
            'base_customer_balance_amount'    => @$entityRow['base_customer_balance_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_customer_balance_amount']) : null,
            'base_customer_balance_invoiced'  => @$entityRow['base_customer_balance_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['base_customer_balance_invoiced']) : null,
            'base_customer_balance_refunded'  => @$entityRow['base_customer_balance_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_customer_balance_refunded']) : null,
            'bs_customer_bal_total_refunded'  => @$entityRow['bs_customer_bal_total_refunded'] ? $this->_helper->formatDecimalValue($entityRow['bs_customer_bal_total_refunded']) : null,
            'base_gift_cards_amount'          => @$entityRow['base_gift_cards_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_gift_cards_amount']) : 0.0000,
            'base_gift_cards_invoiced'        => @$entityRow['base_gift_cards_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['base_gift_cards_invoiced']) : null,
            'base_gift_cards_refunded'        => @$entityRow['base_gift_cards_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_gift_cards_refunded']) : null,
            'base_reward_currency_amount'     => @$entityRow['base_reward_currency_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_reward_currency_amount']) : null,
            'base_rwrd_crrncy_amt_invoiced'   => @$entityRow['base_rwrd_crrncy_amt_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['base_rwrd_crrncy_amt_invoiced']) : null,
            'base_rwrd_crrncy_amnt_refnded'   => @$entityRow['base_rwrd_crrncy_amnt_refnded'] ? $this->_helper->formatDecimalValue($entityRow['base_rwrd_crrncy_amnt_refnded']) : null,
            'discount_amount'                 => @$entityRow['discount_amount'] ? $this->_helper->formatDecimalValue($entityRow['discount_amount']) : 0.0000,
            'discount_canceled'               => @$entityRow['discount_canceled'] ? $this->_helper->formatDecimalValue($entityRow['discount_canceled']) : null,
            'discount_invoiced'               => @$entityRow['discount_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['discount_invoiced']) : null,
            'discount_refunded'               => @$entityRow['discount_refunded'] ? $this->_helper->formatDecimalValue($entityRow['discount_refunded']) : null,
            'grand_total'                     => @$entityRow['grand_total'] ? $this->_helper->formatDecimalValue($entityRow['grand_total']) : 0.0000,
            'shipping_amount'                 => @$entityRow['shipping_amount'] ? $this->_helper->formatDecimalValue($entityRow['shipping_amount']) : 0.0000,
            'shipping_canceled'               => @$entityRow['shipping_canceled'] ? $this->_helper->formatDecimalValue($entityRow['shipping_canceled']) : null,
            'shipping_invoiced'               => @$entityRow['shipping_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['shipping_invoiced']) : null,
            'shipping_refunded'               => @$entityRow['shipping_refunded'] ? $this->_helper->formatDecimalValue($entityRow['shipping_refunded']) : null,
            'shipping_tax_amount'             => @$entityRow['shipping_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['shipping_tax_amount']) : 0.0000,
            'shipping_tax_refunded'           => @$entityRow['shipping_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['shipping_tax_refunded']) : null,
            'subtotal'                        => @$entityRow['subtotal'] ? $this->_helper->formatDecimalValue($entityRow['subtotal']) : 0.0000,
            'subtotal_canceled'               => @$entityRow['subtotal_canceled'] ? $this->_helper->formatDecimalValue($entityRow['subtotal_canceled']) : null,
            'subtotal_refunded'               => @$entityRow['subtotal_refunded'] ? $this->_helper->formatDecimalValue($entityRow['subtotal_refunded']) : null,
            'tax_amount'                      => @$entityRow['tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['tax_amount']) : 0.0000,
            'tax_canceled'                    => @$entityRow['tax_canceled'] ? $this->_helper->formatDecimalValue($entityRow['tax_canceled']) : null,
            'tax_invoiced'                    => @$entityRow['tax_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['tax_invoiced']) : null,
            'tax_refunded'                    => @$entityRow['tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['tax_refunded']) : null,
            'store_to_base_rate'              => 1.0000,
            'store_to_order_rate'             => 1.0000,
            'total_canceled'                  => @$entityRow['total_canceled'] ? $this->_helper->formatDecimalValue($entityRow['total_canceled']) : null,
            'total_invoiced'                  => @$entityRow['total_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['total_invoiced']) : null,
            'total_offline_refunded'          => @$entityRow['total_offline_refunded'] ? $this->_helper->formatDecimalValue($entityRow['total_offline_refunded']) : null,
            'total_online_refunded'           => @$entityRow['total_online_refunded'] ? $this->_helper->formatDecimalValue($entityRow['total_online_refunded']) : null,
            'total_paid'                      => @$entityRow['total_paid'] ? $this->_helper->formatDecimalValue($entityRow['total_paid']) : null,
            'total_qty_ordered'               => @$entityRow['total_qty_ordered'] ? $this->_helper->formatDecimalValue($entityRow['total_qty_ordered']) : null,
            'total_refunded'                  => @$entityRow['total_refunded'] ? $this->_helper->formatDecimalValue($entityRow['total_refunded']) : null,
            'hidden_tax_amount'               => @$entityRow['hidden_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['hidden_tax_amount']) : 0.0000,
            'shipping_hidden_tax_amount'      => @$entityRow['shipping_hidden_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['shipping_hidden_tax_amount']) : 0.0000,
            'hidden_tax_invoiced'             => @$entityRow['hidden_tax_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['hidden_tax_invoiced']) : null,
            'hidden_tax_refunded'             => @$entityRow['hidden_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['hidden_tax_refunded']) : null,
            'shipping_incl_tax'               => @$entityRow['shipping_incl_tax'] ? $this->_helper->formatDecimalValue($entityRow['shipping_incl_tax']) : 0.0000,
            'customer_balance_amount'         => @$entityRow['customer_balance_amount'] ? $this->_helper->formatDecimalValue($entityRow['customer_balance_amount']) : null,
            'customer_balance_invoiced'       => @$entityRow['customer_balance_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['customer_balance_invoiced']) : null,
            'customer_balance_refunded'       => @$entityRow['customer_balance_refunded'] ? $this->_helper->formatDecimalValue($entityRow['customer_balance_refunded']) : null,
            'customer_bal_total_refunded'     => @$entityRow['customer_bal_total_refunded'] ? $this->_helper->formatDecimalValue($entityRow['customer_bal_total_refunded']) : null,
            'gift_cards_amount'               => @$entityRow['gift_cards_amount'] ? $this->_helper->formatDecimalValue($entityRow['gift_cards_amount']) : 0.0000,
            'gift_cards_invoiced'             => @$entityRow['gift_cards_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gift_cards_invoiced']) : null,
            'gift_cards_refunded'             => @$entityRow['gift_cards_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gift_cards_refunded']) : null,
            'reward_currency_amount'          => @$entityRow['reward_currency_amount'] ? $this->_helper->formatDecimalValue($entityRow['reward_currency_amount']) : null,
            'rwrd_currency_amount_invoiced'   => @$entityRow['rwrd_currency_amount_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['rwrd_currency_amount_invoiced']) : null,
            'rwrd_crrncy_amnt_refunded'       => @$entityRow['rwrd_crrncy_amnt_refunded'] ? $this->_helper->formatDecimalValue($entityRow['rwrd_crrncy_amnt_refunded']) : null,
            'shipping_discount_amount'        => @$entityRow['shipping_discount_amount'] ? $this->_helper->formatDecimalValue($entityRow['shipping_discount_amount']) : 0.0000,
            'subtotal_incl_tax'               => @$entityRow['subtotal_incl_tax'] ? $this->_helper->formatDecimalValue($entityRow['subtotal_incl_tax']) : 0.0000,
            'total_due'                       => @$entityRow['total_due'] ? $this->_helper->formatDecimalValue($entityRow['total_due']) : null,
            'can_ship_partially'              => null,
            'can_ship_partially_item'         => null,
            'customer_is_guest'               => strtolower(@$entityRow[self::COL_CHECKOUT_METHOD]) == 'guest' ? 1 : 0,
            'customer_note_notify'            => 0,
            'billing_address_id'              => Mage::getResourceHelper('importexport')->getNextAutoincrement($this->_orderAddressTable),
            'customer_group_id'               => strtolower(@$entityRow[self::COL_CHECKOUT_METHOD]) == 'guest' ? 0 : $this->_emailsToGroupIds[$entityRow[self::COL_CUSTOMER_EMAIL]],
            'edit_increment'                  => null,
            'email_sent'                      => 0,
            'forced_shipment_with_invoice'    => null,
            'gift_message_id'                 => null,
            'payment_auth_expiration'         => null,
            'paypal_ipn_customer_notified'    => null,
            'quote_address_id'                => null,
            'quote_id'                        => null,
            'shipping_address_id'             => Mage::getResourceHelper('importexport')->getNextAutoincrement($this->_orderAddressTable) + 1,
            'adjustment_negative'             => null,
            'adjustment_positive'             => null,
            'base_adjustment_negative'        => null,
            'base_adjustment_positive'        => null,
            'payment_authorization_amount'    => null,
            'weight'                          => @$entityRow['weight'] ? $this->_helper->formatDecimalValue($entityRow['weight']) : 1.0000,
            'customer_dob'                    => null,
            'increment_id'                    => Mage::getSingleton('eav/config')->getEntityType('order')->fetchNewIncrementId($this->_store->getId()),
            'applied_rule_ids'                => null,
            'base_currency_code'              => 'USD',
            'customer_email'                  => $entityRow[self::COL_CUSTOMER_EMAIL],
            'customer_firstname'              => $entityRow[self::COL_CUSTOMER_FIRSTNAME],
            'customer_lastname'               => $entityRow[self::COL_CUSTOMER_LASTNAME],
            'customer_middlename'             => @$entityRow['customer_middlename'] ? $entityRow['customer_middlename'] : null,
            'customer_prefix'                 => @$entityRow['customer_prefix'] ? $entityRow['customer_prefix'] : null,
            'customer_suffix'                 => @$entityRow['customer_suffix'] ? $entityRow['customer_suffix'] : null,
            'customer_taxvat'                 => @$entityRow['customer_taxvat'] ? $entityRow['customer_taxvat'] : null,
            'discount_description'            => @$entityRow['discount_description'] ? $entityRow['discount_description'] : null,
            'ext_customer_id'                 => @$entityRow['ext_customer_id'] ? $entityRow['ext_customer_id'] : null,
            'ext_order_id'                    => $entityRow[self::COL_LEGACY_ORDER_ID],
            'global_currency_code'            => 'USD',
            'hold_before_state'               => null,
            'hold_before_status'              => null,
            'order_currency_code'             => 'USD',
            'original_increment_id'           => null,
            'relation_child_id'               => null,
            'relation_child_real_id'          => null,
            'relation_parent_id'              => null,
            'relation_parent_real_id'         => null,
            'remote_ip'                       => @$entityRow['remote_ip'] ? $entityRow['remote_ip'] : null,
            'shipping_method'                 => @$entityRow['shipping_method'] ? $entityRow['shipping_method'] : null,
            'store_currency_code'             => 'USD',
            'store_name'                      => $this->_getStoreName(),
            'x_forwarded_for'                 => @$entityRow['x_forwarded_for'] ? $entityRow['x_forwarded_for'] : null,
            'customer_note'                   => @$entityRow['customer_note'] ? $entityRow['customer_note'] : null,
            'created_at'                      => $this->_helper->formatTimestamp(@$entityRow['created_at']),
            'updated_at'                      => $this->_helper->formatTimestamp(@$entityRow['updated_at']),
            'total_item_count'                => @$entityRow['total_item_count'] ? $entityRow['total_item_count'] : 1,
            'customer_gender'                 => @$entityRow['customer_gender'] ? $entityRow['customer_gender'] : null,
            'gift_cards'                      => @$entityRow['gift_cards'] ? $entityRow['gift_cards'] : 'a:():{}',
            'reward_points_balance'           => @$entityRow['reward_points_balance'] ? $entityRow['reward_points_balance'] : null,
            'reward_points_balance_refunded'  => @$entityRow['reward_points_balance_refunded'] ? $entityRow['reward_points_balance_refunded'] : null,
            'reward_points_balance_refund'    => @$entityRow['reward_points_balance_refund'] ? $entityRow['reward_points_balance_refund'] : null,
            'reward_salesrule_points'         => @$entityRow['reward_salesrule_points'] ? $entityRow['reward_salesrule_points'] : null,
            'coupon_rule_name'                => @$entityRow['coupon_rule_name'] ? $entityRow['coupon_rule_name'] : null,
            'gw_id'                           => @$entityRow['gw_id'] ? $entityRow['gw_id'] : null,
            'gw_allow_gift_receipt'           => @$entityRow['gw_allow_gift_receipt'] ? $entityRow['gw_allow_gift_receipt'] : null,
            'gw_add_card'                     => @$entityRow['gw_add_card'] ? $entityRow['gw_add_card'] : null,
            'gw_base_price'                   => @$entityRow['gw_base_price'] ? $this->_helper->formatDecimalValue($entityRow['gw_base_price']) : null,
            'gw_price'                        => @$entityRow['gw_price'] ? $this->_helper->formatDecimalValue($entityRow['gw_price']) : null,
            'gw_items_base_price'             => @$entityRow['gw_items_base_price'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_base_price']) : null,
            'gw_items_price'                  => @$entityRow['gw_items_price'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_price']) : null,
            'gw_card_base_price'              => @$entityRow['gw_card_base_price'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_base_price']) : null,
            'gw_card_price'                   => @$entityRow['gw_card_price'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_price']) : null,
            'gw_base_tax_amount'              => @$entityRow['gw_base_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['gw_base_tax_amount']) : null,
            'gw_tax_amount'                   => @$entityRow['gw_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['gw_tax_amount']) : null,
            'gw_items_base_tax_amount'        => @$entityRow['gw_items_base_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_base_tax_amount']) : null,
            'gw_items_tax_amount'             => @$entityRow['gw_items_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_tax_amount']) : null,
            'gw_card_base_tax_amount'         => @$entityRow['gw_card_base_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_base_tax_amount']) : null,
            'gw_card_tax_amount'              => @$entityRow['gw_card_tax_amount'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_tax_amount']) : null,
            'gw_base_price_invoiced'          => @$entityRow['gw_base_price_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_base_price_invoiced']) : null,
            'gw_price_invoiced'               => @$entityRow['gw_price_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_price_invoiced']) : null,
            'gw_items_base_price_invoiced'    => @$entityRow['gw_items_base_price_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_base_price_invoiced']) : null,
            'gw_items_price_invoiced'         => @$entityRow['gw_items_price_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_price_invoiced']) : null,
            'gw_card_base_price_invoiced'     => @$entityRow['gw_card_base_price_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_base_price_invoiced']) : null,
            'gw_card_price_invoiced'          => @$entityRow['gw_card_price_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_price_invoiced']) : null,
            'gw_base_tax_amount_invoiced'     => @$entityRow['gw_base_tax_amount_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_base_tax_amount_invoiced']) : null,
            'gw_tax_amount_invoiced'          => @$entityRow['gw_tax_amount_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_tax_amount_invoiced']) : null,
            'gw_items_base_tax_invoiced'      => @$entityRow['gw_items_base_tax_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_base_tax_invoiced']) : null,
            'gw_items_tax_invoiced'           => @$entityRow['gw_items_tax_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_tax_invoiced']) : null,
            'gw_card_base_tax_invoiced'       => @$entityRow['gw_card_base_tax_invoiced'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_base_tax_invoiced']) : null,
            'gw_base_price_refunded'          => @$entityRow['gw_base_price_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_base_price_refunded']) : null,
            'gw_price_refunded'               => @$entityRow['gw_price_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_price_refunded']) : null,
            'gw_items_base_price_refunded'    => @$entityRow['gw_items_base_price_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_base_price_refunded']) : null,
            'gw_items_price_refunded'         => @$entityRow['gw_items_price_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_price_refunded']) : null,
            'gw_card_base_price_refunded'     => @$entityRow['gw_card_base_price_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_base_price_refunded']) : null,
            'gw_card_price_refunded'          => @$entityRow['gw_card_price_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_price_refunded']) : null,
            'gw_base_tax_amount_refunded'     => @$entityRow['gw_base_tax_amount_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_base_tax_amount_refunded']) : null,
            'gw_tax_amount_refunded'          => @$entityRow['gw_tax_amount_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_tax_amount_refunded']) : null,
            'gw_items_base_tax_refunded'      => @$entityRow['gw_items_base_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_base_tax_refunded']) : null,
            'gw_items_tax_refunded'           => @$entityRow['gw_items_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_items_tax_refunded']) : null,
            'gw_card_base_tax_refunded'       => @$entityRow['gw_card_base_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_base_tax_refunded']) : null,
            'gw_card_tax_refunded'            => @$entityRow['gw_card_tax_refunded'] ? $this->_helper->formatDecimalValue($entityRow['gw_card_tax_refunded']) : null,
        );

        //loop through and assign reg values to base values if base not set
        foreach ($orderData as $k => &$v) {
            if (!empty($orderData[$k]) && array_key_exists('base_' . $k, $orderData) && empty($orderData['base_' . $k])) {
                $orderData['base_' . $k] = $v;
            }
        }

        return $orderData;
    }

    protected function _mapOrderAddresses(array $entityRow)
    {
        $addresses[0] = array(
            'entity_id'           => null,
            'parent_id'           => $this->_getOrderIdFromLegacyOrderId($entityRow[self::COL_LEGACY_ORDER_ID]),
            'customer_address_id' => null,
            'quote_address_id'    => null,
            'region_id'           => null,
            'customer_id'         => $this->_getCustomerId($entityRow),
            'fax'                 => @$entityRow[self::COL_B_FAX] ? $entityRow[self::COL_B_FAX] : null,
            'region'              => $entityRow[self::COL_B_REGION],
            'postcode'            => $entityRow[self::COL_B_POSTCODE],
            'lastname'            => $entityRow[self::COL_B_LASTNAME],
            'street'              => $entityRow[self::COL_B_STREET],
            'city'                => $entityRow[self::COL_B_CITY],
            'email'               => @$entityRow[self::COL_B_EMAIL] ? $entityRow[self::COL_B_EMAIL] : null,
            'telephone'           => @$entityRow[self::COL_B_TELEPHONE] ? $entityRow[self::COL_B_TELEPHONE] : null,
            'country_id'          => @$entityRow[self::COL_B_COUNTRY_ID] ? $entityRow[self::COL_B_COUNTRY_ID] : null,
            'firstname'           => $entityRow[self::COL_B_FIRSTNAME],
            'address_type'        => 'billing',
            'prefix'              => @$entityRow[self::COL_B_PREFIX] ? $entityRow[self::COL_B_PREFIX] : null,
            'middlename'          => @$entityRow[self::COL_B_MIDDLENAME] ? $entityRow[self::COL_B_MIDDLENAME] : null,
            'suffix'              => @$entityRow[self::COL_B_SUFFIX] ? $entityRow[self::COL_B_SUFFIX] : null,
            'company'             => @$entityRow[self::COL_B_COMPANY] ? $entityRow[self::COL_B_COMPANY] : null,
            'vat_id'              => null,
            'vat_is_valid'        => null,
            'vat_request_id'      => null,
            'vat_request_date'    => null,
            'vat_request_success' => null,
        );
        $addresses[1] = @$entityRow[self::COL_SHIPPING_SAME_BILLING] ? $addresses[0] : array(
            'entity_id'           => null,
            'parent_id'           => $this->_getOrderIdFromLegacyOrderId($entityRow[self::COL_LEGACY_ORDER_ID]),
            'customer_address_id' => null,
            'quote_address_id'    => null,
            'region_id'           => null,
            'customer_id'         => $this->_getCustomerId($entityRow),
            'fax'                 => @$entityRow[self::COL_S_FAX] ? $entityRow[self::COL_S_FAX] : null,
            'region'              => @$entityRow[self::COL_S_REGION] ? $entityRow[self::COL_S_REGION] : null,
            'postcode'            => @$entityRow[self::COL_S_POSTCODE] ? $entityRow[self::COL_S_POSTCODE] : null,
            'lastname'            => @$entityRow[self::COL_S_LASTNAME] ? $entityRow[self::COL_S_LASTNAME] : null,
            'street'              => @$entityRow[self::COL_S_STREET] ? $entityRow[self::COL_S_STREET] : null,
            'city'                => @$entityRow[self::COL_S_CITY] ? $entityRow[self::COL_S_CITY] : null,
            'email'               => @$entityRow[self::COL_S_EMAIL] ? $entityRow[self::COL_S_EMAIL] : null,
            'telephone'           => @$entityRow[self::COL_S_TELEPHONE] ? $entityRow[self::COL_S_TELEPHONE] : null,
            'country_id'          => @$entityRow[self::COL_S_COUNTRY_ID] ? $entityRow[self::COL_S_COUNTRY_ID] : null,
            'firstname'           => @$entityRow[self::COL_S_FIRSTNAME] ? $entityRow[self::COL_S_FIRSTNAME] : null,
            'address_type'        => 'shipping',
            'prefix'              => @$entityRow[self::COL_S_PREFIX] ? $entityRow[self::COL_S_PREFIX] : null,
            'middlename'          => @$entityRow[self::COL_S_MIDDLENAME] ? $entityRow[self::COL_S_MIDDLENAME] : null,
            'suffix'              => @$entityRow[self::COL_S_SUFFIX] ? $entityRow[self::COL_S_SUFFIX] : null,
            'company'             => @$entityRow[self::COL_S_COMPANY] ? $entityRow[self::COL_S_COMPANY] : null,
            'vat_id'              => null,
            'vat_is_valid'        => null,
            'vat_request_id'      => null,
            'vat_request_date'    => null,
            'vat_request_success' => null,
        );
        //in case we copied the billing
        $addresses[1]['address_type'] = 'shipping';

        return $addresses;
    }

    protected function _mapOrderPayment(array $entityRow)
    {
        $paymentData = array(
            'entity_id'                    => null,
            'parent_id'                    => $this->_getOrderIdFromLegacyOrderId($entityRow[self::COL_LEGACY_ORDER_ID]),
            'base_shipping_captured'       => @$entityRow['base_shipping_captured'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_captured']) : null,
            'shipping_captured'            => @$entityRow['shipping_captured'] ? $this->_helper->formatDecimalValue($entityRow['shipping_captured']) : null,
            'amount_refunded'              => @$entityRow['amount_refunded'] ? $this->_helper->formatDecimalValue($entityRow['amount_refunded']) : null,
            'base_amount_paid'             => @$entityRow['base_amount_paid'] ? $this->_helper->formatDecimalValue($entityRow['base_amount_paid']) : null,
            'amount_canceled'              => @$entityRow['amount_authorized'] ? $this->_helper->formatDecimalValue($entityRow['amount_authorized']) : null,
            'base_amount_authorized'       => @$entityRow['base_amount_authorized'] ? $this->_helper->formatDecimalValue($entityRow['base_amount_authorized']) : null,
            'base_amount_paid_online'      => @$entityRow['base_amount_paid_online'] ? $this->_helper->formatDecimalValue($entityRow['base_amount_paid_online']) : null,
            'base_amount_refunded_online'  => @$entityRow['base_amount_refunded_online'] ? $this->_helper->formatDecimalValue($entityRow['base_amount_refunded_online']) : null,
            'base_shipping_amount'         => @$entityRow['base_shipping_amount'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_amount']) : null,
            'shipping_amount'              => @$entityRow['shipping_amount'] ? $this->_helper->formatDecimalValue($entityRow['shipping_amount']) : null,
            'amount_paid'                  => @$entityRow['amount_paid'] ? $this->_helper->formatDecimalValue($entityRow['amount_paid']) : null,
            'amount_authorized'            => @$entityRow['amount_authorized'] ? $this->_helper->formatDecimalValue($entityRow['amount_authorized']) : null,
            'base_amount_ordered'          => @$entityRow['base_amount_ordered'] ? $this->_helper->formatDecimalValue($entityRow['base_amount_ordered']) : null,
            'base_shipping_refunded'       => @$entityRow['base_shipping_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_shipping_refunded']) : null,
            'shipping_refunded'            => @$entityRow['shipping_refunded'] ? $this->_helper->formatDecimalValue($entityRow['shipping_refunded']) : null,
            'base_amount_refunded'         => @$entityRow['base_amount_refunded'] ? $this->_helper->formatDecimalValue($entityRow['base_amount_refunded']) : null,
            'amount_ordered'               => @$entityRow['amount_ordered'] ? $this->_helper->formatDecimalValue($entityRow['amount_ordered']) : null,
            'base_amount_canceled'         => @$entityRow['base_amount_canceled'] ? $this->_helper->formatDecimalValue($entityRow['base_amount_canceled']) : null,
            'quote_payment_id'             => @$entityRow['quote_payment_id'] ? $entityRow['quote_payment_id'] : null,
            'additional_data'              => @$entityRow['additional_data'] ? $entityRow['additional_data'] : null,
            'cc_exp_month'                 => @$entityRow['cc_exp_month'] ? $entityRow['cc_exp_month'] : null,
            'cc_ss_start_year'             => @$entityRow['cc_ss_start_year'] ? $entityRow['cc_ss_start_year'] : null,
            'echeck_bank_name'             => @$entityRow['echeck_bank_name'] ? $entityRow['echeck_bank_name'] : null,
            'method'                       => @$entityRow['method'] ? $entityRow['method'] : self::DEF_PAY_METHOD,
            'cc_debug_request_body'        => @$entityRow['cc_debug_request_body'] ? $entityRow['cc_debug_request_body'] : null,
            'cc_secure_verify'             => @$entityRow['cc_secure_verify'] ? $entityRow['cc_secure_verify'] : null,
            'protection_eligibility'       => @$entityRow['protection_eligibility'] ? $entityRow['protection_eligibility'] : null,
            'cc_approval'                  => @$entityRow['cc_approval'] ? $entityRow['cc_approval'] : null,
            'cc_last4'                     => @$entityRow['cc_last4'] ? $entityRow['cc_last4'] : null,
            'cc_status_description'        => @$entityRow['cc_status_description'] ? $entityRow['cc_status_description'] : null,
            'echeck_type'                  => @$entityRow['echeck_type'] ? $entityRow['echeck_type'] : null,
            'cc_debug_response_serialized' => @$entityRow['cc_debug_response_serialized'] ? $entityRow['cc_debug_response_serialized'] : null,
            'cc_ss_start_month'            => @$entityRow['cc_ss_start_month'] ? $entityRow['cc_ss_start_month'] : null,
            'echeck_account_type'          => @$entityRow['echeck_account_type'] ? $entityRow['echeck_account_type'] : null,
            'last_trans_id'                => @$entityRow['last_trans_id'] ? $entityRow['last_trans_id'] : null,
            'cc_cid_status'                => @$entityRow['cc_cid_status'] ? $entityRow['cc_cid_status'] : null,
            'cc_owner'                     => @$entityRow['cc_owner'] ? $entityRow['cc_owner'] : null,
            'cc_type'                      => @$entityRow['cc_type'] ? $entityRow['cc_type'] : null,
            'po_number'                    => @$entityRow['po_number'] ? $entityRow['po_number'] : null,
            'cc_exp_year'                  => @$entityRow['cc_exp_year'] ? $entityRow['cc_exp_year'] : null,
            'cc_status'                    => @$entityRow['cc_status'] ? $entityRow['cc_status'] : null,
            'echeck_routing_number'        => @$entityRow['echeck_routing_number'] ? $entityRow['echeck_routing_number'] : null,
            'account_status'               => @$entityRow['account_status'] ? $entityRow['account_status'] : null,
            'anet_trans_method'            => @$entityRow['anet_trans_method'] ? $entityRow['anet_trans_method'] : null,
            'cc_debug_response_body'       => @$entityRow['cc_debug_response_body'] ? $entityRow['cc_debug_response_body'] : null,
            'cc_ss_issue'                  => @$entityRow['cc_ss_issue'] ? $entityRow['cc_ss_issue'] : null,
            'echeck_account_name'          => @$entityRow['echeck_account_name'] ? $entityRow['echeck_account_name'] : null,
            'cc_avs_status'                => @$entityRow['cc_avs_status'] ? $entityRow['cc_avs_status'] : null,
            'cc_number_enc'                => @$entityRow['cc_number_enc'] ? $entityRow['cc_number_enc'] : null,
            'cc_trans_id'                  => @$entityRow['cc_trans_id'] ? $entityRow['cc_trans_id'] : null,
            'paybox_request_number'        => @$entityRow['paybox_request_number'] ? $entityRow['paybox_request_number'] : null,
            'address_status'               => @$entityRow['address_status'] ? $entityRow['address_status'] : null,
            'additional_information'       => @$entityRow['additional_information'] ? $entityRow['additional_information'] : null,
        );

        //loop through and assign reg values to base values if base not set
        foreach ($paymentData as $k => &$v) {
            if (!empty($paymentData[$k]) && array_key_exists('base_' . $k, $paymentData) && empty($paymentData['base_' . $k])) {
                $paymentData['base_' . $k] = $v;
            }
        }

        return $paymentData;
    }

    protected function _mapOrderGrid(array $entityRow)
    {
        $gridData = array(
            'entity_id'           => $this->_getOrderIdFromLegacyOrderId($entityRow[self::COL_LEGACY_ORDER_ID]),
            'status'              => @$entityRow['status'] ? $entityRow['status'] : self::DEF_STATUS,
            'store_id'            => @$entityRow['store_id'] ? $entityRow['store_id'] : $this->_store->getId(),
            'store_name'          => $this->_getStoreName(),
            'customer_id'         => $this->_getCustomerId($entityRow),
            'base_grand_total'    => @$entityRow['grand_total'] ? $this->_helper->formatDecimalValue($entityRow['grand_total']) : 0.0000,
            'base_total_paid'     => @$entityRow['base_total_paid'] ? $this->_helper->formatDecimalValue($entityRow['base_total_paid']) : 0.0000,
            'grand_total'         => @$entityRow['grand_total'] ? $this->_helper->formatDecimalValue($entityRow['grand_total']) : 0.0000,
            'total_paid'          => @$entityRow['total_paid'] ? $this->_helper->formatDecimalValue($entityRow['total_paid']) : 0.0000,
            'increment_id'        => $this->_getIncrementIdFromLegacyOrderId($entityRow[self::COL_LEGACY_ORDER_ID]),
            'base_currency_code'  => 'USD',
            'order_currency_code' => 'USD',
            'shipping_name'       => @$entityRow[self::COL_SHIPPING_SAME_BILLING] ?
                $entityRow[self::COL_B_FIRSTNAME] . ' ' . $entityRow[self::COL_B_LASTNAME] :
                $entityRow[self::COL_S_FIRSTNAME] . ' ' . $entityRow[self::COL_S_LASTNAME],
            'billing_name'        => $entityRow[self::COL_B_FIRSTNAME] . ' ' . $entityRow[self::COL_B_LASTNAME],
            'created_at'          => $this->_helper->formatTimestamp(@$entityRow['created_at']),
            'updated_at'          => $this->_helper->formatTimestamp(@$entityRow['updated_at']),
        );

        //loop through and assign reg values to base values if base not set
        foreach ($gridData as $k => &$v) {
            if (!empty($gridData[$k]) && array_key_exists('base_' . $k, $gridData) && empty($gridData['base_' . $k])) {
                $gridData['base_' . $k] = $v;
            }
        }

        return $gridData;
    }

    protected function _mapOrderItem(array $rowData, array $entityRow)
    {
        $itemData = array(
            'item_id'                        => null,
            'order_id'                       => $this->_getOrderIdFromLegacyOrderId($entityRow[self::COL_LEGACY_ORDER_ID]),
            'parent_item_id'                 => null,
            'quote_item_id'                  => null,
            'store_id'                       => @$entityRow['store_id'] ? $entityRow['store_id'] : $this->_store->getId(),
            'created_at'                     => $this->_helper->formatTimestamp(@$entityRow['created_at']),
            'updated_at'                     => $this->_helper->formatTimestamp(@$entityRow['updated_at']),
            'product_id'                     => $this->_productSkusToIds[$rowData[self::COL_SKU]],
            'product_type'                   => null,
            'product_options'                => null,
            'weight'                         => 1,
            'is_virtual'                     => @$entityRow['is_virtual'] ? $entityRow['is_virtual'] : 0,
            'sku'                            => $rowData[self::COL_SKU],
            'name'                           => @$rowData[self::COL_PRODUCT_NAME] ? $rowData[self::COL_PRODUCT_NAME] : $this->_getProductNameFromSku($rowData[self::COL_SKU]),
            'description'                    => @$rowData['description'] ? $rowData['description'] : $this->_getProductDescriptionFromSku($rowData[self::COL_SKU]),
            'applied_rule_ids'               => null,
            'additional_data'                => null,
            'free_shipping'                  => @$rowData['free_shipping'] ? $rowData['free_shipping'] : 0,
            'is_qty_decimal'                 => @$rowData['is_qty_decimal'] ? $rowData['is_qty_decimal'] : 0,
            'no_discount'                    => @$rowData['no_discount'] ? $rowData['no_discount'] : 0,
            'qty_backordered'                => @$rowData['qty_backordered'] ? $rowData['qty_backordered'] : null,
            'qty_canceled'                   => @$rowData['qty_canceled'] ? $rowData['qty_canceled'] : 0.0000,
            'qty_invoiced'                   => @$rowData['qty_invoiced'] ? $rowData['qty_invoiced'] : 0.0000,
            'qty_ordered'                    => @$rowData[self::COL_QTY] ? $rowData[self::COL_QTY] : 1.0000,
            'qty_refunded'                   => @$rowData['qty_refunded'] ? $rowData['qty_refunded'] : 0.0000,
            'qty_shipped'                    => @$rowData['qty_shipped'] ? $rowData['qty_shipped'] : 0.0000,
            'base_cost'                      => @$rowData['base_cost'] ? $rowData['base_cost'] : null,
            'price'                          => @$rowData['price'] ? $rowData['price'] : 0.0000,
            'base_price'                     => @$rowData['base_price'] ? $rowData['base_price'] : 0.0000,
            'original_price'                 => @$rowData['original_price'] ? $rowData['original_price'] : 0.0000,
            'base_original_price'            => @$rowData['base_original_price'] ? $rowData['base_original_price'] : 0.0000,
            'tax_percent'                    => @$rowData['tax_percent'] ? $rowData['tax_percent'] : 0.0000,
            'tax_amount'                     => @$rowData[self::COL_TAX_AMOUNT] ? $rowData[self::COL_TAX_AMOUNT] : 0.0000,
            'base_tax_amount'                => null,
            'tax_invoiced'                   => @$rowData[self::COL_TAX_INVOICED] ? $rowData[self::COL_TAX_INVOICED] : 0.0000,
            'base_tax_invoiced'              => null,
            'discount_percent'               => @$rowData['discount_percent'] ? $rowData['discount_percent'] : 0.0000,
            'discount_amount'                => @$rowData[self::COL_DISCOUNT_AMOUNT] ? $rowData[self::COL_DISCOUNT_AMOUNT] : 0.0000,
            'base_discount_amount'           => null,
            'discount_invoiced'              => 0.0000,
            'base_discount_invoiced'         => null,
            'amount_refunded'                => 0.0000,
            'base_amount_refunded'           => null,
            'row_total'                      => @$rowData[self::COL_ROW_TOTAL] ? $rowData[self::COL_ROW_TOTAL] : 0.0000,
            'base_row_total'                 => null,
            'row_invoiced'                   => @$rowData['row_invoiced'] ? $rowData['row_invoiced'] : 0.0000,
            'base_row_invoiced'              => 0.0000,
            'row_weight'                     => @$rowData['row_weight'] ? $rowData['row_weight'] : 1.0000,
            'base_tax_before_discount'       => null,
            'tax_before_discount'            => null,
            'ext_order_item_id'              => @$rowData['ext_order_item_id'] ? $rowData['ext_order_item_id'] : null,
            'locked_do_invoice'              => null,
            'locked_do_ship'                 => null,
            'price_incl_tax'                 => @$rowData[self::COL_PRICE_INCL_TAX] ? $rowData[self::COL_PRICE_INCL_TAX] : 0.0000,
            'base_price_incl_tax'            => null,
            'row_total_incl_tax'             => @$rowData[self::COL_ROW_TOTAL_INCL_TAX] ? $rowData[self::COL_ROW_TOTAL_INCL_TAX] : $rowData[self::COL_ROW_TOTAL],
            'base_row_total_incl_tax'        => null,
            'hidden_tax_amount'              => 0.0000,
            'base_hidden_tax_amount'         => null,
            'hidden_tax_invoiced'            => null,
            'base_hidden_tax_invoiced'       => null,
            'is_nominal'                     => @$rowData['is_nominal'] ? $rowData['is_nominal'] : 0,
            'tax_canceled'                   => null,
            'hidden_tax_canceled'            => null,
            'tax_refunded'                   => null,
            'base_tax_refunded'              => null,
            'discount_refunded'              => null,
            'base_discount_refunded'         => null,
            'gift_message_id'                => null,
            'gift_message_available'         => null,
            'base_weee_tax_applied_amount'   => 0.0000,
            'base_weee_tax_applied_row_amnt' => 0.0000,
            'weee_tax_applied_amount'        => 0.0000,
            'weee_tax_applied_row_amount'    => 0.0000,
            'weee_tax_applied'               => 'a:0:{}',
            'weee_tax_disposition'           => 0.0000,
            'weee_tax_row_disposition'       => 0.0000,
            'base_weee_tax_disposition'      => 0.0000,
            'base_weee_tax_row_disposition'  => 0.0000,
            'event_id'                       => null,
            'giftregistry_item_id'           => null,
            'gw_id'                          => null,
            'gw_base_price'                  => null,
            'gw_price'                       => null,
            'gw_base_tax_amount'             => null,
            'gw_tax_amount'                  => null,
            'gw_base_price_invoiced'         => null,
            'gw_price_invoiced'              => null,
            'gw_base_tax_amount_invoiced'    => null,
            'gw_tax_amount_invoiced'         => null,
            'gw_base_price_refunded'         => null,
            'gw_price_refunded'              => null,
            'gw_base_tax_amount_refunded'    => null,
            'gw_tax_amount_refunded'         => null,
            'qty_returned'                   => @$rowData['qty_returned'] ? $rowData['qty_returned'] : 0.0000,
        );

        //loop through and assign reg values to base values if base not set
        foreach ($itemData as $k => &$v) {
            if (!empty($itemData[$k]) && array_key_exists('base_' . $k, $itemData) && empty($itemData['base_' . $k])) {
                $itemData['base_' . $k] = $v;
            }
        }

        return $itemData;
    }
}
