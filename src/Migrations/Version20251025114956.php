<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Migrations;

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
        $this->addSql('CREATE TABLE guiziweb_acp_checkout_session (id INT AUTO_INCREMENT NOT NULL, order_id INT NOT NULL, channel_id INT NOT NULL, acp_id VARCHAR(255) NOT NULL, status VARCHAR(50) NOT NULL, idempotency_key VARCHAR(255) DEFAULT NULL, last_request_hash VARCHAR(64) DEFAULT NULL, UNIQUE INDEX UNIQ_1D5F92705EC7CC7F (acp_id), UNIQUE INDEX UNIQ_1D5F92707FD1C147 (idempotency_key), UNIQUE INDEX UNIQ_1D5F92708D9F6D38 (order_id), INDEX IDX_1D5F927072F5A1AA (channel_id), INDEX idx_acp_id (acp_id), INDEX idx_idempotency_key (idempotency_key), INDEX idx_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE guiziweb_acp_checkout_session ADD CONSTRAINT FK_1D5F92708D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guiziweb_acp_checkout_session ADD CONSTRAINT FK_1D5F927072F5A1AA FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guiziweb_acp_checkout_session DROP FOREIGN KEY FK_1D5F92708D9F6D38');
        $this->addSql('ALTER TABLE guiziweb_acp_checkout_session DROP FOREIGN KEY FK_1D5F927072F5A1AA');
        $this->addSql('DROP TABLE guiziweb_acp_checkout_session');
    }
}
