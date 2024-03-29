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

declare(strict_types=1);

namespace ShipperHQ\ProductPage\Model\Api;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Checkout\Model\Session;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\RegionInformationInterface;
use Magento\Directory\Model\Country\Postcode\ConfigInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote\Item\Processor;
use ShipperHQ\GraphQL\Helpers\Serializer;
use ShipperHQ\GraphQL\Types\Input\RMSCart;
use ShipperHQ\ProductPage\Api\Data\ProductOptionsInterfaceFactory;
use ShipperHQ\ProductPage\Api\Data\SHQShippingConfigInterface;
use ShipperHQ\ProductPage\Api\Data\SHQShippingConfigInterfaceFactory;
use ShipperHQ\ProductPage\Api\ProductOptionsManagementInterface;
use ShipperHQ\ProductPage\Model\Processor\ShipperMapper;

class ProductOptionsManagement implements ProductOptionsManagementInterface
{
    private static $magentoAttributeNames = [
        'name',
        'price',
        'special_price',
        'special_from_date',
        'special_to_date',
        'tax_class_id',
        'image',
        'weight'
    ];

    private static $conditionalDims = [
        'shipperhq_poss_boxes',
        'shipperhq_volume_weight',
        'ship_box_tolerance',
        'ship_separately'
    ];

    private static $stdAttributeNames = [
        'shipperhq_shipping_group',
        'shipperhq_warehouse',
        'shipperhq_availability_date',
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
        'ship_width',
        'shipperhq_location'
    ];

    /**
     * @var SHQShippingConfigInterface
     */
    protected $config;

    /**
     * @var ProductOptionsInterfaceFactory
     */
    private $productOptionsFactory;

    /**
     * @var CountryInformationAcquirerInterface
     */
    private $countryInformation;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Registry
     */
    private $coreRegistry;

    /**
     * @var Processor
     */
    private $itemProcessor;

    /**
     * @var ShipperMapper
     */
    private $productMapper;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Stock
     */
    private $stockHelper;


    /**
     * @var ConfigInterface
     */
    private $postCodesConfig;

    /**
     * Settings constructor.
     * @param ProductOptionsInterfaceFactory $productOptionsFactory
     * @param CountryInformationAcquirerInterface $countryInformation
     * @param ProductRepositoryInterface $productRepository
     * @param Registry $coreRegistry
     * @param Processor $itemProcessor
     * @param Session $checkoutSession
     * @param ShipperMapper $productMapper
     * @param SHQShippingConfigInterfaceFactory $config
     * @param ConfigInterface $postCodesConfig
     */
    public function __construct(
        ProductOptionsInterfaceFactory $productOptionsFactory,
        CountryInformationAcquirerInterface $countryInformation,
        ProductRepositoryInterface $productRepository,
        Registry $coreRegistry,
        Processor $itemProcessor,
        Session $checkoutSession,
        ShipperMapper $productMapper,
        SHQShippingConfigInterfaceFactory $config,
        ConfigInterface $postCodesConfig,
        Stock $stockHelper
    )
    {
        $this->config = $config->create();
        $this->productOptionsFactory = $productOptionsFactory;
        $this->countryInformation = $countryInformation;
        $this->productRepository = $productRepository;
        $this->coreRegistry = $coreRegistry;
        $this->itemProcessor = $itemProcessor;
        $this->productMapper = $productMapper;
        $this->checkoutSession = $checkoutSession;
        $this->stockHelper = $stockHelper;
        $this->postCodesConfig = $postCodesConfig;
    }

    /**
     * @inheritdoc
     */
    public function getOptions($id, $variant, $buyRequest)
    {
        $product = $this->productRepository->getById($id);

        $options = $this->productOptionsFactory->create();
        $options->setSessionId($this->getSessionId());
        $options->setPublicToken($this->config->getPublicToken());
        $options->setCart(
            $this->getCart($product, $buyRequest)
        );

        $options->setQuoteCurrencyCode($this->getCurrency($product));

        if ($variant === 'full') {
            $options->setCountries(\json_encode($this->getCountryList()));
            $options->setPostCodes(\json_encode($this->postCodesConfig->getPostCodes()));
        }

        return $options;
    }

    /**
     * @param $product
     * @param string $buyRequest
     * @return RMSCart
     * @throws \ShipperHQ\GraphQL\Exception\SerializerException
     */
    private function getCart($product, $buyRequest)
    {
        $packageValue = 0;

        $items = $this->getCartItems($product, $buyRequest);

        if(is_string($items)) { // not all options are selected - produces error
            return $items;
        } else {
            $cart = new RMSCart(
                $items,
                $packageValue,
                false
            );

            return Serializer::serialize($cart, 0);
        }
    }

