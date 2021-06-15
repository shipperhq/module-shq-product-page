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
use Magento\Framework\Stdlib\DateTime\DateTime;
use ShipperHQ\GraphQL\Client\GraphQLClient;
use ShipperHQ\GraphQL\Response\CreateSecretToken;
use ShipperHQ\ProductPage\Helper\Config;
use ShipperHQ\ProductPage\Helper\LogAssist;
use ShipperHQ\ProductPage\Helper\Scope;

/**
 * Shipping data helper
 */
class Authorization
{
    const SHIPPERHQ_ENDPOINT_PATH = 'carriers/shqserver/graphql_url';
    const SHIPPERHQ_TIMEOUT_PATH = 'carriers/shqserver/ws_timeout';
    const SHIPPERHQ_SERVER_API_KEY_PATH = 'carriers/shqserver/api_key';
    const SHIPPERHQ_SERVER_AUTH_CODE_PATH = 'carriers/shqserver/password';
    const SHIPPERHQ_SERVER_SECRET_TOKEN_PATH = 'carriers/shqserver/secret_token';
    const SHIPPERHQ_SERVER_PUBLIC_TOKEN_PATH = 'carriers/shqserver/public_token';
    const SHIPPERHQ_SERVER_TOKEN_EXPIRES_PATH = 'carriers/shqserver/token_expires';
    const SHIPPERHQ_SERVER_EXPIRING_SOON_THRESHOLD = 60 * 60; // 1 hour
    const CACHE_IGNORE = 'ignore';
    const CACHE_PREFER = 'prefer';
    const CACHE_ONLY = 'only';

    /** @var Config */
    private $configHelper;

    /** @var GraphQLClient */
    private $graphqlClient;

    /** @var DateTime */
    private $dateTime;

    /** @var LogAssist */
    private $shipperLogger;

    /** @var Scope */
    private $scopeHelper;

