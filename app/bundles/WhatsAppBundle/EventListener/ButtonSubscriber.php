<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\WhatsAppBundle\WhatsApp\TransportChain;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author iamjpsingh
 */
class ButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private TranslatorInterface $translator,
        private CorePermissions $security,
        private TransportChain $transportChain,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectContactViewButtons', 0],
        ];
    }

    public function injectContactViewButtons(CustomButtonEvent $event): void
    {
        // Only add button on contact view pages
        if (!str_contains($event->getRoute(), 'mautic_contact_action')) {
            return;
        }

        // Only show if WhatsApp transport is enabled
        if (0 === count($this->transportChain->getEnabledTransports())) {
            return;
        }

        // Only show if the user has WhatsApp permissions
        if (!$this->security->isGranted('whatsapp:messages:viewown')
            && !$this->security->isGranted('whatsapp:messages:viewother')
        ) {
            return;
        }

        $item = $event->getItem();

        if (!$item instanceof Lead) {
            return;
        }

        $contactId = $item->getId();

        $event->addButton(
            [
                'attr' => [
                    'href'        => $this->router->generate('mautic_whatsapp_send_to_contact_select', [
                        'contactId' => $contactId,
                    ]),
                    'data-toggle' => 'ajax',
                ],
                'btnText'   => $this->translator->trans('mautic.whatsapp.contact.send'),
                'iconClass' => 'ri-whatsapp-line',
                'priority'  => 50,
            ],
            ButtonHelper::LOCATION_PAGE_ACTIONS
        );
    }
}
