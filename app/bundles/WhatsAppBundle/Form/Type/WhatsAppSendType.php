<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @author iamjpsingh
 *
 * @extends AbstractType<array<mixed>>
 */
class WhatsAppSendType extends AbstractType
{
    public function __construct(
        protected RouterInterface $router,
    ) {
    }

    /**
     * @param FormBuilderInterface<array<mixed>|null> $builder
     * @param array<string, mixed>                    $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'whatsapp',
            WhatsAppListType::class,
            [
                'label'      => 'mautic.whatsapp.campaign.send_message.select',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    'tooltip'  => 'mautic.whatsapp.campaign.send_message.tooltip',
                    'onchange' => 'Mautic.disabledWhatsAppAction()',
                ],
                'multiple'    => false,
                'required'    => true,
                'constraints' => [
                    new NotBlank(
                        ['message' => 'mautic.whatsapp.campaign.choosemessage.notblank']
                    ),
                ],
            ]
        );

        if (!empty($options['update_select'])) {
            $windowUrl = $this->router->generate(
                'mautic_whatsapp_action',
                [
                    'objectAction' => 'new',
                    'contentOnly'  => 1,
                    'updateSelect' => $options['update_select'],
                ]
            );

            $builder->add(
                'newWhatsAppButton',
                ButtonType::class,
                [
                    'attr' => [
                        'class'   => 'btn btn-primary btn-nospin',
                        'onclick' => 'Mautic.loadNewWindow({
                        "windowUrl": "'.$windowUrl.'"
                    })',
                        'icon' => 'ri-add-line',
                    ],
                    'label' => 'mautic.whatsapp.send.new.message',
                ]
            );

            // create button edit whatsapp message
            $windowUrlEdit = $this->router->generate(
                'mautic_whatsapp_action',
                [
                    'objectAction' => 'edit',
                    'objectId'     => 'whatsappId',
                    'contentOnly'  => 1,
                    'updateSelect' => $options['update_select'],
                ]
            );

            $builder->add(
                'editWhatsAppButton',
                ButtonType::class,
                [
                    'attr' => [
                        'class'    => 'btn btn-primary btn-nospin',
                        'onclick'  => 'Mautic.loadNewWindow(Mautic.standardWhatsAppUrl({"windowUrl": "'.$windowUrlEdit.'"}))',
                        'disabled' => !isset($options['data']['whatsapp']),
                        'icon'     => 'ri-edit-line',
                    ],
                    'label' => 'mautic.whatsapp.send.edit.message',
                ]
            );
        }
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
