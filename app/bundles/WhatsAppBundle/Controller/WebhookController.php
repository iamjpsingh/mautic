<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Controller;

use Mautic\WhatsAppBundle\Integration\MetaCloud\Configuration;
use Mautic\WhatsAppBundle\Service\WebhookProcessorService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends AbstractController
{
    public function __construct(
        private Configuration $configuration,
        private LoggerInterface $mauticLogger,
        private WebhookProcessorService $webhookProcessor,
    ) {
    }

    /**
     * Handles both GET (webhook verification) and POST (incoming messages/statuses).
     */
    public function callbackAction(Request $request, string $transport): Response
    {
        if (!defined('MAUTIC_NON_TRACKABLE_REQUEST')) {
            define('MAUTIC_NON_TRACKABLE_REQUEST', 1);
        }

        // Handle webhook verification (GET)
        if ($request->isMethod('GET')) {
            return $this->handleVerification($request);
        }

        // Handle incoming POST (messages + status updates)
        if ($request->isMethod('POST')) {
            $payload = json_decode($request->getContent(), true);

            if (is_array($payload)) {
                // Process status updates
                foreach ($payload['entry'] ?? [] as $entry) {
                    foreach ($entry['changes'] ?? [] as $change) {
                        $value = $change['value'] ?? [];

                        // Process delivery statuses
                        foreach ($value['statuses'] ?? [] as $status) {
                            $this->processStatus($status);
                        }
                    }
                }
            }

            return new Response('OK', 200);
        }

        return new Response('OK', 200);
    }

    private function handleVerification(Request $request): Response
    {
        $mode      = $request->query->get('hub_mode');
        $token     = $request->query->get('hub_verify_token');
        $challenge = $request->query->get('hub_challenge');

        if ('subscribe' !== $mode || null === $token || null === $challenge) {
            return new Response('Bad request', 400);
        }

        try {
            $expectedToken = $this->configuration->getWebhookVerifyToken();
        } catch (\Exception) {
            return new Response('Not configured', 500);
        }

        if ($token !== $expectedToken) {
            return new Response('Invalid token', 403);
        }

        return new Response($challenge, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Process a delivery status update from the Meta webhook.
     *
     * @param array<string, mixed> $status
     */
    private function processStatus(array $status): void
    {
        $messageId  = $status['id'] ?? '';
        $statusType = $status['status'] ?? '';

        if (empty($messageId) || empty($statusType)) {
            return;
        }

        $timestamp = $status['timestamp'] ?? '';

        $this->mauticLogger->info(sprintf(
            'WhatsApp webhook: status=%s, messageId=%s',
            $statusType,
            $messageId
        ));

        $this->webhookProcessor->processDeliveryStatus($messageId, $statusType, $timestamp);
    }
}
