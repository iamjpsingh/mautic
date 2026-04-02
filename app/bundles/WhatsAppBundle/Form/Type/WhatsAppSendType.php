<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Form type for "Send WhatsApp Message" campaign action (session messages).
 *
 * @extends AbstractType<array<mixed>>
 */
class WhatsAppSendType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'whatsapp',
            WhatsAppListType::class,
            [
                'label'      => 'mautic.whatsapp.campaign.send_message.select',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.whatsapp.campaign.send_message.tooltip',
                ],
                'multiple'   => false,
                'required'   => true,
            ]
        );
    }
}
