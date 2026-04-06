<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle;

use Mautic\WhatsAppBundle\DependencyInjection\Compiler\WhatsAppTransportPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * WhatsApp is a core-level channel bundle — not a plugin.
 * Extends Bundle directly, same as EmailBundle and SmsBundle.
 */
class MauticWhatsAppBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new WhatsAppTransportPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
    }
}
