<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WhatsAppTemplateSendType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'whatsapp',
            WhatsAppListType::class,
            [
                'label'      => 'mautic.whatsapp.campaign.send_template.select',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.whatsapp.campaign.send_template.tooltip',
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
        return 'whatsapp_template_send_list';
    }
}
