<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628110519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE submission (id SERIAL NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE winner (id SERIAL NOT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition ALTER status DROP DEFAULT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition ALTER number_of_winners DROP DEFAULT
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE SCHEMA public
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE submission
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE winner
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition ALTER status SET DEFAULT 'draft'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE competition ALTER number_of_winners SET DEFAULT 1
        SQL);
    }
}
