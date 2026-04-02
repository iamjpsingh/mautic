<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class WhatsAppTransportPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->registerTransports($container);
        $this->registerCallbacks($container);
    }

    private function registerTransports(ContainerBuilder $container): void
    {
        if (!$container->has('mautic.whatsapp.transport_chain')) {
            return;
        }

        $definition     = $container->getDefinition('mautic.whatsapp.transport_chain');
        $taggedServices = $container->findTaggedServiceIds('mautic.whatsapp_transport');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('addTransport', [
                $id,
                new Reference($id),
                !empty($tags[0]['alias']) ? $tags[0]['alias'] : $id,
                !empty($tags[0]['integrationAlias']) ? $tags[0]['integrationAlias'] : $id,
            ]);
        }
    }

    private function registerCallbacks(ContainerBuilder $container): void
    {
        if (!$container->has('mautic.whatsapp.callback_handler_container')) {
            return;
        }

        $definition     = $container->getDefinition('mautic.whatsapp.callback_handler_container');
        $taggedServices = $container->findTaggedServiceIds('mautic.whatsapp_callback_handler');

        foreach ($taggedServices as $id => $tags) {
            $definition->addMethodCall('registerHandler', [
                new Reference($id),
            ]);
        }
    }
}