    /**
     * Returns attributes to prefetch for the grouped products.
     * @return string[]
     */
    private function getAttributesForSelect() {
        return array_merge(
            self::$magentoAttributeNames,
            self::$stdAttributeNames,
            self::$conditionalDims
        );
    }

    /**
     * This code prefetches associated products for the grouped products. Only products in stock are loaded.
     * This is needed because by default Magento will load all of the products, including products which are out of stock.
     * It will load all products because Magento\CatalogInventory\Model\Plugin\ProductLinks plugin is registered
     * only for frontend area. So to avoid unexpected side-effects it's probably better to pre-populate the cache
     * like it's done below.
     *
     * @param $product
     */
    private function prefetchAssociatedProducts($product) {
        $associatedProducts = [];

        /** @var Grouped $type */
        $type = $product->getTypeInstance();
        $type->setSaleableStatus($product);

        $collection = $type->getAssociatedProductCollection(
            $product
        )->setFlag('')->addAttributeToSelect(
            $this->getAttributesForSelect()
        )->addFilterByRequiredOptions()->setPositionOrder()->addStoreFilter(
            $type->getStoreFilter($product)
        )->addAttributeToFilter(
            'status',
            ['in' => $type->getStatusFilters($product)]
        );

        $this->stockHelper->addInStockFilterToCollection($collection);

        foreach ($collection as $item) {
            $associatedProducts[] = $item;
        }

        $product->setData('_cache_instance_associated_products', $associatedProducts);
    }

    /**
     * @param $product
     * @param string $buyRequestData
     * @return array|string
     * @throws \ShipperHQ\GraphQL\Exception\SerializerException
     */
    private function getCartItems($product, $buyRequestData)
    {
        $parsed = \json_decode($buyRequestData, true);
        $request = new DataObject();

        foreach ($parsed as $key => $value) {
            $request->setData($key, $value);
        }

        $request->setProduct($product->getId());
        $request->setItem($product->getId());

        if ($product->getTypeId() === 'grouped') {
            $this->prefetchAssociatedProducts($product);
        }

        $cartCandidates = $product->getTypeInstance()->prepareForCartAdvanced($request, $product, 'full');

        /**
         * Error message
         */
        if (is_string($cartCandidates) || $cartCandidates instanceof \Magento\Framework\Phrase) {
            // TODO return error differently
            return (string)$cartCandidates;
        }

        /**
         * If prepare process return one object
         */
        if (!is_array($cartCandidates)) {
            $cartCandidates = [$cartCandidates];
        }

        $result = [];
        $parentItem = null;
        foreach ($cartCandidates as $candidate) {
            // Child items can be sticked together only within their parent
            $stickWithinParent = $candidate->getParentProductId() ? $parentItem : null;
            $candidate->setStickWithinParent($stickWithinParent);

            $item = $this->itemProcessor->init(
                $candidate,
                $request
            );

            $item->setOptions($candidate->getCustomOptions());
            $item->setProduct($candidate);

            $this->itemProcessor->prepare($item, $request, $candidate);

            $rmsItem = $this->productMapper->mapProductDetails($candidate->getId(), $item);
            $rmsItem->setItemId($candidate->getId());

            if (!$parentItem) {
                $parentItem = $rmsItem;
            }

            if ($parentItem && $candidate->getParentProductId()) {
                // for configurable & bundled
                $children = $parentItem->getItems();
                $children[] = $rmsItem;
                $parentItem->setItems($children);
            } else {
                $result[] = $rmsItem;
            }
        }

        return $result;
    }

    private function getSessionId()
    {
        // TODO: do we need to pass something unique here at all?
        return 'PP_SESSION_ID';
    }

    /**
     * @return array
     */
    private function getCountryList()
    {
        $result = [];
        $countries = $this->countryInformation->getCountriesInfo();

        foreach ($countries as $country) {
            $entry = [
                $country->getTwoLetterAbbreviation(),
                $country->getFullNameLocale(),
            ];

            $regions = $this->getRegionsList($country->getAvailableRegions());
            if ($regions) {
                $entry[] = $regions;
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param RegionInformationInterface[] $regions
     */
    private function getRegionsList($regions)
    {
        if (!empty($regions)) {
            $result = [];

            foreach ($regions as $region) {
                $result[] = [
                    $region->getCode(),
                    $region->getName()
                ];
            }

            return $result;
        }

        return null;
    }

    /**
     * @param Product $product
     * @return string|void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getCurrency($product)
    {
        $currency = $this->checkoutSession->getQuote()->getCurrency();
        if ($currency) {
            $currencyCode = $currency->getQuoteCurrencyCode();
            if (!empty($currencyCode)) {
                return $currencyCode;
            }
        }

        return $product->getStore()->getCurrentCurrencyCode();
    }
}
