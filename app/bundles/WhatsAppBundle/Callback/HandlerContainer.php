<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Callback;

use Mautic\WhatsAppBundle\Exception\CallbackHandlerNotFound;

class HandlerContainer
{
    /**
     * @var array<string, CallbackInterface>
     */
    private array $handlers = [];

    public function registerHandler(CallbackInterface $handler): void
    {
        $this->handlers[$handler->getTransportName()] = $handler;
    }

    /**
     * @throws CallbackHandlerNotFound
     */
    public function getHandler(string $transportName): CallbackInterface
    {
        if (!isset($this->handlers[$transportName])) {
            throw new CallbackHandlerNotFound(sprintf('%s has not been registered', $transportName));
        }

        return $this->handlers[$transportName];
    }
}
