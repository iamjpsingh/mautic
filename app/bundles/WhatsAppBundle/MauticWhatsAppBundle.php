<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle;

use Mautic\WhatsAppBundle\Controller\AjaxController;
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

    /**
     * Register a class alias so Mautic's case-sensitive dispatcher finds our AjaxController.
     *
     * Mautic's CoreBundle dispatcher does ucfirst($bundleName) which turns 'whatsapp' into
     * 'Whatsapp' (lowercase 'a') — but our bundle directory is 'WhatsAppBundle' (capital 'A').
     * On case-sensitive Linux filesystems, PSR-4 autoload fails because the file path
     * doesn't match. By registering an alias pointing the lowercase namespace to the real
     * class, the dispatcher works for both 'whatsapp:action' AND 'whatsApp:action' forms.
     *
     * Why this matters: existing frontend code (browser cache, other bundles) may still
     * send 'whatsapp:' (lowercase). Without this, those requests hit the fallback branch
     * that returns {"success":0}.
     */
    public function boot(): void
    {
        parent::boot();

        // Eager-load the real class.
        class_exists(AjaxController::class);

        // Register lowercase alias for Mautic's ucfirst('whatsapp') = 'Whatsapp' lookup.
        $aliasName = 'Mautic\\WhatsappBundle\\Controller\\AjaxController';
        if (!class_exists($aliasName, false)) {
            class_alias(AjaxController::class, $aliasName);
        }
    }
}
