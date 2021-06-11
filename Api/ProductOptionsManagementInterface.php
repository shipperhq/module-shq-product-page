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

namespace ShipperHQ\ProductPage\Api;

use ShipperHQ\ProductPage\Api\Data\ProductOptionsInterface;

interface ProductOptionsManagementInterface
{
    /**
     * @param string $id
     * @param string $variant
     * @param string $buyRequest
     * @return ProductOptionsInterface
     */
    public function getOptions($id, $variant, $buyRequest);
}
