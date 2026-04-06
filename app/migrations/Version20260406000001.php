<?php

declare(strict_types=1);

namespace Mautic\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

/**
 * @author iamjpsingh
 */
final class Version20260406000001 extends PreUpAssertionMigration
{
    private string $templatesTable;

    private function initTableNames(): void
    {
        $this->templatesTable = "{$this->prefix}whatsapp_templates";
    }

    protected function preUpAssertions(): void
    {
        $this->initTableNames();

        $this->skipAssertion(
            fn (Schema $schema) => $schema->hasTable($this->templatesTable),
            "Table {$this->templatesTable} already exists"
        );
    }

    public function up(Schema $schema): void
    {
        $this->initTableNames();

        $this->addSql("CREATE TABLE `{$this->templatesTable}` (
    `id`                INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `meta_template_id`  VARCHAR(255) DEFAULT NULL,
    `name`              VARCHAR(255) NOT NULL,
    `status`            VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    `language`          VARCHAR(10) NOT NULL DEFAULT 'en',
    `category`          VARCHAR(50) DEFAULT NULL,
    `components`        JSON DEFAULT NULL,
    `last_synced_at`    DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC;");

        $idxMetaTemplateId = $this->generatePropertyName($this->templatesTable, 'idx', ['meta_template_id']);
        $idxStatus         = $this->generatePropertyName($this->templatesTable, 'idx', ['status']);
        $idxName           = $this->generatePropertyName($this->templatesTable, 'idx', ['name']);

        $this->addSql("CREATE INDEX `{$idxMetaTemplateId}` ON `{$this->templatesTable}` (`meta_template_id`)");
        $this->addSql("CREATE INDEX `{$idxStatus}` ON `{$this->templatesTable}` (`status`)");
        $this->addSql("CREATE INDEX `{$idxName}` ON `{$this->templatesTable}` (`name`)");
    }

    public function down(Schema $schema): void
    {
        $this->initTableNames();

        if ($schema->hasTable($this->templatesTable)) {
            $this->addSql("DROP TABLE `{$this->templatesTable}`");
        }
    }
}
