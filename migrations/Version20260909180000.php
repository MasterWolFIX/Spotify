<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial tables: user, song, playlist, playlist_song';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `user` (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL COMMENT \'(DC2Type:json)\',
            password VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE song (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(512) NOT NULL,
            artist VARCHAR(255) DEFAULT NULL,
            album VARCHAR(255) DEFAULT NULL,
            duration INT DEFAULT NULL,
            file_path VARCHAR(1024) NOT NULL,
            genre VARCHAR(100) DEFAULT NULL,
            year INT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_33EDEEA1B6A25B51 (file_path(768)),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE playlist (
            id INT AUTO_INCREMENT NOT NULL,
            created_by_id INT DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_D782112DB03A8386 (created_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('CREATE TABLE playlist_song (
            id INT AUTO_INCREMENT NOT NULL,
            playlist_id INT NOT NULL,
            song_id INT NOT NULL,
            position INT NOT NULL,
            UNIQUE INDEX unique_playlist_song (playlist_id, song_id),
            INDEX IDX_playlist (playlist_id),
            INDEX IDX_song (song_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $this->addSql('ALTER TABLE playlist
            ADD CONSTRAINT FK_playlist_user
            FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE playlist_song
            ADD CONSTRAINT FK_ps_playlist
            FOREIGN KEY (playlist_id) REFERENCES playlist (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE playlist_song
            ADD CONSTRAINT FK_ps_song
            FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE playlist DROP FOREIGN KEY FK_playlist_user');
        $this->addSql('ALTER TABLE playlist_song DROP FOREIGN KEY FK_ps_playlist');
        $this->addSql('ALTER TABLE playlist_song DROP FOREIGN KEY FK_ps_song');
        $this->addSql('DROP TABLE playlist_song');
        $this->addSql('DROP TABLE playlist');
        $this->addSql('DROP TABLE song');
        $this->addSql('DROP TABLE `user`');
    }
}