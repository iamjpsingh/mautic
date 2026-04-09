<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefined(['update_select']);
    }

    public function getBlockPrefix(): string
    {
        return 'whatsapp_send_list';
    }
}
