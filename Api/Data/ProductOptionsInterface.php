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

namespace ShipperHQ\ProductPage\Api\Data;

use Magento\Directory\Api\Data\CountryInformationInterface;
use ShipperHQ\GraphQL\Types\Input\RMSItem;

/**
 * Interface PaymentDetailsInterface
 * @api
 */
interface ProductOptionsInterface
{
    /**
     * @return string
     */
    public function getSessionId();

    /**
     * @param string $sessionId
     * @return $this
     */
    public function setSessionId($sessionId);

    /**
     * @return string
     */
    public function getPublicToken();

    /**
     * @param string $token
     * @return $this
     */
    public function setPublicToken($token);

    /**
     * @return string
     */
    public function getCountries();

    /**
     * @param string $countries
     * @return $this
     */
    public function setCountries($countries);

    /**
     * @return string
     */
    public function getPostCodes();

    /**
     * @param string $postCodes
     * @return $this
     */
    public function setPostCodes($postCodes);

    /**
     * @return string
     */
    public function getQuoteCurrencyCode();

    /**
     * @param string $currency
     * @return $this
     */
    public function setQuoteCurrencyCode($currency);

    /**
     * @return string
     */
    public function getCart();

    /**
     * @param string $cart
     * @return $this
     */
    public function setCart($cart);
}
