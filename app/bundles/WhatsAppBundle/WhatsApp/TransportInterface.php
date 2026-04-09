<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\WhatsApp;

use Mautic\LeadBundle\Entity\Lead;

interface TransportInterface
{
    /**
     * Send a plain text message.
     *
     * @return bool|string true on success, wamid string on success with ID, or error message on failure
     */
    public function sendText(Lead $lead, string $content): bool|string;

    /**
     * Send a template message with optional parameters.
     *
     * @param string                          $templateName  The template name registered with Meta
     * @param string                          $languageCode  e.g. "en_US"
     * @param array<int, array<string, mixed>> $components   Template components (header, body, button params)
     *
     * @return bool|string true on success, error message string on failure
     */
    public function sendTemplate(
        Lead $lead,
        string $templateName,
        string $languageCode,
        array $components = [],
    ): bool|string;

    /**
     * Send a media message (image, document, audio, video).
     *
     * @param string               $mediaType One of: image, document, audio, video, sticker
     * @param string               $mediaUrl  Publicly accessible URL of the media
     * @param array<string, mixed> $options   Optional caption, filename, etc.
     *
     * @return bool|string true on success, error message string on failure
     */
    public function sendMedia(
        Lead $lead,
        string $mediaType,
        string $mediaUrl,
        array $options = [],
    ): bool|string;

    /**
     * Send an interactive message (buttons or list).
     *
     * @param array<string, mixed> $interactive The interactive object per Meta Cloud API spec
     *
     * @return bool|string true on success, error message string on failure
     */
    public function sendInteractive(Lead $lead, array $interactive): bool|string;
}
