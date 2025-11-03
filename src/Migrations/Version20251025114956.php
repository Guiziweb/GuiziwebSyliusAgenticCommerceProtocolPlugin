<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Migrations;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251025114956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ACP Checkout Session table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof MySQLPlatform &&
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Migration can only be executed safely on \'mysql\' or \'postgresql\'.'
        );

        $this->addSql('CREATE TABLE guiziweb_acp_checkout_session (id INT NOT NULL, order_id INT NOT NULL, channel_id INT NOT NULL, acp_id VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, idempotency_key VARCHAR(255) DEFAULT NULL, last_request_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D5F92705EC7CC7F ON guiziweb_acp_checkout_session (acp_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D5F92707FD1C147 ON guiziweb_acp_checkout_session (idempotency_key)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1D5F92708D9F6D38 ON guiziweb_acp_checkout_session (order_id)');
        $this->addSql('CREATE INDEX IDX_1D5F927072F5A1AA ON guiziweb_acp_checkout_session (channel_id)');
        $this->addSql('CREATE INDEX idx_acp_id ON guiziweb_acp_checkout_session (acp_id)');
        $this->addSql('CREATE INDEX idx_idempotency_key ON guiziweb_acp_checkout_session (idempotency_key)');
        $this->addSql('CREATE INDEX idx_status ON guiziweb_acp_checkout_session (status)');

        // Add sequence for PostgreSQL
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE SEQUENCE guiziweb_acp_checkout_session_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
            $this->addSql('ALTER TABLE guiziweb_acp_checkout_session ALTER COLUMN id SET DEFAULT nextval(\'guiziweb_acp_checkout_session_id_seq\')');
        }

        // Add AUTO_INCREMENT for MySQL
        if ($this->connection->getDatabasePlatform() instanceof MySQLPlatform) {
            $this->addSql('ALTER TABLE guiziweb_acp_checkout_session MODIFY id INT AUTO_INCREMENT');
            $this->addSql('ALTER TABLE guiziweb_acp_checkout_session DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        }

        $this->addSql('ALTER TABLE guiziweb_acp_checkout_session ADD CONSTRAINT FK_1D5F92708D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guiziweb_acp_checkout_session ADD CONSTRAINT FK_1D5F927072F5A1AA FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guiziweb_acp_checkout_session DROP CONSTRAINT FK_1D5F92708D9F6D38');
        $this->addSql('ALTER TABLE guiziweb_acp_checkout_session DROP CONSTRAINT FK_1D5F927072F5A1AA');
        $this->addSql('DROP TABLE guiziweb_acp_checkout_session');

        // Drop sequence for PostgreSQL
        if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->addSql('DROP SEQUENCE guiziweb_acp_checkout_session_id_seq');
        }
    }
}
