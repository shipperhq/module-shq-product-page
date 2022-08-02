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
namespace ShipperHQ\ProductPage\Model\Processor;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use ShipperHQ\GraphQL\Types\Input\RMSAttribute;
use ShipperHQ\GraphQL\Types\Input\RMSCart;
use ShipperHQ\GraphQL\Types\Input\RMSCustomer;
use ShipperHQ\GraphQL\Types\Input\RMSCustomerInterface;
use ShipperHQ\GraphQL\Types\Input\RMSDestination;
use ShipperHQ\GraphQL\Types\Input\RMSItem;
use ShipperHQ\GraphQL\Types\Input\RMSRatingInfo;
use ShipperHQ\GraphQL\Types\Input\RMSSiteDetails;
use ShipperHQ\WS;
use ShipperHQ\WS\Rate\Request;

class ShipperMapper
{
    private static $ecommerceType = 'magento';

    /** @var $useDefault */
    private static $useDefault = '--- Use Default ---';

    private static $dim_group = 'shipperhq_dim_group';
    private static $conditional_dims = [
        'shipperhq_poss_boxes',
        'shipperhq_volume_weight',
        'ship_box_tolerance',
        'ship_separately'
    ];

    private static $stdAttributeNames = [
        'shipperhq_shipping_group',
        'shipperhq_warehouse',
        'shipperhq_post_shipping_group',
        'shipperhq_location',
        'shipperhq_royal_mail_group',
        'shipperhq_shipping_qty',
        'shipperhq_shipping_fee',
        'shipperhq_additional_price',
        'freight_class',
        'shipperhq_nmfc_class',
        'shipperhq_nmfc_sub',
        'shipperhq_handling_fee',
        'shipperhq_carrier_code',
        'shipperhq_volume_weight',
        'shipperhq_declared_value',
        'ship_separately',
        'shipperhq_dim_group',
        'shipperhq_poss_boxes',
        'shipperhq_master_boxes',
        'ship_box_tolerance',
        'must_ship_freight',
        'packing_section_name',
        'ship_height',
        'ship_length',
        'ship_width'
    ];

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\App\ProductMetadata
     */
    private $productMetadata;

    /**
     * @var \ShipperHQ\ProductPage\LogAssist
     */
    private $shipperLogger;

    /**
     * @var Request\InfoRequestFactory
     */
    private $infoRequestFactory;

    /**
     * @var WS\Shared\CredentialsFactory
     */
    private $credentialsFactory;

    /**
     * @var WS\Shared\SiteDetailsFactory
     */
    private $siteDetailsFactory;

    /**
     * @var \Magento\Framework\HTTP\Header
     */
    private $httpHeader;

    /**
     * @var \Magento\Customer\Model\GroupFactory
     */
    private $groupFactory;

    /**
     * @var TaxHelper
     */
    private $taxHelper;

    /**
     * ShipperMapper constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @param \ShipperHQ\ProductPage\Helper\LogAssist $shipperLogger
     * @param Request\InfoRequestFactory $infoRequestFactory
     * @param WS\Shared\CredentialsFactory $credentialsFactory
     * @param WS\Shared\SiteDetailsFactory $siteDetailsFactory
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\HTTP\Header $httpHeader
     * @param \Magento\Customer\Model\GroupFactory $groupFactory
     * @param TaxHelper $taxHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \ShipperHQ\ProductPage\Helper\LogAssist $shipperLogger,
        \ShipperHQ\WS\Rate\Request\InfoRequestFactory $infoRequestFactory,
        \ShipperHQ\WS\Shared\CredentialsFactory $credentialsFactory,
        \ShipperHQ\WS\Shared\SiteDetailsFactory $siteDetailsFactory,
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\HTTP\Header $httpHeader,
        \Magento\Customer\Model\GroupFactory $groupFactory,
        TaxHelper $taxHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->productMetadata = $productMetadata;
        $this->shipperLogger = $shipperLogger;
        $this->infoRequestFactory = $infoRequestFactory;
        $this->credentialsFactory = $credentialsFactory;
        $this->siteDetailsFactory = $siteDetailsFactory;
        $this->storeManager = $context->getStoreManager();
        $this->httpHeader = $httpHeader;
        $this->groupFactory = $groupFactory;
        $this->taxHelper = $taxHelper;
        $this->storeManager = $storeManager;
    }

    /**
     * Return site specific information
     *
     * @return array
     */
    public function getSiteDetails($storeId = null, $ipAddress = null)
    {
        $edition = $this->productMetadata->getEdition();
        $url = $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        $mobilePrepend = $this->isMobile($this->httpHeader->getHttpUserAgent()) ? 'm' : '';

        $siteDetails = $this->siteDetailsFactory->create([
            'ecommerceCart' => 'Magento 2 ' . $edition,
            'ecommerceVersion' => $this->scopeConfig->getValue('carriers/shqserver/magento_version', 'store', $storeId),
            'websiteUrl' => $url,
            'environmentScope' => $this->scopeConfig->getValue(
                'carriers/shqserver/environment_scope',
                'store',
                $storeId
            ),
            'appVersion' => $this->scopeConfig->getValue('carriers/shqserver/extension_version', 'store', $storeId),
            'ipAddress' => $ipAddress === null ? $mobilePrepend : $mobilePrepend . $ipAddress
        ]);
        return $siteDetails;
    }

