<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\WhatsApp;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\WhatsAppBundle\Exception\PrimaryTransportNotEnabledException;

class TransportChain
{
    /**
     * @var array<string, array{alias: string, integrationAlias: string, service: TransportInterface, published?: bool}>
     */
    private array $transports = [];

    public function __construct(
        private string $primaryTransport,
        private IntegrationHelper $integrationHelper,
    ) {
    }

    public function addTransport(
        string $id,
        TransportInterface $transport,
        string $translatableAlias,
        string $integrationAlias,
    ): self {
        $this->transports[$id] = [
            'alias'            => $translatableAlias,
            'integrationAlias' => $integrationAlias,
            'service'          => $transport,
        ];

        return $this;
    }

    /**
     * @throws PrimaryTransportNotEnabledException
     */
    public function getPrimaryTransport(): TransportInterface
    {
        $enabled = $this->getEnabledTransports();

        if (1 === count($enabled)) {
            return array_shift($enabled);
        }

        if (0 === count($enabled)) {
            throw new PrimaryTransportNotEnabledException('Primary WhatsApp transport is not enabled');
        }

        if (!array_key_exists($this->primaryTransport, $enabled)) {
            throw new PrimaryTransportNotEnabledException(
                'Primary WhatsApp transport is not enabled. '.$this->primaryTransport
            );
        }

        return $enabled[$this->primaryTransport];
    }

    public function sendText(Lead $lead, string $content): bool|string
    {
        return $this->getPrimaryTransport()->sendText($lead, $content);
    }

    public function sendTemplate(
        Lead $lead,
        string $templateName,
        string $languageCode,
        array $components = [],
    ): bool|string {
        return $this->getPrimaryTransport()->sendTemplate($lead, $templateName, $languageCode, $components);
    }

    public function sendMedia(
        Lead $lead,
        string $mediaType,
        string $mediaUrl,
        array $options = [],
    ): bool|string {
        return $this->getPrimaryTransport()->sendMedia($lead, $mediaType, $mediaUrl, $options);
    }

    public function sendInteractive(Lead $lead, array $interactive): bool|string
    {
        return $this->getPrimaryTransport()->sendInteractive($lead, $interactive);
    }

    /**
     * @return array<string, array{alias: string, integrationAlias: string, service: TransportInterface, published?: bool}>
     */
    public function getTransports(): array
    {
        return $this->transports;
    }

    /**
     * @throws PrimaryTransportNotEnabledException
     */
    public function getTransport(string $transport): TransportInterface
    {
        $enabled = $this->getEnabledTransports();

        if (!array_key_exists($transport, $enabled)) {
            throw new PrimaryTransportNotEnabledException(
                $transport.' WhatsApp transport is not enabled or does not exist'
            );
        }

        return $enabled[$transport];
    }

    /**
     * @return array<string, TransportInterface>
     */
    public function getEnabledTransports(): array
    {
        $enabled = [];

        foreach ($this->transports as $alias => $transport) {
            if (!isset($transport['published'])) {
                $integration = $this->integrationHelper->getIntegrationObject($transport['integrationAlias']);
                if (!$integration) {
                    continue;
                }
                $transport['published']   = $integration->getIntegrationSettings()->getIsPublished();
                $this->transports[$alias] = $transport;
            }

            if ($transport['published']) {
                $enabled[$alias] = $transport['service'];
            }
        }

        return $enabled;
    }
}
