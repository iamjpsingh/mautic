<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\EventListener;

use Doctrine\ORM\EntityManager;
use Mautic\LeadBundle\Event\LeadTimelineEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\WhatsAppBundle\Entity\WhatsAppStat;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Registers WhatsApp message send/delivery/read/failed events on the contact timeline.
 *
 * @author iamjpsingh
 */
class LeadSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private RouterInterface $router,
        private EntityManager $em,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::TIMELINE_ON_GENERATE => ['onTimelineGenerate', 0],
        ];
    }

    /**
     * Compile WhatsApp events for the lead timeline.
     */
    public function onTimelineGenerate(LeadTimelineEvent $event): void
    {
        $this->addWhatsAppEvents($event, 'sent');
        $this->addWhatsAppEvents($event, 'failed');
    }

    protected function addWhatsAppEvents(LeadTimelineEvent $event, string $state): void
    {
        $eventTypeKey  = 'whatsapp.'.$state;
        $eventTypeName = $this->translator->trans('mautic.whatsapp.timeline.status.'.$state);
        $event->addEventType($eventTypeKey, $eventTypeName);
        $event->addSerializerGroup('whatsappList');

        if (!$event->isApplicable($eventTypeKey)) {
            return;
        }

        /** @var \Mautic\WhatsAppBundle\Entity\WhatsAppStatRepository $statRepository */
        $statRepository        = $this->em->getRepository(WhatsAppStat::class);
        $queryOptions          = $event->getQueryOptions();
        $queryOptions['state'] = $state;
        $stats                 = $statRepository->getLeadStats($event->getLeadId(), $queryOptions);

        $event->addToCounter($eventTypeKey, $stats);

        if (!$event->isEngagementCount()) {
            foreach ($stats['results'] as $stat) {
                if (!empty($stat['whatsapp_name'])) {
                    $label = $stat['whatsapp_name'];
                } else {
                    $label = $this->translator->trans('mautic.whatsapp.timeline.event.custom');
                }

                $eventName = [
                    'label' => $label,
                    'href'  => $this->router->generate(
                        'mautic_whatsapp_action',
                        ['objectAction' => 'view', 'objectId' => $stat['whatsapp_message_id']]
                    ),
                ];

                $dateSent  = 'sent';
                $contactId = $stat['lead_id'];
                unset($stat['lead_id']);

                $statusBadge = $stat['status'] ?? WhatsAppStat::STATUS_SENT;

                $event->addEvent(
                    [
                        'event'           => $eventTypeKey,
                        'eventId'         => $eventTypeKey.$stat['id'],
                        'eventLabel'      => $eventName,
                        'eventType'       => $eventTypeName,
                        'timestamp'       => $stat['date'.ucfirst($dateSent)],
                        'extra'           => [
                            'stat'   => $stat,
                            'type'   => $state,
                            'status' => $statusBadge,
                        ],
                        'contentTemplate' => '@MauticWhatsApp/SubscribedEvents/Timeline/index.html.twig',
                        'icon'            => ('failed' === $state) ? 'ri-error-warning-fill' : 'ri-whatsapp-fill',
                        'contactId'       => $contactId,
                    ]
                );
            }
        }
    }
}