    /**
     * Return credentials for ShipperHQ login
     *
     * @return array
     */
    public function getCredentials($storeId = null)
    {
        $credentials = $this->credentialsFactory->create([
            'apiKey' => $this->scopeConfig->getValue('carriers/shqserver/api_key', 'store', $storeId),
            'password' => $this->scopeConfig->getValue('carriers/shqserver/password', 'store', $storeId)
        ]);

        return $credentials;
    }

    /**
     * Set up values for ShipperHQ getAllowedMethods()
     *
     * @return string
     */
    public function getCredentialsTranslation($storeId = null, $ipAddress = null)
    {
        $shipperHQRequest = $this->infoRequestFactory->create();
        $shipperHQRequest->setCredentials($this->getCredentials($storeId));
        $shipperHQRequest->setSiteDetails($this->getSiteDetails($storeId, $ipAddress));
        return $shipperHQRequest;
    }

    /**
     * Gets credentials from all websites/stores in Magento
     *
     * @return array
     */
    public function getAllCredentialsTranslation()
    {
        $credentialsPerStore = [];
        $allStores = $this->storeManager->getStores();

        foreach ($allStores as $store) {
            $credentials = $this->getCredentialsTranslation($store->getStoreId());

            if ($credentials != null) {
                $apiKey = $credentials->getCredentials()->getApiKey();

                if (!array_key_exists($apiKey, $credentialsPerStore)) {
                    $credentialsPerStore[$apiKey] = $credentials;
                }
            }
        }

        return $credentialsPerStore;
    }

    private function isMobile($data)
    {
        $uaSignatures = '/(nokia|iphone|android|motorola|^mot\-|softbank|foma|docomo|kddi|up\.browser|up\.link|'
            . 'htc|dopod|blazer|netfront|helio|hosin|huawei|novarra|CoolPad|webos|techfaith|palmsource|'
            . 'blackberry|alcatel|amoi|ktouch|nexian|samsung|^sam\-|s[cg]h|^lge|ericsson|philips|sagem|wellcom|'
            . 'bunjalloo|maui|symbian|smartphone|mmp|midp|wap|phone|windows ce|iemobile|^spice|^bird|^zte\-|longcos|'
            . 'pantech|gionee|^sie\-|portalmmm|jig\s browser|hiptop|^ucweb|^benq|haier|^lct|opera\s*mobi|opera\*mini|'
            . '320x320|240x320|176x220)/i';

        if (preg_match($uaSignatures, $data)) {
            return true;
        }
        $mobile_ua = strtolower(substr((string) $data, 0, 4));
        $mobile_agents = [
            'w3c ',
            'acs-',
            'alav',
            'alca',
            'amoi',
            'audi',
            'avan',
            'benq',
            'bird',
            'blac',
            'blaz',
            'brew',
            'cell',
            'cldc',
            'cmd-',
            'dang',
            'doco',
            'eric',
            'hipt',
            'inno',
            'ipaq',
            'java',
            'jigs',
            'kddi',
            'keji',
            'leno',
            'lg-c',
            'lg-d',
            'lg-g',
            'lge-',
            'maui',
            'maxo',
            'midp',
            'mits',
            'mmef',
            'mobi',
            'mot-',
            'moto',
            'mwbp',
            'nec-',
            'newt',
            'noki',
            'oper',
            'palm',
            'pana',
            'pant',
            'phil',
            'play',
            'port',
            'prox',
            'qwap',
            'sage',
            'sams',
            'sany',
            'sch-',
            'sec-',
            'send',
            'seri',
            'sgh-',
            'shar',
            'sie-',
            'siem',
            'smal',
            'smar',
            'sony',
            'sph-',
            'symb',
            't-mo',
            'teli',
            'tim-',
            'tosh',
            'tsm-',
            'upg1',
            'upsi',
            'vk-v',
            'voda',
            'wap-',
            'wapa',
            'wapi',
            'wapp',
            'wapr',
            'webc',
            'winw',
            'winw',
            'xda ',
            'xda-'
        ];

        if (in_array($mobile_ua, $mobile_agents)) {
            return true;
        }
        return false;
    }

    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    /**
     * @param RateRequest $request
     * @return RMSRatingInfo
     * @throws \ShipperHQ\GraphQL\Exception\SerializerException
     */
    public function mapRateRequest(RateRequest $request, $remoteIp)
    {
        $rateRequestType = new RMSRatingInfo(
            $this->mapCartDetails($request),
            $this->mapDestination($request),
            $this->mapCustomer($request),
            'STD',
            $this->mapSiteDetails($request, $remoteIp)
        );

        return $rateRequestType;
    }

