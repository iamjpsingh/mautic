<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\EventListener;

use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\WhatsAppBundle\Form\Type\WhatsAppSendType;
use Mautic\WhatsAppBundle\Model\WhatsAppModel;
use Mautic\WhatsAppBundle\WhatsApp\TransportChain;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\LeadBundle\Entity\Lead;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WhatsAppModel $model,
        private TransportChain $transportChain,
        private ContactTracker $contactTracker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD => ['onFormBuilder', 0],
            FormEvents::ON_EXECUTE_SUBMIT_ACTION => ['onFormSubmitAction', 0],
        ];
    }

    public function onFormBuilder(FormBuilderEvent $event): void
    {
        if (0 === count($this->transportChain->getEnabledTransports())) {
            return;
        }

        $event->addSubmitAction('whatsapp.send', [
            'group'       => 'mautic.whatsapp.actions',
            'label'       => 'mautic.whatsapp.form.action.send',
            'description' => 'mautic.whatsapp.form.action.send.descr',
            'formType'    => WhatsAppSendType::class,
            'formTypeOptions' => ['update_select' => 'formaction_properties_whatsapp'],
            'formTheme'   => '@MauticWhatsApp/FormTheme/WhatsAppSendList/whatsappsend_list_row.html.twig',
            'eventName'   => FormEvents::ON_EXECUTE_SUBMIT_ACTION,
        ]);
    }

    public function onFormSubmitAction(SubmissionEvent $event): void
    {
        if (false === $event->checkContext('whatsapp.send')) {
            return;
        }

        $properties = $event->getAction()->getProperties();
        $whatsappId = (int) ($properties['whatsapp'] ?? 0);
        $message = $this->model->getEntity($whatsappId);

        if (null === $message || false === $message->isPublished()) {
            return;
        }

        // Get the current contact
        $contact = null;
        $feedback = $event->getActionFeedback();

        if (!empty($feedback['lead.create']['lead'])) {
            $contact = $feedback['lead.create']['lead'];
        }

        if (null === $contact) {
            $contact = $this->contactTracker->getContact();
        }

        if ($contact instanceof Lead && $contact->getPhone()) {
            $this->model->sendWhatsApp($message, $contact, [
                'source' => ['form', $event->getAction()->getForm()->getId()],
                'tokens' => $event->getTokens(),
            ]);
        }
    }
}
