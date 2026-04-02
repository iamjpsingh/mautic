<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Integration\MetaCloud;

use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\WhatsAppBundle\Exception\ConfigurationException;

class Configuration
{
    private ?string $accessToken = null;

    private ?string $phoneNumberId = null;

    private ?string $businessAccountId = null;

    private ?string $webhookVerifyToken = null;

    private ?string $appSecret = null;

    public function __construct(
        private IntegrationHelper $integrationHelper,
    ) {
    }

    /**
     * @throws ConfigurationException
     */
    public function getAccessToken(): string
    {
        $this->setConfiguration();

        return $this->accessToken;
    }

    /**
     * @throws ConfigurationException
     */
    public function getPhoneNumberId(): string
    {
        $this->setConfiguration();

        return $this->phoneNumberId;
    }

    /**
     * @throws ConfigurationException
     */
    public function getBusinessAccountId(): string
    {
        $this->setConfiguration();

        return $this->businessAccountId;
    }

    /**
     * @throws ConfigurationException
     */
    public function getWebhookVerifyToken(): string
    {
        $this->setConfiguration();

        return $this->webhookVerifyToken;
    }

    /**
     * @throws ConfigurationException
     */
    public function getAppSecret(): string
    {
        $this->setConfiguration();

        return $this->appSecret;
    }

    /**
     * @throws ConfigurationException
     */
    private function setConfiguration(): void
    {
        if (null !== $this->accessToken) {
            return;
        }

        $integration = $this->integrationHelper->getIntegrationObject('MetaWhatsApp');

        if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
            throw new ConfigurationException('MetaWhatsApp integration is not published or not found');
        }

        $features = $integration->getIntegrationSettings()->getFeatureSettings();

        $this->phoneNumberId = $features['phone_number_id'] ?? null;
        if (empty($this->phoneNumberId)) {
            throw new ConfigurationException('WhatsApp phone number ID is not configured');
        }

        $this->businessAccountId = $features['business_account_id'] ?? null;
        if (empty($this->businessAccountId)) {
            throw new ConfigurationException('WhatsApp Business Account ID is not configured');
        }

        $this->webhookVerifyToken = $features['webhook_verify_token'] ?? null;
        if (empty($this->webhookVerifyToken)) {
            throw new ConfigurationException('WhatsApp webhook verify token is not configured');
        }

        $keys = $integration->getDecryptedApiKeys();

        if (empty($keys['password'])) {
            throw new ConfigurationException('WhatsApp access token is not configured');
        }

        $this->accessToken = $keys['password'];
        $this->appSecret   = $keys['secret'] ?? '';
    }
}
