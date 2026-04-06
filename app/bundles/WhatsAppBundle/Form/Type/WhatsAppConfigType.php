<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\WhatsAppBundle\WhatsApp\TransportChain;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author iamjpsingh
 *
 * @extends AbstractType<array<mixed>>
 */
class WhatsAppConfigType extends AbstractType
{
    public function __construct(
        private TransportChain $transportChain,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param FormBuilderInterface<array<mixed>|null> $builder
     * @param array<string, mixed>                    $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('whatsapp_enabled', YesNoButtonGroupType::class, [
            'label' => 'mautic.whatsapp.config.form.enabled',
            'attr'  => [
                'tooltip' => 'mautic.whatsapp.config.form.enabled.tooltip',
            ],
            'data' => !empty($options['data']['whatsapp_enabled']),
        ]);

        $builder->add('whatsapp_phone_number_id', TextType::class, [
            'label'      => 'mautic.whatsapp.config.form.phone_number_id',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.whatsapp.config.form.phone_number_id.tooltip',
            ],
        ]);

        $builder->add('whatsapp_business_account_id', TextType::class, [
            'label'      => 'mautic.whatsapp.config.form.business_account_id',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.whatsapp.config.form.business_account_id.tooltip',
            ],
        ]);

        $builder->add('whatsapp_access_token', TextType::class, [
            'label'      => 'mautic.whatsapp.config.form.access_token',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.whatsapp.config.form.access_token.tooltip',
            ],
        ]);

        $builder->add('whatsapp_app_secret', TextType::class, [
            'label'      => 'mautic.whatsapp.config.form.app_secret',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.whatsapp.config.form.app_secret.tooltip',
            ],
        ]);

        $builder->add('whatsapp_webhook_verify_token', TextType::class, [
            'label'      => 'mautic.whatsapp.config.form.webhook_verify_token',
            'label_attr' => ['class' => 'control-label'],
            'required'   => false,
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.whatsapp.config.form.webhook_verify_token.tooltip',
            ],
        ]);

        $choices    = [];
        $transports = $this->transportChain->getEnabledTransports();
        foreach ($transports as $transportServiceId => $transport) {
            $choices[$this->translator->trans($transportServiceId)] = $transportServiceId;
        }

        $builder->add('whatsapp_transport', ChoiceType::class, [
            'label'      => 'mautic.whatsapp.config.select_default_transport',
            'label_attr' => ['class' => 'control-label'],
            'attr'       => [
                'class'   => 'form-control',
                'tooltip' => 'mautic.whatsapp.config.select_default_transport',
            ],
            'choices' => $choices,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'whatsappconfig';
    }
}
