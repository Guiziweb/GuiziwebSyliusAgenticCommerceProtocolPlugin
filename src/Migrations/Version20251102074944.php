<?php

declare(strict_types=1);

namespace Guiziweb\SyliusAgenticCommerceProtocolPlugin\Migrations;

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
        $this->addSql('CREATE TABLE guiziweb_acp_product_feed_config (id INT AUTO_INCREMENT NOT NULL, channel_id INT NOT NULL, feed_endpoint VARCHAR(255) DEFAULT NULL, feed_bearer_token VARCHAR(255) DEFAULT NULL, default_brand VARCHAR(255) DEFAULT NULL, default_weight VARCHAR(50) DEFAULT NULL, default_material VARCHAR(255) DEFAULT NULL, return_policy_url VARCHAR(255) DEFAULT NULL, return_window_days INT DEFAULT NULL, privacy_policy_url VARCHAR(255) DEFAULT NULL, terms_of_service_url VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_EAF9F9D372F5A1AA (channel_id), INDEX idx_channel (channel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE guiziweb_acp_product_feed_config ADD CONSTRAINT FK_EAF9F9D372F5A1AA FOREIGN KEY (channel_id) REFERENCES sylius_channel (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE guiziweb_acp_product_feed_config DROP FOREIGN KEY FK_EAF9F9D372F5A1AA');
        $this->addSql('DROP TABLE guiziweb_acp_product_feed_config');
    }
}
