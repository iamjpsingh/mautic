<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * @author iamjpsingh
 */
class WhatsAppTemplate
{
    private ?int $id = null;

    private ?string $metaTemplateId = null;

    private ?string $name = null;

    private string $status = 'PENDING';

    private string $language = 'en';

    private ?string $category = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $components = null;

    private ?\DateTimeInterface $lastSyncedAt = null;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('whatsapp_templates')
            ->setCustomRepositoryClass(WhatsAppTemplateRepository::class);

        $builder->addId();

        $builder->createField('metaTemplateId', 'string')
            ->columnName('meta_template_id')
            ->length(255)
            ->nullable()
            ->build();

        $builder->createField('name', 'string')
            ->length(255)
            ->build();

        $builder->createField('status', 'string')
            ->length(20)
            ->build();

        $builder->createField('language', 'string')
            ->length(10)
            ->build();

        $builder->createField('category', 'string')
            ->length(50)
            ->nullable()
            ->build();

        $builder->createField('components', 'json')
            ->nullable()
            ->build();

        $builder->createField('lastSyncedAt', 'datetime')
            ->columnName('last_synced_at')
            ->nullable()
            ->build();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMetaTemplateId(): ?string
    {
        return $this->metaTemplateId;
    }

    public function setMetaTemplateId(?string $metaTemplateId): self
    {
        $this->metaTemplateId = $metaTemplateId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getComponents(): ?array
    {
        return $this->components;
    }

    /**
     * @param array<string, mixed>|null $components
     */
    public function setComponents(?array $components): self
    {
        $this->components = $components;

        return $this;
    }

    public function getLastSyncedAt(): ?\DateTimeInterface
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeInterface $lastSyncedAt): self
    {
        $this->lastSyncedAt = $lastSyncedAt;

        return $this;
    }
}
