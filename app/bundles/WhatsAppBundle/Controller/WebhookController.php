<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Controller;

use Mautic\WhatsAppBundle\Callback\HandlerContainer;
use Mautic\WhatsAppBundle\Exception\CallbackHandlerNotFound;
use Mautic\WhatsAppBundle\Helper\ReplyHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WebhookController extends AbstractController
{
    public function __construct(
        private HandlerContainer $callbackHandler,
        private ReplyHelper $replyHelper,
    ) {
    }

    /**
     * Handles both GET (webhook verification) and POST (incoming messages/statuses).
     *
     * @throws \Exception
     */
    public function callbackAction(Request $request, string $transport): Response
    {
        define('MAUTIC_NON_TRACKABLE_REQUEST', 1);

        try {
            $handler = $this->callbackHandler->getHandler($transport);
        } catch (CallbackHandlerNotFound) {
            throw new NotFoundHttpException();
        }

        // Handle webhook verification (GET)
        $verificationResponse = $handler->handleVerification($request);
        if (null !== $verificationResponse) {
            return $verificationResponse;
        }

        // Handle incoming messages (POST)
        return $this->replyHelper->handleRequest($handler, $request);
    }
}
