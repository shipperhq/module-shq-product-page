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

namespace ShipperHQ\ProductPage\Model\Api;

use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use ShipperHQ\GraphQL\Client\GraphQLClient;
use ShipperHQ\GraphQL\Helpers\LoggingHelper;
use ShipperHQ\GraphQL\Response\CreateSecretToken;
use ShipperHQ\ProductPage\Helper\Config;
use ShipperHQ\ProductPage\Helper\ConfigShipper;
use ShipperHQ\ProductPage\Helper\LogAssist;
use ShipperHQ\ProductPage\Helper\Scope;

/**
 * Shipping data helper (module-shipper)
 */
class AuthorizationShipper
{
    const SHIPPERHQ_ENDPOINT_PATH = 'carriers/shipper/graphql_url';
    const SHIPPERHQ_TIMEOUT_PATH = 'carriers/shipper/ws_timeout';
    const SHIPPERHQ_SERVER_API_KEY_PATH = 'carriers/shipper/api_key';
    const SHIPPERHQ_SERVER_AUTH_CODE_PATH = 'carriers/shipper/password';
    const SHIPPERHQ_SERVER_SECRET_TOKEN_PATH = 'carriers/shipper/secret_token';
    const SHIPPERHQ_SERVER_PUBLIC_TOKEN_PATH = 'carriers/shipper/public_token';
    const SHIPPERHQ_SERVER_TOKEN_EXPIRES_PATH = 'carriers/shipper/token_expires';
    const SHIPPERHQ_SERVER_EXPIRING_SOON_THRESHOLD = 60 * 60; // 1 hour

    /** @var ReinitableConfigInterface */
    private $configReader;

    /** @var WriterInterface */
    private $configWriter;

    /** @var GraphQLClient */
    private $graphqlClient;

    /** @var DateTime */
    private $dateTime;

    /** @var \Magento\Framework\Json\DecoderInterface */
    private $jsonDecoder;

    /** @var \ShipperHQ\ProductPage\Helper\LogAssist */
    private $shipperLogger;

    /** @var bool */
    private $isConfigCacheFlushScheduled = false;

    /** @var LoggingHelper */
    private $graphqlLoggingHelper;

    /** @var Config */
    private $configHelper;

    /** @var Scope */
    private $scopeHelper;

    /**
     * Authorization constructor.
     *
     * @param ReinitableConfigInterface $configReader
     * @param WriterInterface $configWriter
     * @param \Magento\Framework\Json\DecoderInterface $jsonDecoder
     * @param GraphQLClient $graphqlClient
     * @param DateTime $dateTime
     * @param LogAssist $shipperLogger
     * @param LoggingHelper $graphqlLoggingHelper
     * @param Config $configHelper
     * @param Scope $scopeHelper
     */
    public function __construct(
        ReinitableConfigInterface $configReader,
        WriterInterface $configWriter,
        \Magento\Framework\Json\DecoderInterface $jsonDecoder,
        GraphQLClient $graphqlClient,
        DateTime $dateTime,
        LogAssist $shipperLogger,
        LoggingHelper $graphqlLoggingHelper,
        Config $configHelper,
        Scope $scopeHelper
    ) {
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
        $this->jsonDecoder = $jsonDecoder;
        $this->graphqlClient = $graphqlClient;
        $this->dateTime = $dateTime;
        $this->shipperLogger = $shipperLogger;
        $this->graphqlLoggingHelper = $graphqlLoggingHelper;
        $this->configHelper = $configHelper;
        $this->scopeHelper = $scopeHelper;
    }

