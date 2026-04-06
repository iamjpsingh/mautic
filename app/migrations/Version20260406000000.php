<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

final class Version20260406000000 extends PreUpAssertionMigration
{
    private string $messagesTable;

    private string $statsTable;

    private string $xrefTable;

    private string $leadsTable;

    private string $leadListsTable;

    private string $categoriesTable;

    private function initTableNames(): void
    {
        $this->messagesTable   = "{$this->prefix}whatsapp_messages";
        $this->statsTable      = "{$this->prefix}whatsapp_message_stats";
        $this->xrefTable       = "{$this->prefix}whatsapp_message_list_xref";
        $this->leadsTable      = "{$this->prefix}leads";
        $this->leadListsTable  = "{$this->prefix}lead_lists";
        $this->categoriesTable = "{$this->prefix}categories";
    }

    protected function preUpAssertions(): void
    {
        $this->initTableNames();

        $this->skipAssertion(
            fn (Schema $schema) => $schema->hasTable($this->messagesTable),
            "Table {$this->messagesTable} already exists"
        );

        $this->skipAssertion(
            fn (Schema $schema) => $schema->hasTable($this->statsTable),
            "Table {$this->statsTable} already exists"
        );

        $this->skipAssertion(
            fn (Schema $schema) => $schema->hasTable($this->xrefTable),
            "Table {$this->xrefTable} already exists"
        );
    }

