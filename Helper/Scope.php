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

namespace ShipperHQ\ProductPage\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * A helper for determining website/store scope and tracking it through the request.
 * USED AS A SINGLETON, see etc/di.xml
 *
 * Class Scope
 * @package ShipperHQ\ProductPage\Helper
 */
class Scope
{
    const SCOPE_TYPE_DEFAULT = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
    const SCOPE_TYPE_WEBSITE = ScopeInterface::SCOPE_WEBSITES;
    const SCOPE_TYPE_STORE = ScopeInterface::SCOPE_STORES;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var int */
    private $websiteId = null;

    /** @var int */
    private $storeId = null;

    /** @var string */
    private $scopeType = self::SCOPE_TYPE_DEFAULT;

    /**
     * Scope constructor.
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    /**
     * Set the websiteId and/or storeId if either is known and populate remaining information like scope type
     * StoreID takes precedence over WebsiteID
     *
     * @param null $websiteId
     * @param null $storeId
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setScopeByWebsiteAndStore($websiteId = null, $storeId = null)
    {
        if ($storeId !== null && $storeId !== "") {
            $this->scopeType = self::SCOPE_TYPE_STORE;
            $this->storeId = $storeId;
            $this->websiteId = null;
        } elseif ($websiteId !== null && $websiteId !== "") {
            $this->scopeType = self::SCOPE_TYPE_WEBSITE;
            $this->websiteId = $websiteId;
            $this->storeId = null;
        } else {
            $this->scopeType = self::SCOPE_TYPE_DEFAULT;
            $this->storeId = null;
            $this->websiteId = null;
        }
    }

    /*
     * Returns the in-scope website.  If the scopeType is STORE then returns the website the store is a child of
     * If the scopeType is default then returns null.
     */
    public function getWebsiteId()
    {
        switch ($this->getScopeType()) {
            case self::SCOPE_TYPE_STORE:
                $store = $this->storeManager->getStore($this->storeId);
                return $store->getWebsiteId();
            case self::SCOPE_TYPE_WEBSITE:
                return $this->websiteId;
            case self::SCOPE_TYPE_DEFAULT:
            default:
                return null;
        }
    }

    /**
     * @return string
     */
    public function getScopeType()
    {
        return $this->scopeType;
    }

    /**
     * Returns the correct Scope ID for the scope type.  I.E. websiteID if type == website.
     * If Scope Type is default returns NULL
     *
     * @return int|null
     */
    public function getScopeId()
    {
        switch ($this->getScopeType()) {
            case self::SCOPE_TYPE_STORE:
                return $this->storeId;
            case self::SCOPE_TYPE_WEBSITE:
                return $this->websiteId;
            case self::SCOPE_TYPE_DEFAULT:
            default:
                return null;
        }
    }

    public function getAllWebsites()
    {
        return $this->storeManager->getWebsites();
    }

    public function getAllStores()
    {
        return $this->storeManager->getStores();
    }
}
