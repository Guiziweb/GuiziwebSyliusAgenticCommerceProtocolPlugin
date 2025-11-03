<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create ProductFeedConfig table for OpenAI Product Feed configuration
 */
final class Version20251102074944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ProductFeedConfig table to store OpenAI Product Feed configuration per channel';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform &&
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on \'mysql\' or \'postgresql\'.'
        );

        $this->addSql('CREATE TABLE guiziweb_acp_product_feed_config (id INT NOT NULL, channel_id INT NOT NULL, feed_endpoint VARCHAR(255) DEFAULT NULL, feed_bearer_token VARCHAR(255) DEFAULT NULL, default_brand VARCHAR(255) DEFAULT NULL, default_weight VARCHAR(50) DEFAULT NULL, default_material VARCHAR(255) DEFAULT NULL, return_policy_url VARCHAR(255) DEFAULT NULL, return_window_days INT DEFAULT NULL, privacy_policy_url VARCHAR(255) DEFAULT NULL, terms_of_service_url VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAF9F9D372F5A1AA ON guiziweb_acp_product_feed_config (channel_id)');
        $this->addSql('CREATE INDEX idx_channel ON guiziweb_acp_product_feed_config (channel_id)');

        // Add sequence for PostgreSQL
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE guiziweb_acp_product_feed_config_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('ALTER TABLE guiziweb_acp_product_feed_config ALTER COLUMN id SET DEFAULT nextval(\'guiziweb_acp_product_feed_config_id_seq\')');
        }

        // Add AUTO_INCREMENT for MySQL
        if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
            $this->addSql('ALTER TABLE guiziweb_acp_product_feed_config MODIFY id INT AUTO_INCREMENT');
            $this->addSql('ALTER TABLE guiziweb_acp_product_feed_config DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        }

        $this->addSql('ALTER TABLE guiziweb_acp_product_feed_config ADD CONSTRAINT FK_EAF9F9D372F5A1AA FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guiziweb_acp_product_feed_config DROP CONSTRAINT FK_EAF9F9D372F5A1AA');
        $this->addSql('DROP TABLE guiziweb_acp_product_feed_config');

        // Drop sequence for PostgreSQL
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE guiziweb_acp_product_feed_config_id_seq');
        }
    }
}
