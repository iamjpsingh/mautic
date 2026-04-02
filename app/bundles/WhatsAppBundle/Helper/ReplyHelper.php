<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Helper;

use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\WhatsAppBundle\Callback\CallbackInterface;
use Mautic\WhatsAppBundle\Event\WhatsAppReplyEvent;
use Mautic\WhatsAppBundle\Exception\NumberNotFoundException;
use Mautic\WhatsAppBundle\WhatsAppEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReplyHelper
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private ContactTracker $contactTracker,
    ) {
    }

    public static function matches(string $pattern, string $replyBody): bool
    {
        return fnmatch($pattern, $replyBody, FNM_CASEFOLD);
    }

    /**
     * @throws \Exception
     */
    public function handleRequest(CallbackInterface $handler, Request $request): Response
    {
        $response = new Response();

        // First, dispatch delivery status updates if present
        $statuses = $handler->getStatusUpdates($request);
        if ([] !== $statuses) {
            $this->eventDispatcher->dispatch(
                new \Mautic\WhatsAppBundle\Event\WhatsAppStatusEvent($statuses),
                WhatsAppEvents::WHATSAPP_ON_DELIVERY
            );
        }

        try {
            $message  = $handler->getMessage($request);
            $contacts = $handler->getContacts($request);

            $this->logger->debug(sprintf('WHATSAPP REPLY: Processing message "%s"', $message));
            $this->logger->debug(sprintf('WHATSAPP REPLY: Found IDs %s', implode(',', $contacts->getKeys())));

            foreach ($contacts as $contact) {
                $this->contactTracker->setSystemContact($contact);

                $eventResponse = $this->dispatchReplyEvent($contact, $message);

                if ($eventResponse instanceof Response) {
                    $response = $eventResponse;
                }
            }
        } catch (BadRequestHttpException) {
            // Could be a status-only webhook with no messages - that's OK
            if ([] === $statuses) {
                return new Response('invalid request', 400);
            }
        } catch (NotFoundHttpException) {
            if ([] === $statuses) {
                return new Response('', 404);
            }
        } catch (NumberNotFoundException $exception) {
            $this->logger->debug(
                sprintf(
                    '%s: %s was not found. The message sent was "%s"',
                    $handler->getTransportName(),
                    $exception->getNumber(),
                    !empty($message) ? $message : 'unknown'
                )
            );
        }

        return $response;
    }

    private function dispatchReplyEvent(Lead $contact, string $message): ?Response
    {
        $replyEvent = new WhatsAppReplyEvent($contact, trim($message));

        $this->eventDispatcher->dispatch($replyEvent, WhatsAppEvents::WHATSAPP_ON_REPLY);

        return $replyEvent->getResponse();
    }
}
