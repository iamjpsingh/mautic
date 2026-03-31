<?php

declare(strict_types=1);

namespace Mautic\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers all services tagged with 'mautic.oauth2_provider'
 * into the OAuthProviderRegistry.
 */
class OAuthProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mautic.oauth2.registry')) {
            return;
        }

        $registryDefinition = $container->getDefinition('mautic.oauth2.registry');

        foreach ($container->findTaggedServiceIds('mautic.oauth2_provider') as $serviceId => $tags) {
            $registryDefinition->addMethodCall('addProvider', [new Reference($serviceId)]);
        }
    }
}
