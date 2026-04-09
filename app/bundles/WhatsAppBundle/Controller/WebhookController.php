<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Controller;

use Mautic\WhatsAppBundle\Callback\CallbackInterface;
use Mautic\WhatsAppBundle\Integration\MetaCloud\Configuration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends AbstractController
{
    public function __construct(
        private Configuration $configuration,
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
}