    /**
     * Authorization constructor.
     * @param Config $configHelper
     * @param GraphQLClient $graphqlClient
     * @param DateTime $dateTime
     * @param LogAssist $shipperLogger
     * @param Scope $scopeHelper
     */
    public function __construct(
        Config $configHelper,
        GraphQLClient $graphqlClient,
        DateTime $dateTime,
        LogAssist $shipperLogger,
        Scope $scopeHelper
    ) {
        $this->configHelper = $configHelper;
        $this->graphqlClient = $graphqlClient;
        $this->dateTime = $dateTime;
        $this->shipperLogger = $shipperLogger;
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
     * @param string $cacheScheme use CACHE_IGNORE, CACHE_PREFER, or CACHE_ONLY
     * @return string
     */
    public function getSecretToken(string $cacheScheme = self::CACHE_PREFER)
    {
        $FAILURE = '';

        if ($cacheScheme !== self::CACHE_IGNORE && ($cacheScheme === self::CACHE_ONLY || !$this->isNewSecretTokenSuggested())) {
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
            $this->shipperLogger->postInfo('Shipperhq_ProductPage', 'Auth Request and Response', $this->prepAuthResponseForLogging($tokenResult));
        } catch (\Exception $e) {
            $this->shipperLogger->postCritical('Shipperhq_ProductPage', 'Auth Request failed with Exception', $e->getMessage());
            return $FAILURE;
        }

        if ($tokenResult && isset($tokenResult['result']) && $tokenResult['result'] instanceof CreateSecretToken) {
            /** @var CreateSecretToken $result */
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
     * Will iterate through default scope and all website scopes and update their auth tokens if needed
     */
    public function updateAllSecretTokens()
    {
        $scopes = $this->getListOfScopes();
        $attemptedCount = 0;
        $successfulCount = 0;
        foreach ($scopes as $idx => $scope) {
            if ($scope['type'] === Scope::SCOPE_TYPE_WEBSITE) {
                $scopesOwnConfig = $this->configHelper->getSHQConfigForWebsiteScope($scope['id']);
                $result = $this->updateSecretTokenForScope($scopesOwnConfig, $scope['id']);
                $attemptedCount += $result !== null;
                $successfulCount += $result === true;
            } else {
                $scopesOwnConfig = $this->configHelper->getSHQConfigForDefaultScope();
                $result = $this->updateSecretTokenForScope($scopesOwnConfig, $scope['id']);
                $attemptedCount += $result !== null;
                $successfulCount += $result === true;
            }
        }
        return $successfulCount === $attemptedCount;
    }

    /**
     * @return mixed
     */
    public function getPublicToken()
    {
        return $this->getScopedConfig(self::SHIPPERHQ_SERVER_PUBLIC_TOKEN_PATH);
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
        return $this->hasCredentialsButNoToken() || $this->isSecretTokenExpiringSoon() || !$this->isSecretTokenValid();
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
     * @return mixed
     */
    private function getApiKey()
    {
        return $this->getScopedConfig(self::SHIPPERHQ_SERVER_API_KEY_PATH);
    }

    /**
     * @return mixed
     */
    private function getAuthCode()
    {
        return $this->getScopedConfig(self::SHIPPERHQ_SERVER_AUTH_CODE_PATH);
    }

    /**
     * @return mixed
     */
    private function getEndpoint()
    {
        return $this->getScopedConfig(self::SHIPPERHQ_ENDPOINT_PATH);
    }

    /**
     * @return mixed
     */
    private function getTimeout()
    {
        return $this->getScopedConfig(self::SHIPPERHQ_TIMEOUT_PATH);
    }

    /**
     * @return mixed
     */
    private function getStoredSecretToken()
    {
        return $this->getScopedConfig(self::SHIPPERHQ_SERVER_SECRET_TOKEN_PATH);
    }

    /**
     * Token expiration date in ISO 8601 format
     * @return mixed
     */
    private function getTokenExpires()
    {
        return $this->getScopedConfig(self::SHIPPERHQ_SERVER_TOKEN_EXPIRES_PATH);
    }

    /**
     * @param string $tokenStr
     * @return bool
     */
    private function persistNewToken(string $tokenStr): bool
    {
        $token = (new Parser())->parse($tokenStr);
        $verified = $this->isSecretTokenValid($tokenStr);

        $currentTime = $this->dateTime->gmtTimestamp();
        $issuedAt = $token->claims()->get('iat')->getTimestamp();
        $expiresAt = $token->claims()->get('exp')->getTimestamp();
        $apiKey = $token->claims()->get('api_key');
        $publicToken = $token->claims()->get('public_token');

        if ($verified && $apiKey == $this->getApiKey() && $issuedAt <= $currentTime && $currentTime <= $expiresAt) {
            $this->downgradeScope();

            $this->writeToScopedConfig(self::SHIPPERHQ_SERVER_SECRET_TOKEN_PATH, $tokenStr);
            $this->writeToScopedConfig(self::SHIPPERHQ_SERVER_PUBLIC_TOKEN_PATH, $publicToken);
            // Timestamps are always UTC but let's be explicit so it's clear that we expect UTC here
            $expiresAt = (new \DateTime("@$expiresAt", new \DateTimeZone("UTC")))->format('c');
            $this->writeToScopedConfig(self::SHIPPERHQ_SERVER_TOKEN_EXPIRES_PATH, $expiresAt);

            return true;
        }

        return false;
    }

    /**
     * @param array $tokenResult
     * @return mixed
     */
    private function prepAuthResponseForLogging(array $tokenResult)
    {
        $debugResult = $tokenResult['debug'];
        $debugResult = $this->sanitizeAuthCode($debugResult);
        $debugResult = $this->sanitizeAuthToken($debugResult);
        return $debugResult;
    }

    /**
     * @param $debugResult
     * @return mixed
     */
    private function sanitizeAuthCode($debugResult)
    {
        if (isset($debugResult['request'])) {
            $debugResult['request'] = json_decode($debugResult['request'], true);
            if (isset($debugResult['request']['variables']['auth_code'])) {
                $debugResult['request']['variables']['auth_code'] = 'SANITIZED';
            }
        }
        return $debugResult;
    }

    /**
     * @param $debugResult
     * @return mixed
     */
    private function sanitizeAuthToken($debugResult)
    {
        if (isset($debugResult['response'])) {
            $debugResult['response'] = json_decode($debugResult['response'], true);
            if (isset($debugResult['response']['data']['createSecretToken']['token'])) {
                $debugResult['response']['data']['createSecretToken']['token'] = 'SANITIZED';
            }
        }
        return $debugResult;
    }

    /**
     * @return string
     */
    private function getScopeType()
    {
        return $this->scopeHelper->getScopeType();
    }

    /**
     * @return int|null
     */
    private function getScopeId()
    {
        return $this->scopeHelper->getScopeId();
    }

    /**
     * Will read config from the selected scope.
     * If user is in store scope then read from the store's parent website scope instead.
     *
     * @param string $path
     * @return mixed
     */
    private function getScopedConfig(string $path)
    {
        $scopeType = $this->scopeHelper->getScopeType();
        $scopeType = $scopeType === Scope::SCOPE_TYPE_DEFAULT ? $scopeType : Scope::SCOPE_TYPE_WEBSITE;
        $scopeId = $this->scopeHelper->getWebsiteId();
        return $this->configHelper->getConfigValue($path, $scopeType, $scopeId);
    }

    /**
     * Will write config to the selected scope.
     * If user is in store scope then write to the store's parent website scope instead.
     *
     * @param string $path
     * @param string $value
     */
    private function writeToScopedConfig(string $path, string $value)
    {
        $scopeType = $this->scopeHelper->getScopeType();
        $scopeType = $scopeType === Scope::SCOPE_TYPE_DEFAULT ? $scopeType : Scope::SCOPE_TYPE_WEBSITE;
        $scopeId = $this->scopeHelper->getWebsiteId();
        $this->configHelper->writeToConfig($path, $value, $scopeType, $scopeId);
    }

    /**
     * @return array
     */
    private function getListOfScopes(): array
    {
        // default scope must have id === null or else there will be issues downstream
        $scopes[] = ["type" => Scope::SCOPE_TYPE_DEFAULT, "id" => null];
        $allWebsites = $this->scopeHelper->getAllWebsites();
        $scopes = array_reduce(
            $allWebsites,
            function ($carry, $website) {
                $carry[] = [
                    "type" => Scope::SCOPE_TYPE_WEBSITE,
                    "id" => $website->getWebsiteId()
                ];
                return $carry;
            },
            $scopes
        );
        return $scopes;
    }

    /**
     * @param array $scopesOwnConfig
     * @param $scopeId
     * @return bool|null
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function updateSecretTokenForScope(array $scopesOwnConfig, $scopeId)
    {
        if (
            isset($scopesOwnConfig[self::SHIPPERHQ_SERVER_API_KEY_PATH]) &&
            isset($scopesOwnConfig[self::SHIPPERHQ_SERVER_AUTH_CODE_PATH])
        ) {
            $this->scopeHelper->setScopeByWebsiteAndStore($scopeId, null); // use website ${id} scope
            return $this->getSecretToken() !== '';
        }
        return null;
    }

    /**
     * If website scope does not have api_key and password then downgrade to default scope. Then downgrade
     * to the default scope.  Returns if the scope was downgraded.
     *
     * @return boolean
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function downgradeScope()
    {
        if ($this->scopeHelper->getWebsiteId() === null) {
            return false;
        }

        $scopesOwnConfig = $this->configHelper->getSHQConfigForWebsiteScope($this->scopeHelper->getWebsiteId());
        if (
            !isset($scopesOwnConfig[self::SHIPPERHQ_SERVER_API_KEY_PATH]) ||
            !isset($scopesOwnConfig[self::SHIPPERHQ_SERVER_AUTH_CODE_PATH])
        ) {
            // website is actually using inherited credentials from default scope
            $this->scopeHelper->setScopeByWebsiteAndStore(null, null);
            return true;
        }
        return false;
    }
}
