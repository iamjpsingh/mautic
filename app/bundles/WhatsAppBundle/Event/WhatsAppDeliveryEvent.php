<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Event;

use Mautic\LeadBundle\Entity\Lead;
use Symfony\Contracts\EventDispatcher\Event;

class WhatsAppDeliveryEvent extends Event
{
    public function __construct(
        private Lead $contact,
        private string $messageId,
        private string $status,
        private ?int $timestamp = null,
    ) {
    }

    public function getContact(): Lead
    {
        return $this->contact;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }
}
