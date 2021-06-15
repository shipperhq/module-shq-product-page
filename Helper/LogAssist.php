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

namespace ShipperHQ\ProductPage\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Shipping data helper
 */
class LogAssist
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * LogAssist constructor.
     *
     * @param ScopeConfigInterface $config
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(ScopeConfigInterface $config, \Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     *
     * @param $module
     * @param $logData
     */
    public function postDebug($module, $message, $data, array $context = [])
    {
        $this->logger->debug($this->getMessage($module, $message, $data), $context);
    }

    /**
     *
     * @param $module
     * @param $logData
     */
    public function postInfo($module, $message, $data, array $context = [])
    {
        $this->logger->info($this->getMessage($module, $message, $data), $context);
    }

    /**
     *
     * @param $module
     * @param $logData
     */
    public function postWarning($module, $message, $data, array $context = [])
    {
        $this->logger->warning($this->getMessage($module, $message, $data), $context);
    }

    /**
     *
     * @param $module
     * @param $logData
     */
    public function postCritical($module, $message, $data, array $context = [])
    {
        $this->logger->warning($this->getMessage($module, $message, $data), $context);
    }

    private function getMessage($module, $message, $data)
    {
        $data = is_string($data) ? $data : var_export($data, true);
        return $module . '-- ' . $message . '-- ' . $data;
    }

}
