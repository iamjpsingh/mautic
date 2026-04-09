<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\EntityLookupType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array<mixed>>
 */
class WhatsAppListType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'modal_route'         => 'mautic_whatsapp_action',
                'modal_header'        => 'mautic.whatsapp.header.new',
                'model'               => 'whatsapp',
                'model_lookup_method' => 'getLookupResults',
                'lookup_arguments'    => fn (Options $options): array => [
                    'type'    => WhatsAppType::class,
                    'filter'  => '$data',
                    'limit'   => 0,
                    'start'   => 0,
                    'options' => [
                        'ignore_ids' => $options['ignore_ids'],
                    ],
                ],
                'ajax_lookup_action' => function (Options $options): string {
                    $query = [
                        'ignore_ids' => $options['ignore_ids'],
                    ];

                    return 'whatsapp:getLookupChoiceList&'.http_build_query($query);
                },
                'multiple'   => false,
                'required'   => false,
                'ignore_ids' => [],
            ]
        );
    }

    public function getParent(): ?string
    {
        return EntityLookupType::class;
    }
}