    /**
     * Get a secret token
     * If a token exists and won't be expiring for some time then that token will be returned.  If the token is
     * expiring soon or there is not an existing token then a new one will be fetched from the Auth service.
     *
     * When a new secret token is fetched the public token and expiration date are extracted from the secret token then
     * all three of these values are persisted to configuration.
     *
     * @param bool $cachedOnly
     * @return string
     */
    public function getSecretToken(bool $cachedOnly = false)
    {
        $FAILURE = '';

        if ($cachedOnly || !$this->isNewSecretTokenSuggested()) {
            return $this->getStoredSecretToken();
        }

        try {
            $initVal = microtime(true);
            $tokenResult = $this->graphqlClient->createSecretToken(
                $this->getApiKey(),
                $this->getAuthCode(),
                $this->getEndpoint(),
                $this->getTimeout()
            );
            $elapsed = microtime(true) - $initVal;
            $this->shipperLogger->postDebug('Shipperhq_ProductPage', 'Auth Request time elapsed', $elapsed);
            $this->shipperLogger->postInfo('Shipperhq_ProductPage', 'Auth Request and Response', $this->graphqlLoggingHelper->prepAuthResponseForLogging($tokenResult));
        } catch (\Exception $e) {
            $this->shipperLogger->postCritical('Shipperhq_ProductPage', 'Auth Request failed with Exception', $e->getMessage());
            return $FAILURE;
        }

        if ($tokenResult && isset($tokenResult['result']) && $tokenResult['result'] instanceof CreateSecretToken) {
            $result = $tokenResult['result'];
            $data = $result->getData();

            if ($data && $data->getCreateSecretToken() && $data->getCreateSecretToken()->getToken()) {
                $tokenStr = $data->getCreateSecretToken()->getToken();

                return $this->persistNewToken($tokenStr) ? $tokenStr : $FAILURE;
            }
        }

        return $FAILURE;
    }

    /**
     * @param string $tokenStr
     *
     * @return bool
     * @throws \Exception
     */
    private function persistNewToken(string $tokenStr): bool
    {
        try {
            $token = (new Parser())->parse($tokenStr);
            $verified = $this->isSecretTokenValid($tokenStr);

            $currentTime = $this->dateTime->gmtTimestamp();
            $issuedAt = $token->claims()->get('iat')->getTimestamp();
            $expiresAt = $token->claims()->get('exp')->getTimestamp();
            $apiKey = $token->claims()->get('api_key');
            $publicToken = $token->claims()->get('public_token');

            if ($verified && $apiKey == $this->getApiKey() && $issuedAt <= $currentTime && $currentTime <= $expiresAt) {
                $this->writeToConfig(self::SHIPPERHQ_SERVER_SECRET_TOKEN_PATH, $tokenStr);
                $this->writeToConfig(self::SHIPPERHQ_SERVER_PUBLIC_TOKEN_PATH, $publicToken);
                // Timestamps are always UTC but let's be explicit so it's clear that we expect UTC here
                $expiresAt = (new \DateTime("@$expiresAt", new \DateTimeZone("UTC")))->format('c');
                $this->writeToConfig(self::SHIPPERHQ_SERVER_TOKEN_EXPIRES_PATH, $expiresAt);

                return true;
            }
        } catch (\Exception $e) {
            $this->shipperLogger->postCritical('Shipperhq_ProductPage', 'Error getting new authorization token', $e->getMessage());
        }

        return false;
    }

    /**
     * @return mixed
     */
    public function getPublicToken()
    {
        return $this->getConfigValue(self::SHIPPERHQ_SERVER_PUBLIC_TOKEN_PATH);
    }

    /**
     * Returns if Secret Token has already expired
     *
     * @return bool
     */
    public function isSecretTokenExpired(): bool
    {
        $currentTime = $this->dateTime->gmtTimestamp();
        $expirationTime = strtotime($this->getTokenExpires());
        return $currentTime >= $expirationTime;
    }

    /**
     * Returns if the Secret Token is within THRESHOLD seconds of expiring
     *
     * @return bool
     */
    public function isSecretTokenExpiringSoon(): bool
    {
        $currentTime = $this->dateTime->gmtTimestamp();
        $expirationTime = strtotime($this->getTokenExpires());
        return ($currentTime + self::SHIPPERHQ_SERVER_EXPIRING_SOON_THRESHOLD) >= $expirationTime;
    }

