<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Provides a select list of WhatsApp message entities.
 *
 * @extends AbstractType<array<mixed>>
 */
class WhatsAppListType extends AbstractType
{
    public function getParent(): string
    {
        return ChoiceType::class;
    }
}
