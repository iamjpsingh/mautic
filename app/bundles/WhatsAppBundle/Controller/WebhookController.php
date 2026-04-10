<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Controller;

use Mautic\WhatsAppBundle\Integration\MetaCloud\Configuration;
use Mautic\WhatsAppBundle\Service\WebhookProcessorService;
use Mautic\WhatsAppBundle\Service\WebhookStatusTracker;
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
        private WebhookStatusTracker $statusTracker,
    ) {
    }

    /**
     * Standalone webhook verification endpoint (GET only).
     */
    public function verifyAction(Request $request): Response
    {
        if (!defined('MAUTIC_NON_TRACKABLE_REQUEST')) {
            define('MAUTIC_NON_TRACKABLE_REQUEST', 1);
        }

        return $this->handleVerification($request);
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
            $rawBody = $request->getContent();
            $this->mauticLogger->info('WhatsApp webhook POST received', ['body' => $rawBody]);

            $payload = json_decode($rawBody, true);

            if (!is_array($payload)) {
                $this->statusTracker->recordError('Received POST with invalid JSON payload');

                return new Response('OK', 200);
            }

            $this->statusTracker->recordReceived();

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
            $this->statusTracker->recordError('Verify request missing hub_mode/hub_verify_token/hub_challenge');

            return new Response('Bad request', 400);
        }

        try {
            $expectedToken = $this->configuration->getWebhookVerifyToken();
        } catch (\Exception $e) {
            $this->statusTracker->recordError('Configuration error: '.$e->getMessage());

            return new Response('Not configured', 500);
        }

        if ($token !== $expectedToken) {
            $this->statusTracker->recordError('Verify token mismatch');

            return new Response('Invalid token', 403);
        }

        $this->statusTracker->recordVerified();
        $this->mauticLogger->info('WhatsApp webhook verified successfully');

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

        $this->mauticLogger->info('WhatsApp webhook status received', [
            'payload' => $status,
        ]);

        if (empty($messageId) || empty($statusType)) {
            $this->mauticLogger->warning('WhatsApp webhook: missing id or status in payload', ['payload' => $status]);

            return;
        }

        $timestamp = $status['timestamp'] ?? '';

        $this->webhookProcessor->processDeliveryStatus($messageId, $statusType, $timestamp);
    }
}
