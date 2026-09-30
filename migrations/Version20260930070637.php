<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930070637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE song_play (id INT AUTO_INCREMENT NOT NULL, played_at DATETIME NOT NULL, duration INT DEFAULT 0 NOT NULL, song_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX idx_song_play_played_at (played_at), INDEX IDX_2D1A084FA0BDB2F3 (song_id), INDEX IDX_2D1A084FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE song_play ADD CONSTRAINT FK_2D1A084FA0BDB2F3 FOREIGN KEY (song_id) REFERENCES song (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE song_play ADD CONSTRAINT FK_2D1A084FA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE song ADD play_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE song_play DROP FOREIGN KEY FK_2D1A084FA0BDB2F3');
        $this->addSql('ALTER TABLE song_play DROP FOREIGN KEY FK_2D1A084FA76ED395');
        $this->addSql('DROP TABLE song_play');
        $this->addSql('ALTER TABLE song DROP play_count');
    }
}
