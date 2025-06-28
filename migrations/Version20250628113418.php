<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628113418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_subm_competition_email ON submission (competition_id, email)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_winner_competition_email ON winner (competition_id, email)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_winner_competition_email
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_subm_competition_email
        SQL);
    }
}
