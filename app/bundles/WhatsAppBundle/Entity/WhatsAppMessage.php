<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CategoryBundle\Entity\Category;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Mautic\CoreBundle\Entity\UuidInterface;
use Mautic\CoreBundle\Entity\UuidTrait;
use Mautic\CoreBundle\Validator\EntityEvent;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Form\Validator\Constraints\LeadListAccess;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class WhatsAppMessage extends FormEntity implements UuidInterface
{
    use UuidTrait;

    public const MESSAGE_TYPE_TEMPLATE    = 'template';
    public const MESSAGE_TYPE_SESSION     = 'session';
    public const MESSAGE_TYPE_MEDIA       = 'media';
    public const MESSAGE_TYPE_INTERACTIVE = 'interactive';

    public const MEDIA_TYPE_IMAGE    = 'image';
    public const MEDIA_TYPE_VIDEO    = 'video';
    public const MEDIA_TYPE_DOCUMENT = 'document';
    public const MEDIA_TYPE_AUDIO    = 'audio';

    public const INTERACTIVE_TYPE_BUTTON = 'button';
    public const INTERACTIVE_TYPE_LIST   = 'list';

    private ?int $id = null;

    private ?string $name = null;

    private ?string $description = null;

    private ?string $message = null;

    private string $messageType = self::MESSAGE_TYPE_TEMPLATE;

    private ?string $templateName = null;

    private ?string $templateLanguage = null;

    /**
     * @var array<string, mixed>
     */
    private array $templateComponents = [];

    private ?string $mediaUrl = null;

    private ?string $mediaType = null;

    private ?string $interactiveType = null;

    /**
     * @var array<string, mixed>
     */
    private array $interactiveData = [];

    private int $sentCount = 0;

    private int $readCount = 0;

    private int $deliveredCount = 0;

    private ?\DateTimeInterface $publishUp = null;

    private ?\DateTimeInterface $publishDown = null;

    private ?Category $category = null;

    /**
     * @var ArrayCollection<int, LeadList>
     */
    private Collection $lists;

    /**
     * @var ArrayCollection<int, WhatsAppStat>
     */
    private Collection $stats;

    private int $pendingCount = 0;

    public function __construct()
    {
        $this->lists = new ArrayCollection();
        $this->stats = new ArrayCollection();
    }

    public function __clone()
    {
        $this->id             = null;
        $this->stats          = new ArrayCollection();
        $this->sentCount      = 0;
        $this->readCount      = 0;
        $this->deliveredCount = 0;

        parent::__clone();
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('whatsapp_messages')
            ->setCustomRepositoryClass(WhatsAppMessageRepository::class);

        $builder->addIdColumns();

        $builder->createField('message', 'text')
            ->nullable()
            ->build();

        $builder->createField('messageType', 'string')
            ->columnName('message_type')
            ->length(20)
            ->build();

        $builder->createField('templateName', 'string')
            ->columnName('template_name')
            ->nullable()
            ->length(255)
            ->build();

        $builder->createField('templateLanguage', 'string')
            ->columnName('template_language')
            ->nullable()
            ->length(10)
            ->build();

        $builder->createField('templateComponents', 'json')
            ->columnName('template_components')
            ->nullable()
            ->build();

        $builder->createField('mediaUrl', 'string')
            ->columnName('media_url')
            ->nullable()
            ->length(2048)
            ->build();

        $builder->createField('mediaType', 'string')
            ->columnName('media_type')
            ->nullable()
            ->length(20)
            ->build();

        $builder->createField('interactiveType', 'string')
            ->columnName('interactive_type')
            ->nullable()
            ->length(20)
            ->build();

        $builder->createField('interactiveData', 'json')
            ->columnName('interactive_data')
            ->nullable()
            ->build();

        $builder->addPublishDates();

        $builder->createField('sentCount', 'integer')
            ->columnName('sent_count')
            ->build();

        $builder->createField('readCount', 'integer')
            ->columnName('read_count')
            ->build();

        $builder->createField('deliveredCount', 'integer')
            ->columnName('delivered_count')
            ->build();

        $builder->addCategory();

        $builder->createManyToMany('lists', LeadList::class)
            ->setJoinTable('whatsapp_message_list_xref')
            ->setIndexBy('id')
            ->addInverseJoinColumn('leadlist_id', 'id', false, false, 'CASCADE')
            ->addJoinColumn('whatsapp_message_id', 'id', false, false, 'CASCADE')
            ->fetchExtraLazy()
            ->build();

        $builder->createOneToMany('stats', WhatsAppStat::class)
            ->setIndexBy('id')
            ->mappedBy('whatsappMessage')
            ->cascadePersist()
            ->fetchExtraLazy()
            ->build();

        static::addUuidField($builder);
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint(
            'name',
            new NotBlank(
                [
                    'message' => 'mautic.core.name.required',
                ]
            )
        );

        $metadata->addPropertyConstraint(
            'messageType',
            new Choice(
                [
                    'choices' => [
                        self::MESSAGE_TYPE_TEMPLATE,
                        self::MESSAGE_TYPE_SESSION,
                        self::MESSAGE_TYPE_MEDIA,
                        self::MESSAGE_TYPE_INTERACTIVE,
                    ],
                    'message' => 'mautic.whatsapp.message_type.invalid',
                ]
            )
        );

        $metadata->addConstraint(new Callback(
            function (WhatsAppMessage $entity, ExecutionContextInterface $context): void {
                // Validate template fields when message type is template
                if (self::MESSAGE_TYPE_TEMPLATE === $entity->getMessageType()) {
                    if (empty($entity->getTemplateName())) {
                        $context->buildViolation('mautic.whatsapp.template_name.required')
                            ->atPath('templateName')
                            ->addViolation();
                    }
                    if (empty($entity->getTemplateLanguage())) {
                        $context->buildViolation('mautic.whatsapp.template_language.required')
                            ->atPath('templateLanguage')
                            ->addViolation();
                    }
                }

                // Validate media fields when message type is media
                if (self::MESSAGE_TYPE_MEDIA === $entity->getMessageType()) {
                    if (empty($entity->getMediaUrl())) {
                        $context->buildViolation('mautic.whatsapp.media_url.required')
                            ->atPath('mediaUrl')
                            ->addViolation();
                    }
                    if (empty($entity->getMediaType())) {
                        $context->buildViolation('mautic.whatsapp.media_type.required')
                            ->atPath('mediaType')
                            ->addViolation();
                    }
                }

                // Validate session message has body content
                if (self::MESSAGE_TYPE_SESSION === $entity->getMessageType()) {
                    if (empty($entity->getMessage())) {
                        $context->buildViolation('mautic.whatsapp.message.required')
                            ->atPath('message')
                            ->addViolation();
                    }
                }

                // Validate interactive fields
                if (self::MESSAGE_TYPE_INTERACTIVE === $entity->getMessageType()) {
                    if (empty($entity->getInteractiveType())) {
                        $context->buildViolation('mautic.whatsapp.interactive_type.required')
                            ->atPath('interactiveType')
                            ->addViolation();
                    }
                    if (empty($entity->getInteractiveData())) {
                        $context->buildViolation('mautic.whatsapp.interactive_data.required')
                            ->atPath('interactiveData')
                            ->addViolation();
                    }
                }

                // Validate lists for broadcast type
                $validator  = $context->getValidator();
                $violations = $validator->validate(
                    $entity->getLists(),
                    [
                        new LeadListAccess(),
                    ]
                );
                foreach ($violations as $violation) {
                    $context->buildViolation($violation->getMessage())
                        ->atPath('lists')
                        ->addViolation();
                }
            },
        ));

        $metadata->addConstraint(new EntityEvent());
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('whatsappMessage')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'message',
                    'messageType',
                    'language',
                    'category',
                ]
            )
            ->addProperties(
                [
                    'publishUp',
                    'publishDown',
                    'sentCount',
                    'readCount',
                    'deliveredCount',
                    'templateName',
                    'templateLanguage',
                    'templateComponents',
                    'mediaUrl',
                    'mediaType',
                    'interactiveType',
                    'interactiveData',
                ]
            )
            ->build();
    }

    /**
     * @param mixed $val
     */
    protected function isChanged($prop, $val): void
    {
        $getter  = 'get'.ucfirst($prop);
        $current = $this->$getter();

        if ('category' === $prop || 'list' === $prop) {
            $currentId = $current ? $current->getId() : '';
            $newId     = $val ? $val->getId() : null;
            if ($currentId != $newId) {
                $this->changes[$prop] = [$currentId, $newId];
            }
        } else {
            parent::isChanged($prop, $val);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->isChanged('name', $name);
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->isChanged('description', $description);
        $this->description = $description;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->isChanged('message', $message);
        $this->message = $message;

        return $this;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function setMessageType(string $messageType): self
    {
        $this->isChanged('messageType', $messageType);
        $this->messageType = $messageType;

        return $this;
    }

    public function getTemplateName(): ?string
    {
        return $this->templateName;
    }

    public function setTemplateName(?string $templateName): self
    {
        $this->isChanged('templateName', $templateName);
        $this->templateName = $templateName;

        return $this;
    }

    public function getTemplateLanguage(): ?string
    {
        return $this->templateLanguage;
    }

    public function setTemplateLanguage(?string $templateLanguage): self
    {
        $this->isChanged('templateLanguage', $templateLanguage);
        $this->templateLanguage = $templateLanguage;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplateComponents(): array
    {
        return $this->templateComponents;
    }

    /**
     * @param array<string, mixed> $templateComponents
     */
    public function setTemplateComponents(?array $templateComponents): self
    {
        $this->isChanged('templateComponents', $templateComponents ?? []);
        $this->templateComponents = $templateComponents ?? [];

        return $this;
    }

    public function getMediaUrl(): ?string
    {
        return $this->mediaUrl;
    }

    public function setMediaUrl(?string $mediaUrl): self
    {
        $this->isChanged('mediaUrl', $mediaUrl);
        $this->mediaUrl = $mediaUrl;

        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->mediaType;
    }

    public function setMediaType(?string $mediaType): self
    {
        $this->isChanged('mediaType', $mediaType);
        $this->mediaType = $mediaType;

        return $this;
    }

    public function getInteractiveType(): ?string
    {
        return $this->interactiveType;
    }

    public function setInteractiveType(?string $interactiveType): self
    {
        $this->isChanged('interactiveType', $interactiveType);
        $this->interactiveType = $interactiveType;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getInteractiveData(): array
    {
        return $this->interactiveData;
    }

    /**
     * @param array<string, mixed> $interactiveData
     */
    public function setInteractiveData(?array $interactiveData): self
    {
        $this->isChanged('interactiveData', $interactiveData ?? []);
        $this->interactiveData = $interactiveData ?? [];

        return $this;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): self
    {
        $this->sentCount = $sentCount;

        return $this;
    }

    public function getReadCount(): int
    {
        return $this->readCount;
    }

    public function setReadCount(int $readCount): self
    {
        $this->readCount = $readCount;

        return $this;
    }

    public function getDeliveredCount(): int
    {
        return $this->deliveredCount;
    }

    public function setDeliveredCount(int $deliveredCount): self
    {
        $this->deliveredCount = $deliveredCount;

        return $this;
    }

    public function getPublishUp(): ?\DateTimeInterface
    {
        return $this->publishUp;
    }

    public function setPublishUp(?\DateTimeInterface $publishUp): self
    {
        $this->isChanged('publishUp', $publishUp);
        $this->publishUp = $publishUp;

        return $this;
    }

    public function getPublishDown(): ?\DateTimeInterface
    {
        return $this->publishDown;
    }

    public function setPublishDown(?\DateTimeInterface $publishDown): self
    {
        $this->isChanged('publishDown', $publishDown);
        $this->publishDown = $publishDown;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->isChanged('category', $category);
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, LeadList>
     */
    public function getLists(): Collection
    {
        return $this->lists;
    }

    public function addList(LeadList $list): self
    {
        $this->lists[] = $list;

        return $this;
    }

    public function removeList(LeadList $list): void
    {
        $this->lists->removeElement($list);
    }

    /**
     * @return Collection<int, WhatsAppStat>
     */
    public function getStats(): Collection
    {
        return $this->stats;
    }

    public function clearStats(): void
    {
        $this->stats = new ArrayCollection();
    }

    public function getPendingCount(): int
    {
        return $this->pendingCount;
    }

    public function setPendingCount(int $pendingCount): self
    {
        $this->pendingCount = $pendingCount;

        return $this;
    }
}
