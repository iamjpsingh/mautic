<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Integration\MetaCloud;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\WhatsAppBundle\Exception\ConfigurationException;
use Mautic\WhatsAppBundle\WhatsApp\TransportInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MetaCloudTransport implements TransportInterface
{
    private const API_VERSION = 'v21.0';

    private const BASE_URL = 'https://graph.facebook.com';

    public function __construct(
        private Configuration $configuration,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    public function sendText(Lead $lead, string $content): bool|string
    {
        $number = $this->resolvePhoneNumber($lead);
        if (null === $number) {
            return 'Contact does not have a phone number';
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $number,
            'type'              => 'text',
            'text'              => [
                'preview_url' => false,
                'body'        => $content,
            ],
        ];

        return $this->sendMessage($payload);
    }

    public function sendTemplate(
        Lead $lead,
        string $templateName,
        string $languageCode,
        array $components = [],
    ): bool|string {
        $number = $this->resolvePhoneNumber($lead);
        if (null === $number) {
            return 'Contact does not have a phone number';
        }

        $template = [
            'name'     => $templateName,
            'language' => [
                'code' => $languageCode,
            ],
        ];

        if ([] !== $components) {
            $template['components'] = $components;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $number,
            'type'              => 'template',
            'template'          => $template,
        ];

        return $this->sendMessage($payload);
    }

    public function sendMedia(
        Lead $lead,
        string $mediaType,
        string $mediaUrl,
        array $options = [],
    ): bool|string {
        $number = $this->resolvePhoneNumber($lead);
        if (null === $number) {
            return 'Contact does not have a phone number';
        }

        $allowedTypes = ['image', 'document', 'audio', 'video', 'sticker'];
        if (!in_array($mediaType, $allowedTypes, true)) {
            return sprintf('Invalid media type "%s". Allowed: %s', $mediaType, implode(', ', $allowedTypes));
        }

        $mediaObject = ['link' => $mediaUrl];

        if (isset($options['caption']) && in_array($mediaType, ['image', 'document', 'video'], true)) {
            $mediaObject['caption'] = $options['caption'];
        }

        if (isset($options['filename']) && 'document' === $mediaType) {
            $mediaObject['filename'] = $options['filename'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $number,
            'type'              => $mediaType,
            $mediaType          => $mediaObject,
        ];

        return $this->sendMessage($payload);
    }

    public function sendInteractive(Lead $lead, array $interactive): bool|string
    {
        $number = $this->resolvePhoneNumber($lead);
        if (null === $number) {
            return 'Contact does not have a phone number';
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $number,
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ];

        return $this->sendMessage($payload);
    }

    /**
     * Fetch message templates from the WhatsApp Business Account.
     *
     * @return array<string, mixed>
     *
     * @throws ConfigurationException
     */
    public function getTemplates(int $limit = 100): array
    {
        $businessAccountId = $this->configuration->getBusinessAccountId();
        $url               = sprintf(
            '%s/%s/%s/message_templates?limit=%d',
            self::BASE_URL,
            self::API_VERSION,
            $businessAccountId,
            $limit
        );

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->configuration->getAccessToken(),
            ],
        ]);

        return $response->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendMessage(array $payload): bool|string
    {
        try {
            $phoneNumberId = $this->configuration->getPhoneNumberId();
            $url           = sprintf(
                '%s/%s/%s/messages',
                self::BASE_URL,
                self::API_VERSION,
                $phoneNumberId
            );

            $response   = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->configuration->getAccessToken(),
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                return true;
            }

            $body    = $response->toArray(false);
            $message = $body['error']['message'] ?? 'Unknown API error (HTTP '.$statusCode.')';

            $this->logger->warning('WhatsApp API error: '.$message, ['response' => $body]);

            return $message;
        } catch (ConfigurationException $e) {
            $this->logger->warning(
                $e->getMessage() ?: 'mautic.whatsapp.transport.meta_cloud.not_configured',
                ['exception' => $e]
            );

            return $e->getMessage() ?: 'mautic.whatsapp.transport.meta_cloud.not_configured';
        } catch (\Throwable $e) {
            $this->logger->warning($e->getMessage(), ['exception' => $e]);

            return $e->getMessage();
        }
    }

    private function resolvePhoneNumber(Lead $lead): ?string
    {
        $number = $lead->getLeadPhoneNumber();

        if (null === $number) {
            return null;
        }

        try {
            return $this->sanitizeNumber($number);
        } catch (NumberParseException $e) {
            $this->logger->warning(
                'Failed to parse phone number for WhatsApp: '.$e->getMessage(),
                ['exception' => $e]
            );

            return null;
        }
    }

    /**
     * Sanitize and format a phone number to E.164 without the leading "+".
     * Meta Cloud API expects numbers without the + prefix.
     *
     * @throws NumberParseException
     */
    private function sanitizeNumber(string $number): string
    {
        $util   = PhoneNumberUtil::getInstance();
        $parsed = $util->parse($number, 'US');
        $e164   = $util->format($parsed, PhoneNumberFormat::E164);

        // Meta API expects the number without the leading "+"
        return ltrim($e164, '+');
    }
}