    public function up(Schema $schema): void
    {
        $this->initTableNames();

        // Create whatsapp_messages table
        $this->addSql("CREATE TABLE `{$this->messagesTable}` (
    `id`                   INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `category_id`          INT UNSIGNED DEFAULT NULL,
    `name`                 VARCHAR(255) NOT NULL,
    `message`              LONGTEXT DEFAULT NULL,
    `message_type`         VARCHAR(20) NOT NULL DEFAULT 'template',
    `template_name`        VARCHAR(255) DEFAULT NULL,
    `template_language`    VARCHAR(10) DEFAULT 'en',
    `template_components`  JSON DEFAULT NULL,
    `media_url`            VARCHAR(2048) DEFAULT NULL,
    `media_type`           VARCHAR(20) DEFAULT NULL,
    `interactive_type`     VARCHAR(20) DEFAULT NULL,
    `interactive_data`     JSON DEFAULT NULL,
    `sent_count`           INT NOT NULL DEFAULT 0,
    `delivered_count`      INT NOT NULL DEFAULT 0,
    `read_count`           INT NOT NULL DEFAULT 0,
    `description`          LONGTEXT DEFAULT NULL,
    `lang`                 VARCHAR(10) NOT NULL DEFAULT 'en',
    `is_published`         TINYINT(1) NOT NULL DEFAULT 0,
    `publish_up`           DATETIME DEFAULT NULL,
    `publish_down`         DATETIME DEFAULT NULL,
    `date_added`           DATETIME DEFAULT NULL,
    `date_modified`        DATETIME DEFAULT NULL,
    `created_by`           INT DEFAULT NULL,
    `created_by_user`      VARCHAR(255) DEFAULT NULL,
    `modified_by`          INT DEFAULT NULL,
    `modified_by_user`     VARCHAR(255) DEFAULT NULL,
    `checked_out`          INT DEFAULT NULL,
    `checked_out_by`       VARCHAR(255) DEFAULT NULL,
    `checked_out_by_user`  VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        // Add FK for category_id
        $categoryFk = $this->generatePropertyName($this->messagesTable, 'fk', ['category_id']);
        $this->addSql("ALTER TABLE `{$this->messagesTable}` ADD CONSTRAINT `{$categoryFk}` FOREIGN KEY (`category_id`) REFERENCES `{$this->categoriesTable}` (`id`) ON DELETE SET NULL");

        // Create whatsapp_message_stats table
        $this->addSql("CREATE TABLE `{$this->statsTable}` (
    `id`                   INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `whatsapp_message_id`  INT UNSIGNED NOT NULL,
    `lead_id`              BIGINT UNSIGNED NOT NULL,
    `list_id`              INT UNSIGNED DEFAULT NULL,
    `date_sent`            DATETIME NOT NULL,
    `date_delivered`       DATETIME DEFAULT NULL,
    `date_read`            DATETIME DEFAULT NULL,
    `tracking_hash`        VARCHAR(255) DEFAULT NULL,
    `wa_message_id`        VARCHAR(255) DEFAULT NULL,
    `source`               VARCHAR(255) DEFAULT NULL,
    `source_id`            INT DEFAULT NULL,
    `is_failed`            TINYINT(1) NOT NULL DEFAULT 0,
    `status`               VARCHAR(20) NOT NULL DEFAULT 'sent',
    `details`              JSON DEFAULT NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        // Add FKs for stats table
        $statsMessageFk = $this->generatePropertyName($this->statsTable, 'fk', ['whatsapp_message_id']);
        $statsLeadFk    = $this->generatePropertyName($this->statsTable, 'fk', ['lead_id']);
        $statsListFk    = $this->generatePropertyName($this->statsTable, 'fk', ['list_id']);

        $this->addSql("ALTER TABLE `{$this->statsTable}` ADD CONSTRAINT `{$statsMessageFk}` FOREIGN KEY (`whatsapp_message_id`) REFERENCES `{$this->messagesTable}` (`id`) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE `{$this->statsTable}` ADD CONSTRAINT `{$statsLeadFk}` FOREIGN KEY (`lead_id`) REFERENCES `{$this->leadsTable}` (`id`) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE `{$this->statsTable}` ADD CONSTRAINT `{$statsListFk}` FOREIGN KEY (`list_id`) REFERENCES `{$this->leadListsTable}` (`id`) ON DELETE SET NULL");

        // Add indexes on stats table
        $idxTrackingHash = $this->generatePropertyName($this->statsTable, 'idx', ['tracking_hash']);
        $idxWaMessageId  = $this->generatePropertyName($this->statsTable, 'idx', ['wa_message_id']);
        $idxLeadMessage  = $this->generatePropertyName($this->statsTable, 'idx', ['lead_id', 'whatsapp_message_id']);
        $idxStatus       = $this->generatePropertyName($this->statsTable, 'idx', ['status']);
        $idxIsFailed     = $this->generatePropertyName($this->statsTable, 'idx', ['is_failed']);

        $this->addSql("CREATE INDEX `{$idxTrackingHash}` ON `{$this->statsTable}` (`tracking_hash`)");
        $this->addSql("CREATE INDEX `{$idxWaMessageId}` ON `{$this->statsTable}` (`wa_message_id`)");
        $this->addSql("CREATE INDEX `{$idxLeadMessage}` ON `{$this->statsTable}` (`lead_id`, `whatsapp_message_id`)");
        $this->addSql("CREATE INDEX `{$idxStatus}` ON `{$this->statsTable}` (`status`)");
        $this->addSql("CREATE INDEX `{$idxIsFailed}` ON `{$this->statsTable}` (`is_failed`)");

        // Create whatsapp_message_list_xref table
        $this->addSql("CREATE TABLE `{$this->xrefTable}` (
    `whatsapp_message_id`  INT UNSIGNED NOT NULL,
    `leadlist_id`          INT UNSIGNED NOT NULL,
    PRIMARY KEY (`whatsapp_message_id`, `leadlist_id`)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        // Add FKs for xref table
        $xrefMessageFk = $this->generatePropertyName($this->xrefTable, 'fk', ['whatsapp_message_id']);
        $xrefListFk    = $this->generatePropertyName($this->xrefTable, 'fk', ['leadlist_id']);

        $this->addSql("ALTER TABLE `{$this->xrefTable}` ADD CONSTRAINT `{$xrefMessageFk}` FOREIGN KEY (`whatsapp_message_id`) REFERENCES `{$this->messagesTable}` (`id`) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE `{$this->xrefTable}` ADD CONSTRAINT `{$xrefListFk}` FOREIGN KEY (`leadlist_id`) REFERENCES `{$this->leadListsTable}` (`id`) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->initTableNames();

        if ($schema->hasTable($this->xrefTable)) {
            $this->addSql("DROP TABLE `{$this->xrefTable}`");
        }

        if ($schema->hasTable($this->statsTable)) {
            $this->addSql("DROP TABLE `{$this->statsTable}`");
        }

        if ($schema->hasTable($this->messagesTable)) {
            $this->addSql("DROP TABLE `{$this->messagesTable}`");
        }
    }
}
