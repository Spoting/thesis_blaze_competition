<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250717133447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE competition_status_transition (id SERIAL NOT NULL, competition_id INT NOT NULL, old_status VARCHAR(50) NOT NULL, new_status VARCHAR(50) NOT NULL, transitioned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, triggered_by VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F6904E127B39D312 ON competition_status_transition (competition_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_status_transition_competition_timestamp ON competition_status_transition (competition_id, transitioned_at)
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN competition_status_transition.transitioned_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_status_transition ADD CONSTRAINT FK_F6904E127B39D312 FOREIGN KEY (competition_id) REFERENCES competition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot DROP CONSTRAINT FK_EF2FF4177B39D312
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot ADD CONSTRAINT FK_EF2FF4177B39D312 FOREIGN KEY (competition_id) REFERENCES competition (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_status_transition DROP CONSTRAINT FK_F6904E127B39D312
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE competition_status_transition
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot DROP CONSTRAINT fk_ef2ff4177b39d312
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition_stats_snapshot ADD CONSTRAINT fk_ef2ff4177b39d312 FOREIGN KEY (competition_id) REFERENCES competition (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }
}
