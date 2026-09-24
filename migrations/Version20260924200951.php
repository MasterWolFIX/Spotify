<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924200951 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE playlist ADD image_path VARCHAR(255) DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE playlist_song RENAME INDEX idx_playlist TO IDX_93F4D9C36BBD148');
        $this->addSql('ALTER TABLE playlist_song RENAME INDEX idx_song TO IDX_93F4D9C3A0BDB2F3');
        $this->addSql('ALTER TABLE song DROP INDEX UNIQ_33EDEEA1B6A25B51, ADD UNIQUE INDEX UNIQ_33EDEEA182A8E361 (file_path)');
        $this->addSql('ALTER TABLE song CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE playlist DROP image_path, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE playlist_song RENAME INDEX idx_93f4d9c36bbd148 TO IDX_playlist');
        $this->addSql('ALTER TABLE playlist_song RENAME INDEX idx_93f4d9c3a0bdb2f3 TO IDX_song');
        $this->addSql('ALTER TABLE song DROP INDEX UNIQ_33EDEEA182A8E361, ADD UNIQUE INDEX UNIQ_33EDEEA1B6A25B51 (file_path(768))');
        $this->addSql('ALTER TABLE song CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
