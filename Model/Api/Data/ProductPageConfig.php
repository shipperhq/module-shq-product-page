<?php
/**
 * Shipper HQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Server
 * @copyright Copyright (c) 2020 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace ShipperHQ\ProductPage\Model\Api\Data;

use Magento\Framework\App\Config;
use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;
use ShipperHQ\ProductPage\Api\Data\ProductPageConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;

class ProductPageConfig implements ProductPageConfigInterface
{
    /**
     * Cookie names for bundle overrides
     */
    const JS_BUNDLE_OVERRIDE_COOKIE_NAME = 'shq_pp_js_bundle';
    const CSS_BUNDLE_OVERRIDE_COOKIE_NAME = 'shq_pp_css_bundle';

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var Config $scopeConfig
     */
    private $config;

    /**
     * @var Registry
     */
    private $coreRegistry;

    /**
     * ProductPageConfig constructor.
     * @param Config $config
     * @param CookieManagerInterface $cookieManager
     */
    public function __construct(
        Config $config,
        CookieManagerInterface $cookieManager,
        Registry $coreRegistry
    )
    {
        $this->config = $config;
        $this->cookieManager = $cookieManager;
        $this->coreRegistry = $coreRegistry;
    }


    /**
     * @return string
     */
    public function getJsBundleUrl()
    {
        $override = $this->cookieManager->getCookie(self::JS_BUNDLE_OVERRIDE_COOKIE_NAME);
        if ($override) {
            return $override;
        }

        return $this->config->getValue('carriers/shqserver/pp_js_bundle_url', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function getCssBundleUrl()
    {
        $override = $this->cookieManager->getCookie(self::CSS_BUNDLE_OVERRIDE_COOKIE_NAME);
        if ($override) {
            return $override;
        }

        return $this->config->getValue('carriers/shqserver/pp_css_bundle_url', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function getMaximumAllowedQty()
    {
        return $this->config->getValue('carriers/shqserver/pp_maximum_allowed_qty', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return null|string
     */
    public function getProductId() {
        $product = $this->coreRegistry->registry('product');
        return $product ? $product->getId() : null;
    }
}
