<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019185629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE api_error_logs (id SERIAL NOT NULL, report_run_id INT DEFAULT NULL, report_query_id INT DEFAULT NULL, endpoint VARCHAR(64) NOT NULL, request_params JSON NOT NULL, response_status INT DEFAULT NULL, error_body JSON DEFAULT NULL, error_class VARCHAR(128) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_73527771AD102B5B ON api_error_logs (report_run_id)');
        $this->addSql('CREATE INDEX IDX_73527771EFF4F6E4 ON api_error_logs (report_query_id)');
        $this->addSql('CREATE INDEX api_error_logs_created_at_idx ON api_error_logs (created_at)');
        $this->addSql('COMMENT ON COLUMN api_error_logs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE report_queries (id SERIAL NOT NULL, report_run_id INT NOT NULL, sequence INT NOT NULL, request_params JSON NOT NULL, status VARCHAR(32) NOT NULL, attempts SMALLINT NOT NULL, max_attempts SMALLINT NOT NULL, last_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, response_payload JSON DEFAULT NULL, aggregated_count INT DEFAULT NULL, error_payload JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EA613A72AD102B5B ON report_queries (report_run_id)');
        $this->addSql('COMMENT ON COLUMN report_queries.last_attempt_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_queries.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_queries.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE report_results (id SERIAL NOT NULL, report_run_id INT NOT NULL, status VARCHAR(32) NOT NULL, result_payload JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FF3A9914AD102B5B ON report_results (report_run_id)');
        $this->addSql('COMMENT ON COLUMN report_results.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_results.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE report_runs (id SERIAL NOT NULL, report_id INT NOT NULL, input_payload JSON NOT NULL, status VARCHAR(32) NOT NULL, status_message TEXT DEFAULT NULL, queued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, error_payload JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F31696E44BD2A4C0 ON report_runs (report_id)');
        $this->addSql('COMMENT ON COLUMN report_runs.queued_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_runs.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_runs.finished_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_runs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN report_runs.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE reports (id SERIAL NOT NULL, code VARCHAR(128) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, config_schema JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX reports_code_unique ON reports (code)');
        $this->addSql('COMMENT ON COLUMN reports.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reports.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE api_error_logs ADD CONSTRAINT FK_73527771AD102B5B FOREIGN KEY (report_run_id) REFERENCES report_runs (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_error_logs ADD CONSTRAINT FK_73527771EFF4F6E4 FOREIGN KEY (report_query_id) REFERENCES report_queries (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE report_queries ADD CONSTRAINT FK_EA613A72AD102B5B FOREIGN KEY (report_run_id) REFERENCES report_runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE report_results ADD CONSTRAINT FK_FF3A9914AD102B5B FOREIGN KEY (report_run_id) REFERENCES report_runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE report_runs ADD CONSTRAINT FK_F31696E44BD2A4C0 FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE api_error_logs DROP CONSTRAINT FK_73527771AD102B5B');
        $this->addSql('ALTER TABLE api_error_logs DROP CONSTRAINT FK_73527771EFF4F6E4');
        $this->addSql('ALTER TABLE report_queries DROP CONSTRAINT FK_EA613A72AD102B5B');
        $this->addSql('ALTER TABLE report_results DROP CONSTRAINT FK_FF3A9914AD102B5B');
        $this->addSql('ALTER TABLE report_runs DROP CONSTRAINT FK_F31696E44BD2A4C0');
        $this->addSql('DROP TABLE api_error_logs');
        $this->addSql('DROP TABLE report_queries');
        $this->addSql('DROP TABLE report_results');
        $this->addSql('DROP TABLE report_runs');
        $this->addSql('DROP TABLE reports');
    }
}
