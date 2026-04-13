<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [
    ];

    $services->load('Mautic\\WhatsAppBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->load('Mautic\\WhatsAppBundle\\Entity\\', '../Entity/{WhatsAppMessageRepository,WhatsAppStatRepository}.php')
        ->tag(Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

    $services->set(Mautic\WhatsAppBundle\Entity\WhatsAppTemplateRepository::class)
        ->factory([new \Symfony\Component\DependencyInjection\Reference('doctrine.orm.entity_manager'), 'getRepository'])
        ->args([Mautic\WhatsAppBundle\Entity\WhatsAppTemplate::class]);

    $services->alias('mautic.whatsapp.model.whatsapp', Mautic\WhatsAppBundle\Model\WhatsAppModel::class);

    // Register lowercase-namespace aliases so Mautic's AJAX dispatcher (which does
    // ucfirst('whatsapp') = 'Whatsapp', losing the camelCase 'A' in our bundle name)
    // can resolve the controller via the service container. Without these aliases,
    // Symfony's ControllerResolver falls back to `new $class()` which fails on
    // constructors that need DI.
    $services->alias('Mautic\\WhatsappBundle\\Controller\\AjaxController', Mautic\WhatsAppBundle\Controller\AjaxController::class)
        ->public();
    $services->alias('Mautic\\WhatsappBundle\\Controller\\WhatsAppController', Mautic\WhatsAppBundle\Controller\WhatsAppController::class)
        ->public();
    $services->alias('Mautic\\WhatsappBundle\\Controller\\WebhookController', Mautic\WhatsAppBundle\Controller\WebhookController::class)
        ->public();
};
