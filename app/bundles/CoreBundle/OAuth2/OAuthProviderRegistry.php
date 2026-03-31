<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\OAuth2;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\CoreBundle\OAuth2\Provider\OAuthProviderInterface;

/**
 * Central registry for OAuth2 providers.
 * Stores provider definitions and manages credentials configured in system settings.
 * Plugins use this to get OAuth2 credentials without implementing their own OAuth flow.
 */
class OAuthProviderRegistry
{
    /**
     * @var array<string, OAuthProviderInterface>
     */
    private array $providers = [];

    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
        private EncryptionHelper $encryptionHelper,
    ) {
    }

    public function addProvider(OAuthProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function getProvider(string $name): ?OAuthProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * @return array<string, OAuthProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Check if a provider has been configured with client credentials.
     */
    public function isConfigured(string $providerName): bool
    {
        $clientId = $this->getClientId($providerName);

        return !empty($clientId);
    }

    public function getClientId(string $providerName): ?string
    {
        return $this->getParam($providerName, 'client_id');
    }

    public function getClientSecret(string $providerName): ?string
    {
        $encrypted = $this->getParam($providerName, 'client_secret');
        if (empty($encrypted)) {
            return null;
        }

        $decrypted = $this->encryptionHelper->decrypt($encrypted);

        return false === $decrypted ? $encrypted : (string) $decrypted;
    }

    public function getAccessToken(string $providerName): ?string
    {
        $encrypted = $this->getParam($providerName, 'access_token');
        if (empty($encrypted)) {
            return null;
        }

        $decrypted = $this->encryptionHelper->decrypt($encrypted);

        return false === $decrypted ? $encrypted : (string) $decrypted;
    }

    public function getRefreshToken(string $providerName): ?string
    {
        $encrypted = $this->getParam($providerName, 'refresh_token');
        if (empty($encrypted)) {
            return null;
        }

        $decrypted = $this->encryptionHelper->decrypt($encrypted);

        return false === $decrypted ? $encrypted : (string) $decrypted;
    }

    public function getTokenExpiresAt(string $providerName): ?int
    {
        $value = $this->getParam($providerName, 'token_expires_at');

        return $value ? (int) $value : null;
    }

    public function isTokenExpired(string $providerName): bool
    {
        $expiresAt = $this->getTokenExpiresAt($providerName);

        if (null === $expiresAt) {
            return true;
        }

        return $expiresAt < time();
    }

    /**
     * Get additional scopes configured for this provider.
     *
     * @return string[]
     */
    public function getScopes(string $providerName): array
    {
        $provider = $this->getProvider($providerName);
        $defaultScopes = $provider ? $provider->getDefaultScopes() : [];
        $extraScopes = $this->getParam($providerName, 'scopes');

        if (empty($extraScopes)) {
            return $defaultScopes;
        }

        $extra = array_filter(array_map('trim', explode(',', $extraScopes)));

        return array_unique(array_merge($defaultScopes, $extra));
    }

    /**
     * Build the callback URL for a provider's OAuth flow.
     */
    public function getCallbackUrl(string $providerName): string
    {
        $siteUrl = rtrim((string) $this->coreParametersHelper->get('site_url'), '/');

        return $siteUrl.'/oauth/callback/'.$providerName;
    }

    private function getParam(string $providerName, string $key): ?string
    {
        $paramKey = 'oauth2_'.$providerName.'_'.$key;
        $value = $this->coreParametersHelper->get($paramKey);

        return empty($value) ? null : (string) $value;
    }
}
