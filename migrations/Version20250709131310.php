<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250709131310 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot ADD competition_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot ADD initiated_submissions INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot ADD processed_submissions INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot ADD captured_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN competition_stats_snapshot.captured_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot ADD CONSTRAINT FK_EF2FF4177B39D312 FOREIGN KEY (competition_id) REFERENCES competition (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_EF2FF4177B39D312 ON competition_stats_snapshot (competition_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_snapshot_competition_timestamp ON competition_stats_snapshot (competition_id, captured_at)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot DROP CONSTRAINT FK_EF2FF4177B39D312
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_EF2FF4177B39D312
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_snapshot_competition_timestamp
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot DROP competition_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot DROP initiated_submissions
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot DROP processed_submissions
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot DROP captured_at
        SQL);
    }
}
