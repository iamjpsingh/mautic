<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use Mautic\WhatsAppBundle\Entity\WhatsAppMessage;

class WhatsAppMessageEvent extends CommonEvent
{
    public function __construct(WhatsAppMessage $message, bool $isNew = false)
    {
        $this->entity = $message;
        $this->isNew  = $isNew;
    }

    public function getWhatsAppMessage(): WhatsAppMessage
    {
        return $this->entity;
    }

    public function setWhatsAppMessage(WhatsAppMessage $message): void
    {
        $this->entity = $message;
    }
}
