<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Form type for "WhatsApp Reply" campaign decision.
 *
 * Supports three conditions:
 *  - reply contains keyword (pattern)
 *  - any reply (leave pattern empty)
 *  - no reply timeout (timeout field in hours)
 *
 * @extends AbstractType<array<mixed>>
 */
class CampaignReplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'match_mode',
            ChoiceType::class,
            [
                'label'   => 'mautic.whatsapp.reply.match_mode',
                'choices' => [
                    'mautic.whatsapp.reply.match_mode.any_reply'  => 'any',
                    'mautic.whatsapp.reply.match_mode.keyword'    => 'keyword',
                    'mautic.whatsapp.reply.match_mode.no_reply'   => 'no_reply',
                ],
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.whatsapp.reply.match_mode.tooltip',
                ],
                'required' => true,
            ]
        );

        $builder->add(
            'pattern',
            TextType::class,
            [
                'label'      => 'mautic.whatsapp.reply_pattern',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.whatsapp.reply_pattern.tooltip',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'no_reply_timeout',
            IntegerType::class,
            [
                'label'      => 'mautic.whatsapp.reply.no_reply_timeout',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.whatsapp.reply.no_reply_timeout.tooltip',
                ],
                'required' => false,
                'data'     => 24,
            ]
        );
    }
}
