<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Integration;

use Mautic\PluginBundle\Integration\AbstractIntegration;

class MetaWhatsAppIntegration extends AbstractIntegration
{
    protected bool $coreIntegration = true;

    public function getName(): string
    {
        return 'MetaWhatsApp';
    }

    public function getDisplayName(): string
    {
        return 'WhatsApp (Meta Cloud API)';
    }

    public function getIcon(): string
    {
        return 'app/bundles/WhatsAppBundle/Assets/img/WhatsApp.png';
    }

    public function getSecretKeys(): array
    {
        return ['access_token', 'app_secret'];
    }

    /**
     * @return array<string, string>
     */
    public function getRequiredKeyFields(): array
    {
        return [
            'access_token'           => 'mautic.whatsapp.config.form.access_token',
            'phone_number_id'        => 'mautic.whatsapp.config.form.phone_number_id',
            'business_account_id'    => 'mautic.whatsapp.config.form.business_account_id',
            'webhook_verify_token'   => 'mautic.whatsapp.config.form.webhook_verify_token',
            'app_secret'             => 'mautic.whatsapp.config.form.app_secret',
        ];
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    /**
     * @return string[]
     */
    public function getSupportedFeatures(): array
    {
        return ['push_lead'];
    }
}