    /**
     * @param RateRequest $request
     * @return RMSCart
     * @throws \ShipperHQ\GraphQL\Exception\SerializerException
     */
    private function mapCartDetails(RateRequest $request)
    {
        $items = (array)$request->getAllItems(); // Coerce into being an array

        $packageValue = $request->getPackageValue();
        if (!$packageValue) {
            $packageValue = $request->getPackageValueWithDiscount();
        }

        $cartType = new RMSCart(
            [],
            $packageValue,
            (bool)$request->getFreeShipping()
        );

        $childItems = [];
        foreach ($items as $k => $item) {
            if ($item->getParentItemId()) {
                $childItems[$item->getParentItemId()][] = $item;
            }
        }

        $cartItems = [];
        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($items as $k => $item) {
            if ($item->getParentItemId()) {
                continue;
            }

            $type = $item->getProductType() ?: $item->getProduct()->getTypeId();

            $itemData = $this->mapProductDetails($k, $item);

            switch ($type) {
                case 'configurable':
                case 'bundle':
                    $children = [];
                    if (isset($childItems[$item->getItemId()])) {
                        foreach ($childItems[$item->getItemId()] as $childItem) {
                            $children[] = $this->mapProductDetails($k, $childItem);
                        }
                    }

                    $itemData->setItems($children);
                    break;
            }

            $cartItems[] = $itemData;
        }

        $cartType->setItems($cartItems);

        return $cartType;
    }

    /**
     * @param $id
     * @param Item $item
     * @return RMSItem
     * @throws \ShipperHQ\GraphQL\Exception\SerializerException
     */
    public function mapProductDetails($id, Item $item)
    {
        $type = $item->getProductType() ?: $item->getProduct()->getTypeId();

        if ($this->taxHelper->discountTax() && $item->getTaxPercent() > 0) {
            $discountAmount = round($item->getDiscountAmount() / ($item->getTaxPercent()/100+1), 2);
            $baseDiscountAmount = round($item->getBaseDiscountAmount() / ($item->getTaxPercent()/100+1), 2);
        } else {
            $discountAmount = $item->getDiscountAmount();
            $baseDiscountAmount = $item->getBaseDiscountAmount();
        }
        $fixedPrice = $item->getProduct()->getPriceType() == \Magento\Bundle\Model\Product\Price::PRICE_TYPE_FIXED;
        $fixedWeight = (bool)$item->getProduct()->getWeightType() == 1;

        $realQty = $item->getQty();
        $adjustedQty = ($realQty < 1 && $realQty > 0) ? 1 : $realQty;
        $weight = (float)$item->getWeight();

        if ($realQty !== $adjustedQty) {
            $weight = $weight * $realQty;

            $this->shipperLogger->postInfo(
                'ShipperHQ_Server',
                'Item quantity is decimal and less than 1, rounding quantity up to 1.' .
                'Setting weight to fractional value',
                'SKU: ' . $item->getSku() . ' Weight: ' . $weight
            );
        }

        $itemType = new RMSItem(
            $item->getId(),
            $item->getSku(),
            $item->getName(),
            $item->getPrice() ? (float)$item->getPrice() : 0,
            $weight,
            $adjustedQty,
            strtoupper((string) $type),
            $item->getPriceInclTax() ? (float)$item->getPriceInclTax() : 0,
            (bool)$item->getFreeShipping(),
            (bool)$fixedPrice,
            (bool)$fixedWeight
        );

        $usingNonBaseCurrency = $item->getBasePrice() !== $item->getPrice();
        $hasDiscount = $discountAmount > 0 || $baseDiscountAmount > 0;

        if ($usingNonBaseCurrency) {
            $itemType->setBasePrice($item->getBasePrice() ? (float)$item->getBasePrice() : 0)
                ->setTaxInclBasePrice($item->getBasePriceInclTax() ? (float)$item->getBasePriceInclTax() : 0);
        }

        if ($hasDiscount) {
            $itemType->setDiscountPercent((float)$item->getDiscountPercent())
                ->setDiscountedStorePrice((float)($item->getPrice() - ($discountAmount / $realQty)))
                ->setDiscountedTaxInclStorePrice(
                    (float)($item->getPrice() -
                        ($discountAmount / $realQty) +
                        ($item->getTaxAmount() / $realQty))
                );

            if ($usingNonBaseCurrency) {
                $itemType->setDiscountedBasePrice((float)($item->getBasePrice() - ($baseDiscountAmount / $realQty)))
                    ->setDiscountedTaxInclBasePrice(
                        (float)($item->getBasePrice() -
                            ($baseDiscountAmount / $realQty) +
                            ($item->getBaseTaxAmount() / $realQty))
                    );
            }
        }

        $attributes = $this->mapProductAttributes($item);
        if ($attributes) {
            $itemType->setAttributes($attributes);
        }

        return $itemType;
    }

