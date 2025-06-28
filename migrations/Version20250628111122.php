<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628111122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE submission ADD competition_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission ADD email VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission ADD submission_data JSON NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN submission.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission ADD CONSTRAINT FK_DB055AF37B39D312 FOREIGN KEY (competition_id) REFERENCES competition (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_DB055AF37B39D312 ON submission (competition_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner ADD competition_id INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner ADD submission_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner ADD email VARCHAR(255) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner ADD rank INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN winner.created_at IS '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner ADD CONSTRAINT FK_CF6600E7B39D312 FOREIGN KEY (competition_id) REFERENCES competition (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner ADD CONSTRAINT FK_CF6600EE1FD4933 FOREIGN KEY (submission_id) REFERENCES submission (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_CF6600E7B39D312 ON winner (competition_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_CF6600EE1FD4933 ON winner (submission_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission DROP CONSTRAINT FK_DB055AF37B39D312
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_DB055AF37B39D312
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission DROP competition_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission DROP submission_data
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE submission DROP created_at
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner DROP CONSTRAINT FK_CF6600E7B39D312
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner DROP CONSTRAINT FK_CF6600EE1FD4933
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_CF6600E7B39D312
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_CF6600EE1FD4933
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner DROP competition_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner DROP submission_id
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner DROP email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner DROP rank
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE winner DROP created_at
        SQL);
    }
}
