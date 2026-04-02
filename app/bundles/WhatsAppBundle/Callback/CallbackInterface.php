<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Callback;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\WhatsAppBundle\Exception\NumberNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface CallbackInterface
{
    /**
     * Returns a "transport" string to match the URL path /whatsapp/{transport}/callback.
     */
    public function getTransportName(): string;

    /**
     * Handle Meta webhook verification (GET request with hub.mode, hub.verify_token, hub.challenge).
     * Return a Response if this is a verification request, or null if not.
     */
    public function handleVerification(Request $request): ?Response;

    /**
     * Return all contacts that match the sender in the webhook payload.
     *
     * @return ArrayCollection<int, \Mautic\LeadBundle\Entity\Lead>
     *
     * @throws NumberNotFoundException
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function getContacts(Request $request): ArrayCollection;

    /**
     * Extract the message text from the webhook payload.
     *
     * @throws BadRequestHttpException
     * @throws NotFoundHttpException
     */
    public function getMessage(Request $request): string;

    /**
     * Extract delivery status updates from the webhook payload.
     *
     * @return array<int, array{message_id: string, status: string, timestamp: string, recipient_id: string}>
     */
    public function getStatusUpdates(Request $request): array;
}
