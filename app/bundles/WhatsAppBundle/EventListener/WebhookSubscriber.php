<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\EventListener;

use Mautic\WhatsAppBundle\Event\WhatsAppDeliveryEvent;
use Mautic\WhatsAppBundle\Event\WhatsAppReplyEvent;
use Mautic\WhatsAppBundle\Event\WhatsAppSendEvent;
use Mautic\WhatsAppBundle\WhatsAppEvents;
use Mautic\WebhookBundle\Event\WebhookBuilderEvent;
use Mautic\WebhookBundle\Model\WebhookModel;
use Mautic\WebhookBundle\WebhookEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class WebhookSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WebhookModel $webhookModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WhatsAppEvents::WHATSAPP_ON_SEND     => 'onSend',
            WhatsAppEvents::WHATSAPP_ON_REPLY    => 'onReply',
            WhatsAppEvents::WHATSAPP_ON_DELIVERY => 'onDelivery',
            WebhookEvents::WEBHOOK_ON_BUILD      => 'onWebhookBuild',
        ];
    }

    /**
     * Register WhatsApp events in the webhook builder UI.
     */
    public function onWebhookBuild(WebhookBuilderEvent $event): void
    {
        $event->addEvent(
            WhatsAppEvents::WHATSAPP_ON_SEND,
            [
                'label'       => 'mautic.whatsapp.webhook.event.send',
                'description' => 'mautic.whatsapp.webhook.event.send_desc',
            ]
        );

        $event->addEvent(
            WhatsAppEvents::WHATSAPP_ON_REPLY,
            [
                'label'       => 'mautic.whatsapp.webhook.event.reply',
                'description' => 'mautic.whatsapp.webhook.event.reply_desc',
            ]
        );

        $event->addEvent(
            WhatsAppEvents::WHATSAPP_ON_DELIVERY,
            [
                'label'       => 'mautic.whatsapp.webhook.event.delivery',
                'description' => 'mautic.whatsapp.webhook.event.delivery_desc',
            ]
        );
    }

    public function onSend(WhatsAppSendEvent $event): void
    {
        $this->webhookModel->queueWebhooksByType(
            WhatsAppEvents::WHATSAPP_ON_SEND,
            [
                'whatsappId'   => $event->getWhatsAppId(),
                'contact'      => $event->getLead(),
                'content'      => $event->getContent(),
                'templateName' => $event->getTemplateName(),
            ]
        );
    }

    public function onReply(WhatsAppReplyEvent $event): void
    {
        $this->webhookModel->queueWebhooksByType(
            WhatsAppEvents::WHATSAPP_ON_REPLY,
            [
                'contact'     => $event->getContact(),
                'message'     => $event->getMessage(),
                'messageType' => $event->getMessageType(),
            ]
        );
    }

    public function onDelivery(WhatsAppDeliveryEvent $event): void
    {
        $this->webhookModel->queueWebhooksByType(
            WhatsAppEvents::WHATSAPP_ON_DELIVERY,
            [
                'contact'   => $event->getContact(),
                'messageId' => $event->getMessageId(),
                'status'    => $event->getStatus(),
                'timestamp' => $event->getTimestamp(),
            ]
        );
    }
}
