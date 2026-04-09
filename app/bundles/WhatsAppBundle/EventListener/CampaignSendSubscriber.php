<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\WhatsAppBundle\Form\Type\WhatsAppSendType;
use Mautic\WhatsAppBundle\Form\Type\WhatsAppTemplateSendType;
use Mautic\WhatsAppBundle\Model\WhatsAppModel;
use Mautic\WhatsAppBundle\WhatsApp\TransportChain;
use Mautic\WhatsAppBundle\WhatsAppEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSendSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WhatsAppModel $whatsAppModel,
        private TransportChain $transportChain,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD                    => ['onCampaignBuild', 0],
            WhatsAppEvents::ON_CAMPAIGN_TRIGGER_ACTION           => ['onCampaignTriggerAction', 0],
            WhatsAppEvents::ON_CAMPAIGN_TRIGGER_ACTION_TEMPLATE  => ['onCampaignTriggerActionTemplate', 0],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        if (0 === count($this->transportChain->getEnabledTransports())) {
            return;
        }

        $event->addAction(
            'whatsapp.send_template',
            [
                'label'            => 'mautic.whatsapp.campaign.send_template',
                'description'      => 'mautic.whatsapp.campaign.send_template.tooltip',
                'eventName'        => WhatsAppEvents::ON_CAMPAIGN_TRIGGER_ACTION_TEMPLATE,
                'formType'         => WhatsAppTemplateSendType::class,
                'formTypeOptions'  => ['update_select' => 'campaignevent_properties_whatsapp'],
                'formTheme'        => '@MauticWhatsApp/FormTheme/WhatsAppSendList/whatsappsend_list_row.html.twig',
                'channel'          => 'whatsapp',
                'channelIdField'   => 'whatsapp',
            ]
        );

        $event->addAction(
            'whatsapp.send_message',
            [
                'label'            => 'mautic.whatsapp.campaign.send_message',
                'description'      => 'mautic.whatsapp.campaign.send_message.tooltip',
                'eventName'        => WhatsAppEvents::ON_CAMPAIGN_TRIGGER_ACTION,
                'formType'         => WhatsAppSendType::class,
                'formTypeOptions'  => ['update_select' => 'campaignevent_properties_whatsapp'],
                'formTheme'        => '@MauticWhatsApp/FormTheme/WhatsAppSendList/whatsappsend_list_row.html.twig',
                'channel'          => 'whatsapp',
                'channelIdField'   => 'whatsapp',
            ]
        );
    }

    /**
     * Execute "Send WhatsApp Message" (session/text message).
     */
    public function onCampaignTriggerAction(CampaignExecutionEvent $event): void
    {
        $lead       = $event->getLead();
        $whatsappId = (int) $event->getConfig()['whatsapp'];
        $whatsapp   = $this->whatsAppModel->getEntity($whatsappId);

        if (!$whatsapp) {
            $event->setFailed('mautic.whatsapp.campaign.failed.missing_entity');

            return;
        }

        if (!$whatsapp->isPublished()) {
            $event->setFailed('mautic.whatsapp.campaign.failed.unpublished');

            return;
        }

        $result = $this->whatsAppModel->sendWhatsApp(
            $whatsapp,
            $lead,
            ['channel' => ['campaign.event', $event->getEvent()['id']]]
        )[$lead->getId()];

        if ('Authenticate' === $result['status']) {
            $event->setResult(false);

            return;
        }

        if (!empty($result['sent'])) {
            $event->setChannel('whatsapp', $whatsapp->getId());
            $event->setResult($result);
        } else {
            $result['failed'] = true;
            $result['reason'] = $result['status'];
            $event->setResult($result);
        }
    }

    /**
     * Execute "Send WhatsApp Template" (template message via Meta Cloud API).
     */
    public function onCampaignTriggerActionTemplate(CampaignExecutionEvent $event): void
    {
        $lead       = $event->getLead();
        $whatsappId = (int) $event->getConfig()['whatsapp'];
        $whatsapp   = $this->whatsAppModel->getEntity($whatsappId);

        if (!$whatsapp) {
            $event->setFailed('mautic.whatsapp.campaign.failed.missing_entity');

            return;
        }

        if (!$whatsapp->isPublished()) {
            $event->setFailed('mautic.whatsapp.campaign.failed.unpublished');

            return;
        }

        $result = $this->whatsAppModel->sendWhatsApp(
            $whatsapp,
            $lead,
            ['channel' => ['campaign.event', $event->getEvent()['id']]]
        )[$lead->getId()];

        if ('Authenticate' === $result['status']) {
            $event->setResult(false);

            return;
        }

        if (!empty($result['sent'])) {
            $event->setChannel('whatsapp', $whatsapp->getId());
            $event->setResult($result);
        } else {
            $result['failed'] = true;
            $result['reason'] = $result['status'];
            $event->setResult($result);
        }
    }
}
