<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class WhatsAppStatusEvent extends Event
{
    /**
     * @param array<int, array{message_id: string, status: string, timestamp: string, recipient_id: string}> $statuses
     */
    public function __construct(
        private array $statuses,
    ) {
    }

    /**
     * @return array<int, array{message_id: string, status: string, timestamp: string, recipient_id: string}>
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }
}
