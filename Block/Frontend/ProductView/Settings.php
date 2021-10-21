<?php
/**
 * Shipper HQ
 *
 * @category ShipperHQ
 * @package ShipperHQ_Client
 * @copyright Copyright (c) 2019 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */

namespace ShipperHQ\ProductPage\Block\Frontend\ProductView;

use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Context;
use ShipperHQ\ProductPage\Api\Data\ProductPageConfigInterface;
use ShipperHQ\ProductPage\Api\Data\SHQShippingConfigInterface;
use ShipperHQ\ProductPage\Api\Data\SHQShippingConfigInterfaceFactory;

class Settings extends AbstractBlock
{
    /**
     * @var SHQShippingConfigInterface
     */
    protected $config;

    /**
     * @var ProductPageConfigInterface
     */
    private $productPageConfig;

    /**
     * Settings constructor.
     * @param Context $context
     * @param SHQShippingConfigInterfaceFactory $config
     * @param ProductPageConfigInterface $productPageConfig
     */
    public function __construct(
        Context $context,
        SHQShippingConfigInterfaceFactory $config,
        ProductPageConfigInterface $productPageConfig
    )
    {
        parent::__construct($context);
        $this->config = $config->create();
        $this->productPageConfig = $productPageConfig;
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        $data = array(
            'defaultCountry' => $this->config->getDefaultCountry(),
            'endpoint' => $this->config->getEndpoint(),
            'scope' => $this->config->getScope(),
            'jsBundleUrl' => $this->productPageConfig->getJsBundleUrl(),
            'cssBundleUrl' => $this->productPageConfig->getCssBundleUrl(),
            'productId' => $this->productPageConfig->getProductId(),
            'maximumAllowedQty' => (int) $this->productPageConfig->getMaximumAllowedQty(),
        );

        return "<script>\n" .
            "sessionStorage.setItem('shq-ppse-settings', '" . \json_encode($data) . "');\n" .
            "</script>" .
            '<div id="shqPPSE"></div><div data-bind=\'mageInit: {"ShipperHQ_ProductPage/js/loader": {}}\'></div>';

    }
}
