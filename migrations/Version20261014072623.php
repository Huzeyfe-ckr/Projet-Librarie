<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261014072623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE announcement (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(255) NOT NULL, price NUMERIC(10, 2) DEFAULT NULL, status VARCHAR(255) NOT NULL, inactivated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_4DB9D91CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, author VARCHAR(255) DEFAULT NULL, isbn VARCHAR(255) DEFAULT NULL, book_condition VARCHAR(255) NOT NULL, genre VARCHAR(255) DEFAULT NULL, inactivated_at DATETIME DEFAULT NULL, announcement_id INT NOT NULL, INDEX IDX_CBE5A331913AEA17 (announcement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_page (id INT AUTO_INCREMENT NOT NULL, img_url LONGTEXT NOT NULL, page_order INT NOT NULL, uploaded_at DATETIME NOT NULL, inactivated_at DATETIME DEFAULT NULL, book_id INT NOT NULL, INDEX IDX_D97FD2816A2B381 (book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, inactivated_at DATETIME DEFAULT NULL, announcement_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9474526C913AEA17 (announcement_id), INDEX IDX_9474526CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exchange (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(255) NOT NULL, initiated_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, announcement_id INT NOT NULL, requester_id INT NOT NULL, INDEX IDX_D33BB079913AEA17 (announcement_id), INDEX IDX_D33BB079ED442CF4 (requester_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, sent_at DATETIME NOT NULL, inactivated_at DATETIME DEFAULT NULL, exchange_id INT DEFAULT NULL, sender_id INT DEFAULT NULL, INDEX IDX_B6BD307F68AFD1A0 (exchange_id), INDEX IDX_B6BD307FF624B39D (sender_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, username VARCHAR(255) NOT NULL, avatar_url LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, cgu_validated_at DATETIME DEFAULT NULL, is_verified TINYINT NOT NULL, inactivated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wishlist (id INT AUTO_INCREMENT NOT NULL, added_at DATETIME NOT NULL, inactivated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, book_id INT DEFAULT NULL, INDEX IDX_9CE12A31A76ED395 (user_id), INDEX IDX_9CE12A3116A2B381 (book_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE announcement ADD CONSTRAINT FK_4DB9D91CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A331913AEA17 FOREIGN KEY (announcement_id) REFERENCES announcement (id)');
        $this->addSql('ALTER TABLE book_page ADD CONSTRAINT FK_D97FD2816A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C913AEA17 FOREIGN KEY (announcement_id) REFERENCES announcement (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE exchange ADD CONSTRAINT FK_D33BB079913AEA17 FOREIGN KEY (announcement_id) REFERENCES announcement (id)');
        $this->addSql('ALTER TABLE exchange ADD CONSTRAINT FK_D33BB079ED442CF4 FOREIGN KEY (requester_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F68AFD1A0 FOREIGN KEY (exchange_id) REFERENCES exchange (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FF624B39D FOREIGN KEY (sender_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A31A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT FK_9CE12A3116A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE announcement DROP FOREIGN KEY FK_4DB9D91CA76ED395');
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A331913AEA17');
        $this->addSql('ALTER TABLE book_page DROP FOREIGN KEY FK_D97FD2816A2B381');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C913AEA17');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE exchange DROP FOREIGN KEY FK_D33BB079913AEA17');
        $this->addSql('ALTER TABLE exchange DROP FOREIGN KEY FK_D33BB079ED442CF4');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F68AFD1A0');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FF624B39D');
        $this->addSql('ALTER TABLE wishlist DROP FOREIGN KEY FK_9CE12A31A76ED395');
        $this->addSql('ALTER TABLE wishlist DROP FOREIGN KEY FK_9CE12A3116A2B381');
        $this->addSql('DROP TABLE announcement');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE book_page');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE exchange');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE wishlist');
    }
}
