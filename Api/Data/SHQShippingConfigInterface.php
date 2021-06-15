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

namespace ShipperHQ\ProductPage\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

interface SHQShippingConfigInterface extends ExtensibleDataInterface
{
    /**
     * @return string
     */
    public function getScope();

    /**
     * @return string
     * @deprecated
     */
    public function getApiKey();

    /**
     * @return string
     */
    public function getPublicToken();

    /**
     * @return string
     */
    public function getDefaultCountry();

    /**
     * @return string
     */
    public function getEndpoint();
}
