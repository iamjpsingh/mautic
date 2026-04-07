<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CategoryBundle\Form\Type\CategoryListType;
use Mautic\CoreBundle\Form\DataTransformer\IdToEntityModelTransformer;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Mautic\CoreBundle\Form\Type\FormButtonsType;
use Mautic\CoreBundle\Form\Type\PublishDownDateType;
use Mautic\CoreBundle\Form\Type\PublishUpDateType;
use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Form\Type\LeadListType;
use Mautic\WhatsAppBundle\Entity\WhatsAppMessage;
use Mautic\WhatsAppBundle\Entity\WhatsAppTemplate;
use Mautic\WhatsAppBundle\Entity\WhatsAppTemplateRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<WhatsAppMessage>
 */
class WhatsAppType extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new CleanFormSubscriber(['content' => 'html', 'customHtml' => 'html']));
        $builder->addEventSubscriber(new FormExitSubscriber('whatsapp.message', $options));

        $builder->add(
            'name',
            TextType::class,
            [
                'label'      => 'mautic.whatsapp.form.internal.name',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
            ]
        );

        $builder->add(
            'messageType',
            ChoiceType::class,
            [
                'label'      => 'mautic.whatsapp.form.message_type',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.whatsappMessageTypeChanged(this)',
                ],
                'choices' => [
                    'mautic.whatsapp.message_type.template'    => WhatsAppMessage::MESSAGE_TYPE_TEMPLATE,
                    'mautic.whatsapp.message_type.session'     => WhatsAppMessage::MESSAGE_TYPE_SESSION,
                    'mautic.whatsapp.message_type.media'       => WhatsAppMessage::MESSAGE_TYPE_MEDIA,
                    'mautic.whatsapp.message_type.interactive' => WhatsAppMessage::MESSAGE_TYPE_INTERACTIVE,
                ],
                'expanded' => false,
                'multiple' => false,
            ]
        );

        // Template selector — populated from approved WhatsApp templates
        $templateChoices = $this->buildTemplateChoices();
        $builder->add(
            'templateId',
            ChoiceType::class,
            [
                'label'      => 'mautic.whatsapp.form.template_select',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    'onchange' => 'Mautic.whatsappTemplateSelected(this)',
                ],
                'choices'     => $templateChoices,
                'required'    => false,
                'placeholder' => 'mautic.whatsapp.form.template_select.placeholder',
                'mapped'      => false,
            ]
        );

        // Template fields (auto-filled from templateId selection)
        $builder->add(
            'templateName',
            TextType::class,
            [
                'label'      => 'mautic.whatsapp.form.template_name',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'    => 'form-control',
                    'tooltip'  => 'mautic.whatsapp.form.template_name.help',
                    'readonly' => 'readonly',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'templateLanguage',
            TextType::class,
            [
                'label'      => 'mautic.whatsapp.form.template_language',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'placeholder' => 'en_US',
                    'readonly'    => 'readonly',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'templateComponents',
            HiddenType::class,
            [
                'label'    => 'mautic.whatsapp.form.template_components',
                'required' => false,
                'attr'     => [
                    'class' => 'form-control whatsapp-template-components',
                ],
            ]
        );

        // Session / text message body
        $builder->add(
            'message',
            TextareaType::class,
            [
                'label'      => 'mautic.whatsapp.form.message',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'                => 'form-control',
                    'data-token-activator' => '{',
                    'data-token-visual'    => 'false',
                    'rows'                 => 6,
                    'maxlength'            => 4096,
                ],
                'required' => false,
            ]
        );

        // Media fields
        $builder->add(
            'mediaUrl',
            UrlType::class,
            [
                'label'      => 'mautic.whatsapp.form.media_url',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.whatsapp.form.media_url.help',
                ],
                'required' => false,
            ]
        );

        $builder->add(
            'mediaType',
            ChoiceType::class,
            [
                'label'      => 'mautic.whatsapp.form.media_type',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
                'choices'    => [
                    'mautic.whatsapp.media_type.image'    => WhatsAppMessage::MEDIA_TYPE_IMAGE,
                    'mautic.whatsapp.media_type.video'    => WhatsAppMessage::MEDIA_TYPE_VIDEO,
                    'mautic.whatsapp.media_type.document' => WhatsAppMessage::MEDIA_TYPE_DOCUMENT,
                    'mautic.whatsapp.media_type.audio'    => WhatsAppMessage::MEDIA_TYPE_AUDIO,
                ],
                'required'    => false,
                'placeholder' => '',
            ]
        );

        // Interactive fields
        $builder->add(
            'interactiveType',
            ChoiceType::class,
            [
                'label'      => 'mautic.whatsapp.form.interactive_type',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
                'choices'    => [
                    'mautic.whatsapp.interactive_type.button' => WhatsAppMessage::INTERACTIVE_TYPE_BUTTON,
                    'mautic.whatsapp.interactive_type.list'   => WhatsAppMessage::INTERACTIVE_TYPE_LIST,
                ],
                'required'    => false,
                'placeholder' => '',
            ]
        );

        $builder->add(
            'interactiveData',
            HiddenType::class,
            [
                'label'    => 'mautic.whatsapp.form.interactive_data',
                'required' => false,
                'attr'     => [
                    'class' => 'form-control whatsapp-interactive-data',
                ],
            ]
        );

        $builder->add('isPublished', YesNoButtonGroupType::class, [
            'label' => 'mautic.core.form.available',
        ]);

        // Segment lists for broadcast
        $transformer = new IdToEntityModelTransformer($this->em, LeadList::class, 'id', true);
        $builder->add(
            $builder->create(
                'lists',
                LeadListType::class,
                [
                    'label'      => 'mautic.whatsapp.form.list',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class' => 'form-control',
                    ],
                    'multiple' => true,
                    'expanded' => false,
                    'required' => false,
                ]
            )
                ->addModelTransformer($transformer)
        );

        $builder->add('publishUp', PublishUpDateType::class);
        $builder->add('publishDown', PublishDownDateType::class);

        $builder->add(
            'category',
            CategoryListType::class,
            [
                'bundle' => 'whatsapp',
            ]
        );

        if (!empty($options['update_select'])) {
            $builder->add(
                'buttons',
                FormButtonsType::class,
                [
                    'apply_text' => false,
                ]
            );
            $builder->add(
                'updateSelect',
                HiddenType::class,
                [
                    'data'   => $options['update_select'],
                    'mapped' => false,
                ]
            );
        } else {
            $builder->add(
                'buttons',
                FormButtonsType::class
            );
        }

        if (!empty($options['action'])) {
            $builder->setAction($options['action']);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'data_class' => WhatsAppMessage::class,
            ]
        );

        $resolver->setDefined(['update_select']);
    }

    /**
     * Build choices array from approved WhatsApp templates.
     *
     * @return array<string, int>
     */
    private function buildTemplateChoices(): array
    {
        /** @var WhatsAppTemplateRepository $repository */
        $repository = $this->em->getRepository(WhatsAppTemplate::class);
        $templates  = $repository->findApproved();
        $choices    = [];

        foreach ($templates as $template) {
            $label = sprintf(
                '%s (%s) — %s',
                $template->getName(),
                $template->getLanguage(),
                $template->getCategory() ?? 'N/A'
            );
            $choices[$label] = $template->getId();
        }

        return $choices;
    }
}
