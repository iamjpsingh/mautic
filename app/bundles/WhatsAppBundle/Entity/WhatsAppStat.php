<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;

class WhatsAppStat
{
    public const TABLE_NAME = 'whatsapp_message_stats';

    public const STATUS_SENT      = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ      = 'read';
    public const STATUS_FAILED    = 'failed';

    private ?string $id = null;

    private ?WhatsAppMessage $whatsappMessage = null;

    private ?Lead $lead = null;

    private ?LeadList $list = null;

    private ?IpAddress $ipAddress = null;

    private ?\DateTimeInterface $dateSent = null;

    private ?\DateTimeInterface $dateDelivered = null;

    private ?\DateTimeInterface $dateRead = null;

    private ?string $trackingHash = null;

    /**
     * Meta Cloud API message ID (wamid).
     */
    private ?string $whatsappMessageId = null;

    private ?string $source = null;

    private ?int $sourceId = null;

    /**
     * @var array<string, mixed>
     */
    private array $tokens = [];

    /**
     * @var array<string, mixed>
     */
    private array $details = [];

    private ?bool $isFailed = false;

    private string $status = self::STATUS_SENT;

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass(WhatsAppStatRepository::class)
            ->addIndex(['whatsapp_message_id', 'lead_id'], 'stat_whatsapp_search')
            ->addIndex(['tracking_hash'], 'stat_whatsapp_hash_search')
            ->addIndex(['source', 'source_id'], 'stat_whatsapp_source_search')
            ->addIndex(['is_failed'], 'stat_whatsapp_failed_search')
            ->addIndex(['status'], 'stat_whatsapp_status_search')
            ->addIndex(['wa_message_id'], 'stat_whatsapp_wamid_search');

        $builder->addBigIntIdField();

        $builder->createManyToOne('whatsappMessage', WhatsAppMessage::class)
            ->inversedBy('stats')
            ->addJoinColumn('whatsapp_message_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->addLead(true, 'SET NULL');

        $builder->createManyToOne('list', LeadList::class)
            ->addJoinColumn('list_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->addIpAddress(true);

        $builder->createField('dateSent', 'datetime')
            ->columnName('date_sent')
            ->nullable()
            ->build();

        $builder->createField('dateDelivered', 'datetime')
            ->columnName('date_delivered')
            ->nullable()
            ->build();

        $builder->createField('dateRead', 'datetime')
            ->columnName('date_read')
            ->nullable()
            ->build();

        $builder->createField('trackingHash', 'string')
            ->columnName('tracking_hash')
            ->nullable()
            ->build();

        $builder->createField('whatsappMessageId', 'string')
            ->columnName('wa_message_id')
            ->nullable()
            ->length(255)
            ->build();

        $builder->createField('isFailed', 'boolean')
            ->columnName('is_failed')
            ->nullable()
            ->build();

        $builder->createField('status', 'string')
            ->length(20)
            ->build();

        $builder->createField('source', 'string')
            ->nullable()
            ->build();

        $builder->createField('sourceId', 'integer')
            ->columnName('source_id')
            ->nullable()
            ->build();

        $builder->createField('tokens', 'array')
            ->nullable()
            ->build();

        $builder->addField('details', Types::JSON);
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('stat')
            ->addProperties(
                [
                    'id',
                    'ipAddress',
                    'dateSent',
                    'dateDelivered',
                    'dateRead',
                    'isFailed',
                    'status',
                    'source',
                    'sourceId',
                    'trackingHash',
                    'whatsappMessageId',
                    'lead',
                    'whatsappMessage',
                    'details',
                ]
            )
            ->build();
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function getWhatsappMessage(): ?WhatsAppMessage
    {
        return $this->whatsappMessage;
    }

    public function setWhatsappMessage(WhatsAppMessage $whatsappMessage): self
    {
        $this->whatsappMessage = $whatsappMessage;

        return $this;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(Lead $lead): self
    {
        $this->lead = $lead;

        return $this;
    }

    public function getList(): ?LeadList
    {
        return $this->list;
    }

    public function setList(LeadList $list): self
    {
        $this->list = $list;

        return $this;
    }

    public function getIpAddress(): ?IpAddress
    {
        return $this->ipAddress;
    }

    public function setIpAddress(IpAddress $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getDateSent(): ?\DateTimeInterface
    {
        return $this->dateSent;
    }

    public function setDateSent(?\DateTimeInterface $dateSent): self
    {
        $this->dateSent = $dateSent;

        return $this;
    }

    public function getDateDelivered(): ?\DateTimeInterface
    {
        return $this->dateDelivered;
    }

    public function setDateDelivered(?\DateTimeInterface $dateDelivered): self
    {
        $this->dateDelivered = $dateDelivered;

        return $this;
    }

    public function getDateRead(): ?\DateTimeInterface
    {
        return $this->dateRead;
    }

    public function setDateRead(?\DateTimeInterface $dateRead): self
    {
        $this->dateRead = $dateRead;

        return $this;
    }

    public function getTrackingHash(): ?string
    {
        return $this->trackingHash;
    }

    public function setTrackingHash(?string $trackingHash): self
    {
        $this->trackingHash = $trackingHash;

        return $this;
    }

    public function getWhatsappMessageId(): ?string
    {
        return $this->whatsappMessageId;
    }

    public function setWhatsappMessageId(?string $whatsappMessageId): self
    {
        $this->whatsappMessageId = $whatsappMessageId;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSourceId(?int $sourceId): self
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * @param array<string, mixed> $tokens
     */
    public function setTokens(array $tokens): self
    {
        $this->tokens = $tokens;

        return $this;
    }

    public function isFailed(): ?bool
    {
        return $this->isFailed;
    }

    public function setIsFailed(?bool $isFailed): self
    {
        $this->isFailed = $isFailed;

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

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @param array<string, mixed> $details
     */
    public function setDetails(array $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function addDetail(string $type, string $detail): self
    {
        $this->details[$type][] = $detail;

        return $this;
    }
}
