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

use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class Config
{
    /**
     * Copy of the config loaded into this process's memory
     * @var MutableScopeConfigInterface
     */
    private $localConfig;

    /**
     * Class used to write persistent config
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * Cache manager used to flush cache
     * @var Manager
     */
    private $cacheManager;

    /** @var ConfigFactory */
    private $configFactory;

    /** @var boolean */
    private $isConfigCacheCleanScheduled = false;

    /**
     * Config constructor.
     * @param MutableScopeConfigInterface $localConfig
     * @param WriterInterface $configWriter
     * @param Manager $cacheManager
     */
    public function __construct(
        MutableScopeConfigInterface $localConfig,
        WriterInterface $configWriter,
        Manager $cacheManager,
        ConfigFactory $configFactory
    ) {
        $this->localConfig = $localConfig;
        $this->configWriter = $configWriter;
        $this->cacheManager = $cacheManager;
        $this->configFactory = $configFactory;
    }

    /**
     * Wraps WriterInterface->save() but also schedules the config cache to be cleaned
     *
     * @param $path
     * @param $value
     * @param null $scope
     * @param null $scopeId
     */
    public function writeToConfig($path, $value, $scope = null, $scopeId = null)
    {
        $currentValue = $this->getConfigValue(...array_filter([$path, $scope, $scopeId]));
        if ($value === $currentValue) {
            return;
        }

        $args = array_filter([$path, $value, $scope, $scopeId], function ($e) {
            return $e !== null;
        });
        $this->configWriter->save(...$args);
        $this->localConfig->setValue(...$args);
        $this->scheduleConfigCacheClean();
    }

    /**
     * Wraps WriterInterface->delete() but also schedules the config cache to be cleaned
     *
     * @param $path
     * @param null $scope
     * @param null $scopeId
     */
    public function deleteFromConfig($path, $scope = null, $scopeId = null)
    {
        // TODO: Short circuit if delete is not needed

        $args = array_filter([$path, $scope, $scopeId]);
        $this->configWriter->delete(...$args);
        array_splice($args, 1, 0, [null]); // Insert a null Value field at position 1, move the other elements down
        $this->localConfig->setValue(...$args); // Be setting to null we indicate value should be fetched again
        $this->scheduleConfigCacheClean();
    }

    /**
     * Wraps MutableScopeConfigInterface->getValue except allows for smartly invalidating config cache
     * @param $path
     * @param null $scopeType
     * @param null $scopeCode
     * @return mixed
     */
    public function getConfigValue($path, $scopeType = null, $scopeCode = null)
    {
        $args = array_filter([$path, $scopeType, $scopeCode]); // drop any null arguments

        return $this->localConfig->getValue(...$args);
    }

    public function getConfigFlag($configField)
    {
        return $this->localConfig->isSetFlag($configField, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Try to use sparingly. Will flush the cache immediately if there are uncommitted changes.
     */
    public function runScheduledCleaningNow()
    {
        $this->cleanConfigCacheIfScheduled();
    }

    /**
     * Gets all config specifically assigned to the website scope (does not include config inherited from default)
     *
     * @param int|null $website
     * @return array
     */
    public function getSHQConfigForWebsiteScope($website, $section = 'carriers/shqserver')
    {
        $configDataObject = $this->configFactory->create(
            [
                'data' => [
                    'section' => $section,
                    'website' => $website
                ],
            ]
        );
        return $configDataObject->load();
    }

    /**
     * @return array
     */
    public function getSHQConfigForDefaultScope()
    {
        return $this->getSHQConfigForWebsiteScope(null);
    }

    /**
     * @return Config
     */
    private function scheduleConfigCacheClean(): Config
    {
        $this->isConfigCacheCleanScheduled = true;
        return $this;
    }

    /**
     * Cleans the config if a change has been made since the last read
     * @return Config
     */
    private function cleanConfigCacheIfScheduled(): Config
    {
        if ($this->isConfigCacheCleanScheduled) {
            $this->localConfig->clean();
            $this->cacheManager->clean(["config"]);
            $this->isConfigCacheCleanScheduled = false;
        }
        return $this;
    }
}
