<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240918165007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE record (id INT AUTO_INCREMENT NOT NULL, singer_id INT NOT NULL, name VARCHAR(75) NOT NULL, price NUMERIC(5, 2) NOT NULL, INDEX IDX_9B349F91271FD47C (singer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE singer (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(75) NOT NULL, last_name VARCHAR(75) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE song (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE song_record (song_id INT NOT NULL, record_id INT NOT NULL, INDEX IDX_9D4F8E1BA0BDB2F3 (song_id), INDEX IDX_9D4F8E1B4DFD750C (record_id), PRIMARY KEY(song_id, record_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE record ADD CONSTRAINT FK_9B349F91271FD47C FOREIGN KEY (singer_id) REFERENCES singer (id)');
        $this->addSql('ALTER TABLE song_record ADD CONSTRAINT FK_9D4F8E1BA0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE song_record ADD CONSTRAINT FK_9D4F8E1B4DFD750C FOREIGN KEY (record_id) REFERENCES record (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE record DROP FOREIGN KEY FK_9B349F91271FD47C');
        $this->addSql('ALTER TABLE song_record DROP FOREIGN KEY FK_9D4F8E1BA0BDB2F3');
        $this->addSql('ALTER TABLE song_record DROP FOREIGN KEY FK_9D4F8E1B4DFD750C');
        $this->addSql('DROP TABLE record');
        $this->addSql('DROP TABLE singer');
        $this->addSql('DROP TABLE song');
        $this->addSql('DROP TABLE song_record');
        $this->addSql('DROP TABLE user');
    }
}
