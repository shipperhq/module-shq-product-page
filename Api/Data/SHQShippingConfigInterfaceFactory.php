<?php

namespace ShipperHQ\ProductPage\Api\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Factory class for @see \Magento\Customer\Api\Data\AddressInterface
 */
class SHQShippingConfigInterfaceFactory
{
    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager = null;

    /**
     * Instance name to create
     *
     * @var string
     */
    protected $_instanceName = null;

    /**
     * Factory constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(\Magento\Framework\ObjectManagerInterface $objectManager, ScopeConfigInterface $scopeConfig)
    {
        $this->_objectManager = $objectManager;
        if($scopeConfig->getValue('carriers/shipper/active', ScopeInterface::SCOPE_STORE)) {
            $this->_instanceName = '\\ShipperHQ\\ProductPage\\Model\\Api\\Data\\SHQShippingConfigShipper';
        } else {
            $this->_instanceName = '\\ShipperHQ\\ProductPage\\Model\\Api\\Data\\SHQShippingConfig';
        }
    }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     * @return \ShipperHQ\ProductPage\Api\Data\SHQShippingConfigInterface
     */
    public function create(array $data = [])
    {
        return $this->_objectManager->create($this->_instanceName, $data);
    }
}
