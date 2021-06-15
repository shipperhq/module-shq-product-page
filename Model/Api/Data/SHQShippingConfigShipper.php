<?php
/**
 * Shipper HQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Server
 * @copyright Copyright (c) 2019 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace ShipperHQ\ProductPage\Model\Api\Data;

use Magento\Framework\DataObject;
use Magento\Store\Model\ScopeInterface;
use \Magento\Framework\App\MutableScopeConfig;
use ShipperHQ\ProductPage\Api\Data\SHQShippingConfigInterface;
use ShipperHQ\ProductPage\Model\Api\AuthorizationShipper;

class SHQShippingConfigShipper extends DataObject implements SHQShippingConfigInterface
{
    /** @var MutableScopeConfig $scopeConfig */
    private $scopeConfig;

    /** @var AuthorizationShipper */
    private $authorizationHelper;

    /**
     * SHQServer constructor.
     * @param MutableScopeConfig $scopeConfig
     * @param AuthorizationShipper $authorizationHelper
     */
    public function __construct(
        MutableScopeConfig $scopeConfig,
        AuthorizationShipper $authorizationHelper
    ) {
        parent::__construct();
        $this->scopeConfig = $scopeConfig;
        $this->authorizationHelper = $authorizationHelper;
    }
    /**
     * @return string
     */
    public function getScope()
    {
        return $this->scopeConfig->getValue('carriers/shipper/environment_scope', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     * @deprecated
     */
    public function getApiKey()
    {
        return $this->scopeConfig->getValue('carriers/shipper/api_key', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function getPublicToken()
    {
        $publicToken = $this->scopeConfig->getValue(AuthorizationShipper::SHIPPERHQ_SERVER_PUBLIC_TOKEN_PATH, ScopeInterface::SCOPE_STORE);
        if (!$publicToken || $this->authorizationHelper->hasCredentialsButNoToken()) {
            $this->authorizationHelper->getSecretToken(); // fetches tokens again
            $this->scopeConfig->clean(); // clears the in-mem config cache
            $publicToken = $this->scopeConfig->getValue(AuthorizationShipper::SHIPPERHQ_SERVER_PUBLIC_TOKEN_PATH, ScopeInterface::SCOPE_STORE);
        }
        return $publicToken;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->scopeConfig->getValue('carriers/shipper/graphql_url', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function getDefaultCountry()
    {
        return $this->scopeConfig->getValue('general/country/default', ScopeInterface::SCOPE_STORE);
    }
}
