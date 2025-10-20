<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020204424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE report_vehicles (id SERIAL NOT NULL, report_run_id INT NOT NULL, vehicle_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, attempts SMALLINT NOT NULL, max_attempts SMALLINT NOT NULL, last_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, request_options JSON DEFAULT NULL, response_payload JSON DEFAULT NULL, error_payload JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1FAEF087AD102B5B ON report_vehicles (report_run_id)');
        $this->addSql('COMMENT ON COLUMN report_vehicles.last_attempt_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_vehicles.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_vehicles.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE report_vehicles ADD CONSTRAINT FK_1FAEF087AD102B5B FOREIGN KEY (report_run_id) REFERENCES report_runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE report_vehicles DROP CONSTRAINT FK_1FAEF087AD102B5B');
        $this->addSql('DROP TABLE report_vehicles');
    }
}
