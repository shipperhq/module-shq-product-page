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

use ShipperHQ\GraphQL\Helpers\Serializer;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session;
use Magento\Directory\Api\CountryInformationAcquirerInterface;
use Magento\Directory\Api\Data\RegionInformationInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote\Item\Processor;
use ShipperHQ\GraphQL\Types\Input\RMSCart;
use ShipperHQ\ProductPage\Api\Data\ProductOptionsInterfaceFactory;
use ShipperHQ\ProductPage\Api\Data\SHQShippingConfigInterface;
use ShipperHQ\ProductPage\Api\Data\SHQShippingConfigInterfaceFactory;
use ShipperHQ\ProductPage\Api\ProductOptionsManagementInterface;
use ShipperHQ\ProductPage\Model\Processor\ShipperMapper;

class ProductOptionsManagement implements ProductOptionsManagementInterface
{
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
     * Settings constructor.
     * @param ProductOptionsInterfaceFactory $productOptionsFactory
     * @param CountryInformationAcquirerInterface $countryInformation
     * @param ProductRepositoryInterface $productRepository
     * @param Registry $coreRegistry
     * @param Processor $itemProcessor
     * @param Session $checkoutSession
     * @param ShipperMapper $productMapper
     * @param SHQShippingConfigInterfaceFactory $config
     */
    public function __construct(
        ProductOptionsInterfaceFactory $productOptionsFactory,
        CountryInformationAcquirerInterface $countryInformation,
        ProductRepositoryInterface $productRepository,
        Registry $coreRegistry,
        Processor $itemProcessor,
        Session $checkoutSession,
        ShipperMapper $productMapper,
        SHQShippingConfigInterfaceFactory $config
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
