<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\EventListener;

use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\LeadEventLog;
use Mautic\LeadBundle\Entity\LeadEventLogRepository;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\EventListener\TimelineEventLogTrait;
use Mautic\LeadBundle\LeadEvents;
use Mautic\WhatsAppBundle\Event\WhatsAppReplyEvent;
use Mautic\WhatsAppBundle\WhatsAppEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReplySubscriber implements EventSubscriberInterface
{
    use TimelineEventLogTrait;

    public function __construct(Translator $translator, LeadEventLogRepository $eventLogRepository)
    {
        $this->translator         = $translator;
        $this->eventLogRepository = $eventLogRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WhatsAppEvents::WHATSAPP_ON_REPLY    => ['onReply', 0],
            LeadEvents::TIMELINE_ON_GENERATE     => 'onTimelineGenerate',
        ];
    }

    public function onReply(WhatsAppReplyEvent $event): void
    {
        $message = $event->getMessage();
        $contact = $event->getContact();

        $log = new LeadEventLog();
        $log
            ->setLead($contact)
            ->setBundle('whatsapp')
            ->setObject('whatsapp')
            ->setAction('reply')
            ->setProperties(
                [
                    'message'     => InputHelper::clean($message),
                    'messageType' => $event->getMessageType(),
                ]
            );

        $this->eventLogRepository->saveEntity($log);
        $this->eventLogRepository->detachEntity($log);
        $event->setEventLog($log);
    }

    public function onTimelineGenerate(LeadTimelineEvent $event): void
    {
        $this->addEvents(
            $event,
            'whatsapp_reply',
            'mautic.whatsapp.timeline.reply',
            'ri-whatsapp-line',
            'whatsapp',
            'whatsapp',
            'reply',
            '@MauticWhatsApp/SubscribedEvents/Timeline/reply.html.twig'
        );
    }
}
