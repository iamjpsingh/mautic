<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Event;

use Mautic\LeadBundle\Entity\Lead;
use Symfony\Contracts\EventDispatcher\Event;

class WhatsAppSendEvent extends Event
{
    public function __construct(
        private int $whatsappId,
        private Lead $lead,
        private string $content,
        private ?string $templateName = null,
    ) {
    }

    public function getWhatsAppId(): int
    {
        return $this->whatsappId;
    }

    public function getLead(): Lead
    {
        return $this->lead;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }
}