    /**
     * Checks if the secret token has a valid signature. Will use stored secret token if no tokenString passed in
     *
     * @param null $tokenStr
     *
     * @return bool
     */
    public function isSecretTokenValid($tokenStr = null): bool
    {
        if ($tokenStr == null) {
            $tokenStr = $this->getStoredSecretToken();
        }

        $token = (new Parser())->parse($tokenStr);
        $validator = new Validator();
        $signer = new Key($this->getAuthCode());

        return $validator->validate($token, new SignedWith(new Sha256(), $signer));
    }

    /**
     * If the current token is invalid or is about to expire then returns true
     *
     * @return bool
     */
    public function isNewSecretTokenSuggested(): bool
    {
        return $this->isSecretTokenExpiringSoon() || !$this->isSecretTokenValid();
    }

    /**
     * Checks if scope has api_key/password but doesn't have a token, in which case one should be generated
     * TODO: There are similar checks elsewhere in the code that might benefit by being refactored to use this
     *
     * @return bool
     */
    public function hasCredentialsButNoToken(): bool
    {
        $websiteId = $this->scopeHelper->getWebsiteId();
        $scopesOwnConfig = $this->configHelper->getSHQConfigForWebsiteScope($websiteId);
        $hasAPIKey = isset($scopesOwnConfig[self::SHIPPERHQ_SERVER_API_KEY_PATH]);
        $hasPassword = isset($scopesOwnConfig[self::SHIPPERHQ_SERVER_AUTH_CODE_PATH]);
        $hasSecretToken = isset($scopesOwnConfig[self::SHIPPERHQ_SERVER_SECRET_TOKEN_PATH]);
        return $hasAPIKey && $hasPassword && !$hasSecretToken;
    }
    /**
     * Wraps WriterInterface->save() but also schedules the config cache to be cleaned
     *
     * @param $path
     * @param $value
     * @param null $scope
     * @param null $scopeId
     */
    private function writeToConfig($path, $value, $scope = null, $scopeId = null)
    {
        $args = array_filter([$path, $value, $scope, $scopeId]);
        $this->configWriter->save(...$args);
        $this->scheduleConfigCacheFlush();
    }

    /**
     * Wraps ReinitableConfigInterface->getValue except allows for smartly reiniting config cache
     * @param $path
     * @param null $scopeType
     * @param null $scopeCode
     * @return mixed
     */
    private function getConfigValue($path, $scopeType = null, $scopeCode = null)
    {
        $args = array_filter([$path, $scopeType, $scopeCode]); // drop any null arguments

        if ($this->isConfigCacheFlushScheduled) {
            $this->configReader->reinit();
            $this->isConfigCacheFlushScheduled = false;
        }

        return $this->configReader->getValue(...$args);
    }

    /**
     * @return mixed
     */
    private function getApiKey()
    {
        return $this->getConfigValue(self::SHIPPERHQ_SERVER_API_KEY_PATH);
    }

    /**
     * @return mixed
     */
    private function getAuthCode()
    {
        return $this->getConfigValue(self::SHIPPERHQ_SERVER_AUTH_CODE_PATH);
    }

    /**
     * @return mixed
     */
    private function getEndpoint()
    {
        return $this->getConfigValue(self::SHIPPERHQ_ENDPOINT_PATH);
    }

    /**
     * @return mixed
     */
    private function getTimeout()
    {
        return $this->getConfigValue(self::SHIPPERHQ_TIMEOUT_PATH);
    }

    /**
     * @return mixed
     */
    private function getStoredSecretToken()
    {
        return $this->getConfigValue(self::SHIPPERHQ_SERVER_SECRET_TOKEN_PATH);
    }

    /**
     * Token expiration date in ISO 8601 format
     * @return mixed
     */
    private function getTokenExpires()
    {
        return $this->getConfigValue(self::SHIPPERHQ_SERVER_TOKEN_EXPIRES_PATH);
    }

    /**
     * @return AuthorizationShipper
     */
    private function scheduleConfigCacheFlush(): AuthorizationShipper
    {
        $this->isConfigCacheFlushScheduled = true;
        return $this;
    }
}
