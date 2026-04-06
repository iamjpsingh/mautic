<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\WhatsAppBundle\DependencyInjection\Compiler\WhatsAppTransportPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MauticWhatsAppBundle extends PluginBundleBase
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new WhatsAppTransportPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }
}