    /**
     * @param RateRequest $request
     * @return RMSDestination
     */
    private function mapDestination(RateRequest $request)
    {
        $region = $request->getDestRegionCode();
        if ($region === null) { //SHQ16-2098
            $region = "";
        }
        $street = explode("\n", (string) $request->getDestStreet());
        $street1 = array_shift($street);
        $street2 = implode(" ", $street);

        $destinationType = new RMSDestination(
            $request->getDestCountryId() === null ? '' : $request->getDestCountryId(),
            $region,
            $request->getDestCity() === null ? '' : $request->getDestCity(),
            $street1 !== null && is_string($street1) ? $street1 : '',
            !empty($street2) ? $street2 : '',
            $request->getDestPostcode() === null ? '' : $request->getDestPostcode()
        );

        return $destinationType;
    }

    /**
     * @param RateRequest $request
     * @return RMSCustomerInterface
     */
    public function mapCustomer(RateRequest $request)
    {
        $items = $request->getAllItems();
        $customerGroupId = 0;
        if (!empty($items)) {
            $customerGroupId = $items[0]->getQuote()->getCustomerGroupId();
        }

        $group = $this->groupFactory->create()->load($customerGroupId);

        $customerType = new RMSCustomer(
            $group->getCustomerGroupCode()
        );

        return $customerType;
    }

    /**
     * @param RateRequest $request
     * @param             $remoteIp
     *
     * @return RMSSiteDetails
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function mapSiteDetails(RateRequest $request, $remoteIp)
    {
        $storeId = $request->getStoreId();
        $edition = $this->productMetadata->getEdition();
        $url = $this->storeManager->getStore($storeId)->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_LINK);
        $isAdminOrder = $remoteIp == null;
        $eCommercePlatform = $isAdminOrder ? 'Magento 2 ' . $edition : 'Magento 2 ' . $edition . ' Enhanced Checkout';
        //$mobilePrepend = $this->isMobile($this->httpHeader->getHttpUserAgent()) ? 'm' : '';

        $siteDetails = new RMSSiteDetails(
            $this->scopeConfig->getValue('carriers/shqserver/extension_version', 'store', $storeId),
            $eCommercePlatform,
            $this->scopeConfig->getValue('carriers/shqserver/magento_version', 'store', $storeId),
            $url,
            $remoteIp
        );

        return $siteDetails;
    }

    /**
     * Reads attributes from the item
     *
     * @param $reqdAttributeNames
     * @param $item
     * @return array
     */
    private function mapProductAttributes($item)
    {
        $attributes = [];
        $product = $item->getProduct();
        $reqdAttributeNames = self::$stdAttributeNames;
        if ($product->getResource()->getAttribute(self::$dim_group) && $product->getAttributeText(self::$dim_group) != '') {
            $reqdAttributeNames = array_diff(self::$stdAttributeNames, self::$conditional_dims);
        }

        foreach ($reqdAttributeNames as $attributeName) {
            $attribute = $product->getResource()->getAttribute($attributeName);
            if ($attribute) {
                $attributeType = $attribute->getFrontendInput();
            } else {
                continue;
            }
            if ($attributeType == 'select' || $attributeType == 'multiselect') {
                $attributeString = $product->getData($attribute->getAttributeCode());
                $attributeValue = explode(',', (string) $attributeString);
                if (is_array($attributeValue)) {
                    $valueString = [];
                    foreach ($attributeValue as $aValue) {
                        $admin_value = $attribute->setStoreId(0)->getSource()->getOptionText($aValue);
                        // MNB-1634 getOptionsText may return an array in some scenarios -- see vendor/magento/module-eav/Model/Entity/Attribute/Source/Table.php
                        // and Don't sent HTML in request. Convert to actual character
                        if (is_array($admin_value)) {
                            $valueString = array_merge(array_map("html_entity_decode", $admin_value), $valueString);
                        } else {
                            $valueString[] = html_entity_decode($admin_value);
                        }
                    }
                    $attributeValue = implode('#', $valueString);
                } else {
                    $attributeValue = $attribute->setStoreId(0)->getSource()->getOptionText($attributeValue);
                }
            } else {
                $attributeValue = $product->getData($attributeName);
            }

            if (!empty($attributeValue) && false === strpos($attributeValue, self::$useDefault)) {
                $attributes[] = new RMSAttribute(
                    $attributeName,
                    $attributeValue
                );
            }
        }

        return $attributes;
    }
}
