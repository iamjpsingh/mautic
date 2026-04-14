<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

/**
 * Adds per-contact WhatsApp session tracking.
 *
 * Tracks the timestamp of the contact's last inbound WhatsApp message so
 * we can enforce Meta's 24-hour customer service window: free-form
 * (session) messages may only be delivered within 24 hours of the
 * contact's most recent inbound message. Outside that window Meta
 * silently rejects the send, so we gate it at the application layer.
 *
 * @author iamjpsingh
 */
final class Version20260414000000 extends PreUpAssertionMigration
{
    private string $sessionsTable;

    private string $leadsTable;

    private function initTableNames(): void
    {
        $this->sessionsTable = "{$this->prefix}whatsapp_contact_sessions";
        $this->leadsTable    = "{$this->prefix}leads";
    }

    protected function preUpAssertions(): void
    {
        $this->initTableNames();

        $this->skipAssertion(
            fn (Schema $schema) => $schema->hasTable($this->sessionsTable),
            "Table {$this->sessionsTable} already exists"
        );
    }

    public function up(Schema $schema): void
    {
        $this->initTableNames();

        $this->addSql("CREATE TABLE `{$this->sessionsTable}` (
    `lead_id`          BIGINT UNSIGNED NOT NULL,
    `last_inbound_at`  DATETIME DEFAULT NULL,
    `last_outbound_at` DATETIME DEFAULT NULL,
    `is_opted_out`     TINYINT(1) NOT NULL DEFAULT 0,
    `opted_out_at`     DATETIME DEFAULT NULL,
    PRIMARY KEY (`lead_id`)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        $leadFk = $this->generatePropertyName($this->sessionsTable, 'fk', ['lead_id']);
        $this->addSql("ALTER TABLE `{$this->sessionsTable}` ADD CONSTRAINT `{$leadFk}` FOREIGN KEY (`lead_id`) REFERENCES `{$this->leadsTable}` (`id`) ON DELETE CASCADE");

        $idxInbound = $this->generatePropertyName($this->sessionsTable, 'idx', ['last_inbound_at']);
        $this->addSql("CREATE INDEX `{$idxInbound}` ON `{$this->sessionsTable}` (`last_inbound_at`)");
    }

    public function down(Schema $schema): void
    {
        $this->initTableNames();

        if ($schema->hasTable($this->sessionsTable)) {
            $this->addSql("DROP TABLE `{$this->sessionsTable}`");
        }
    }
}
