<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\WhatsAppBundle\Form\Type\WhatsAppConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @author iamjpsingh
 */
class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm([
            'bundle'     => 'WhatsAppBundle',
            'formAlias'  => 'whatsappconfig',
            'formType'   => WhatsAppConfigType::class,
            'formTheme'  => '@MauticWhatsApp/FormTheme/Config/_config_whatsappconfig_widget.html.twig',
            'parameters' => $event->getParametersFromConfig('MauticWhatsAppBundle'),
        ]);
    }
}
