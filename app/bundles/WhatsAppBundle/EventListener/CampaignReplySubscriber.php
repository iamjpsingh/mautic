<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\DecisionEvent;
use Mautic\CampaignBundle\Executioner\RealTimeExecutioner;
use Mautic\WhatsAppBundle\Event\WhatsAppReplyEvent;
use Mautic\WhatsAppBundle\Form\Type\CampaignReplyType;
use Mautic\WhatsAppBundle\Helper\ReplyHelper;
use Mautic\WhatsAppBundle\WhatsApp\TransportChain;
use Mautic\WhatsAppBundle\WhatsAppEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignReplySubscriber implements EventSubscriberInterface
{
    public const TYPE = 'whatsapp.reply';

    public function __construct(
        private TransportChain $transportChain,
        private RealTimeExecutioner $realTimeExecutioner,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD   => ['onCampaignBuild', 0],
            WhatsAppEvents::ON_CAMPAIGN_REPLY    => ['onCampaignReply', 0],
            WhatsAppEvents::WHATSAPP_ON_REPLY    => ['onReply', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        if (0 === count($this->transportChain->getEnabledTransports())) {
            return;
        }

        $event->addDecision(
            self::TYPE,
            [
                'label'       => 'mautic.campaign.whatsapp.reply',
                'description' => 'mautic.campaign.whatsapp.reply.tooltip',
                'eventName'   => WhatsAppEvents::ON_CAMPAIGN_REPLY,
                'formType'    => CampaignReplyType::class,
            ]
        );
    }

    /**
     * Evaluate the campaign decision when a WhatsApp reply is received.
     *
     * Supports three match modes:
     *  - "any"      : any reply triggers the decision
     *  - "keyword"  : reply must match the pattern (regex or simple string)
     *  - "no_reply" : handled by the campaign scheduler via timeout; this decision
     *                 is the positive path (reply received), so "no_reply" mode
     *                 means the negative path fires on timeout
     */
    public function onCampaignReply(DecisionEvent $decisionEvent): void
    {
        /** @var WhatsAppReplyEvent $replyEvent */
        $replyEvent = $decisionEvent->getPassthrough();
        $properties = $decisionEvent->getLog()->getEvent()->getProperties();
        $matchMode  = $properties['match_mode'] ?? 'any';
        $pattern    = $properties['pattern'] ?? '';

        if ('no_reply' === $matchMode) {
            // For "no_reply" mode, receiving a reply means the positive path is taken
            // (the contact DID reply). The negative/timeout path handles the "no reply" case.
            $decisionEvent->setChannel('whatsapp');
            $decisionEvent->setAsApplicable();

            return;
        }

        if ('any' === $matchMode || empty($pattern)) {
            $decisionEvent->setChannel('whatsapp');
            $decisionEvent->setAsApplicable();

            return;
        }

        // keyword mode
        if (!ReplyHelper::matches($pattern, $replyEvent->getMessage())) {
            return;
        }

        $decisionEvent->setChannel('whatsapp');
        $decisionEvent->setAsApplicable();
    }

    /**
     * When a WhatsApp reply webhook is received, trigger real-time campaign decision evaluation.
     *
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException
     * @throws \Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogPassedAndFailedException
     * @throws \Mautic\CampaignBundle\Executioner\Exception\CannotProcessEventException
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function onReply(WhatsAppReplyEvent $event): void
    {
        $this->realTimeExecutioner->execute(self::TYPE, $event, 'whatsapp');
    }
}
