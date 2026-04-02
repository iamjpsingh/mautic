<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Exception;

class NumberNotFoundException extends \Exception
{
    public function __construct(
        private string $number,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        if ('' === $message) {
            $message = sprintf('Number %s not found', $number);
        }

        parent::__construct($message, $code, $previous);
    }

    public function getNumber(): string
    {
        return $this->number;
    }
}
