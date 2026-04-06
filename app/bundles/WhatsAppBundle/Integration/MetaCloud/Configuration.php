<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Integration\MetaCloud;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\WhatsAppBundle\Exception\ConfigurationException;

/**
 * Reads WhatsApp configuration from core system parameters.
 * No plugin dependency — configuration lives in Settings → Configuration → WhatsApp.
 */
class Configuration
{
    private ?string $accessToken = null;

    private ?string $phoneNumberId = null;

    private ?string $businessAccountId = null;

    private ?string $webhookVerifyToken = null;

    private ?string $appSecret = null;

    public function __construct(
        private CoreParametersHelper $coreParametersHelper,
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

    public function isConfigured(): bool
    {
        try {
            $this->setConfiguration();

            return true;
        } catch (ConfigurationException) {
            return false;
        }
    }

    /**
     * @throws ConfigurationException
     */
    private function setConfiguration(): void
    {
        if (null !== $this->accessToken) {
            return;
        }

        if (!$this->coreParametersHelper->get('whatsapp_enabled')) {
            throw new ConfigurationException('WhatsApp is not enabled in system configuration');
        }

        $this->phoneNumberId = (string) $this->coreParametersHelper->get('whatsapp_phone_number_id');
        if (empty($this->phoneNumberId)) {
            throw new ConfigurationException('WhatsApp Phone Number ID is not configured');
        }

        $this->businessAccountId = (string) $this->coreParametersHelper->get('whatsapp_business_account_id');
        if (empty($this->businessAccountId)) {
            throw new ConfigurationException('WhatsApp Business Account ID is not configured');
        }

        $this->accessToken = (string) $this->coreParametersHelper->get('whatsapp_access_token');
        if (empty($this->accessToken)) {
            throw new ConfigurationException('WhatsApp Access Token is not configured');
        }

        $this->webhookVerifyToken = (string) $this->coreParametersHelper->get('whatsapp_webhook_verify_token');
        $this->appSecret = (string) $this->coreParametersHelper->get('whatsapp_app_secret');
    }
}
