<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Integration\MetaCloud;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\WhatsAppBundle\Callback\CallbackInterface;
use Mautic\WhatsAppBundle\Exception\ConfigurationException;
use Mautic\WhatsAppBundle\Exception\NumberNotFoundException;
use Mautic\WhatsAppBundle\Helper\ContactHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MetaCloudCallback implements CallbackInterface
{
    public function __construct(
        private ContactHelper $contactHelper,
        private Configuration $configuration,
        private LoggerInterface $logger,
    ) {
    }

    public function getTransportName(): string
    {
        return 'meta_cloud';
    }

    public function handleVerification(Request $request): ?Response
    {
        if (!$request->isMethod('GET')) {
            return null;
        }

        $mode      = $request->query->get('hub_mode');
        $token     = $request->query->get('hub_verify_token');
        $challenge = $request->query->get('hub_challenge');

        if ('subscribe' !== $mode || null === $token || null === $challenge) {
            return null;
        }

        try {
            $expectedToken = $this->configuration->getWebhookVerifyToken();
        } catch (ConfigurationException) {
            throw new NotFoundHttpException();
        }

        if ($token !== $expectedToken) {
            $this->logger->warning('WhatsApp webhook verification failed: invalid verify token');

            throw new BadRequestHttpException('Invalid verify token');
        }

        return new Response($challenge, 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * @throws NumberNotFoundException
     */
    public function getContacts(Request $request): ArrayCollection
    {
        $this->validateWebhookRequest($request);

        $senderNumber = $this->extractSenderNumber($request);

        return $this->contactHelper->findContactsByNumber($senderNumber);
    }

    public function getMessage(Request $request): string
    {
        $this->validateWebhookRequest($request);

        $payload  = $this->getPayload($request);
        $messages = $this->extractMessages($payload);

        if ([] === $messages) {
            throw new BadRequestHttpException('No messages found in webhook payload');
        }

        $message = $messages[0];

        return match ($message['type'] ?? '') {
            'text'        => trim($message['text']['body'] ?? ''),
            'interactive' => $this->extractInteractiveReply($message),
            'button'      => trim($message['button']['text'] ?? ''),
            default       => sprintf('[%s message received]', $message['type'] ?? 'unknown'),
        };
    }

    /**
     * @return array<int, array{message_id: string, status: string, timestamp: string, recipient_id: string}>
     */
    public function getStatusUpdates(Request $request): array
    {
        $payload  = $this->getPayload($request);
        $statuses = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];

                foreach ($value['statuses'] ?? [] as $status) {
                    $statuses[] = [
                        'message_id'   => $status['id'] ?? '',
                        'status'       => $status['status'] ?? '',
                        'timestamp'    => $status['timestamp'] ?? '',
                        'recipient_id' => $status['recipient_id'] ?? '',
                    ];
                }
            }
        }

        return $statuses;
    }

    /**
     * Validate that the webhook request is legitimate using the app secret signature.
     */
    private function validateWebhookRequest(Request $request): void
    {
        if (!$request->isMethod('POST')) {
            throw new BadRequestHttpException('Expected POST request');
        }

        try {
            $appSecret = $this->configuration->getAppSecret();
        } catch (ConfigurationException) {
            throw new NotFoundHttpException();
        }

        if ('' !== $appSecret) {
            $signature = $request->headers->get('X-Hub-Signature-256');
            if (null === $signature) {
                throw new BadRequestHttpException('Missing X-Hub-Signature-256 header');
            }

            $rawBody         = $request->getContent();
            $expectedSig     = 'sha256='.hash_hmac('sha256', $rawBody, $appSecret);

            if (!hash_equals($expectedSig, $signature)) {
                $this->logger->warning('WhatsApp webhook signature verification failed');

                throw new BadRequestHttpException('Invalid signature');
            }
        }
    }

    private function extractSenderNumber(Request $request): string
    {
        $payload  = $this->getPayload($request);
        $messages = $this->extractMessages($payload);

        if ([] === $messages) {
            throw new BadRequestHttpException('No messages found in webhook payload');
        }

        $from = $messages[0]['from'] ?? '';
        if ('' === $from) {
            throw new BadRequestHttpException('Missing sender number in webhook payload');
        }

        // Meta sends numbers without "+", prepend it for phone number parsing
        return '+'.$from;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayload(Request $request): array
    {
        $content = $request->getContent();
        $payload = json_decode($content, true);

        if (!is_array($payload)) {
            throw new BadRequestHttpException('Invalid JSON payload');
        }

        return $payload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractMessages(array $payload): array
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value    = $change['value'] ?? [];
                $messages = $value['messages'] ?? [];

                if ([] !== $messages) {
                    return $messages;
                }
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractInteractiveReply(array $message): string
    {
        $interactive = $message['interactive'] ?? [];
        $type        = $interactive['type'] ?? '';

        return match ($type) {
            'button_reply' => trim($interactive['button_reply']['title'] ?? ''),
            'list_reply'   => trim($interactive['list_reply']['title'] ?? ''),
            default        => '[interactive reply]',
        };
    }
}
