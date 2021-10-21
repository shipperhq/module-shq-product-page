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

namespace ShipperHQ\ProductPage\Api\Data;

interface ProductPageConfigInterface
{
    /**
     * @return string
     */
    public function getJsBundleUrl();

    /**
     * @return string
     */
    public function getCssBundleUrl();

    /**
     * @return string
     */
    public function getMaximumAllowedQty();

    /**
     * @return string
     */
    public function getProductId();
}
