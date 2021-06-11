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

use Magento\Framework\Model\AbstractModel;
use ShipperHQ\ProductPage\Api\Data\ProductOptionsInterface;

class ProductOptions extends AbstractModel implements ProductOptionsInterface
{
    /**
     * @inheritdoc
     */
    public function setSessionId($sessionId)
    {
        return $this->setData('sessionId', $sessionId);
    }

    /**
     * @inheritdoc
     */
    public function getSessionId()
    {
        return $this->getData('sessionId');
    }

    /**
     * @inheritdoc
     */
    public function getPublicToken()
    {
        return $this->getData('publicToken');
    }

    /**
     * @inheritdoc
     */
    public function setPublicToken($token)
    {
        return $this->setData('publicToken', $token);
    }

    /**
     * @inheritdoc
     */
    public function getCountries()
    {
        return $this->getData('countries');
    }

    /**
     * @inheritdoc
     */
    public function setCountries($countries)
    {
        return $this->setData('countries', $countries);
    }

    /**
     * @inheritdoc
     */
    public function getCart()
    {
        return $this->getData('cart');
    }

    /**
     * @inheritdoc
     */
    public function setCart($cart)
    {
        return $this->setData('cart', $cart);
    }

    /**
     * @inheritdoc
     */
    public function getQuoteCurrencyCode()
    {
        return $this->getData('quoteCurrencyCode');
    }

    /**
     * @inheritdoc
     */
    public function setQuoteCurrencyCode($currency)
    {
        return $this->setData('quoteCurrencyCode', $currency);
    }
}
