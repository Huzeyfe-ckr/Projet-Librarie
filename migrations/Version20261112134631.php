<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112134631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_announcement_requester ON exchange (announcement_id, requester_id)');
        $this->addSql('ALTER TABLE message CHANGE exchange_id exchange_id INT NOT NULL, CHANGE sender_id sender_id INT NOT NULL');
        $this->addSql('ALTER TABLE wishlist CHANGE book_id book_id INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_wishlist_user_book ON wishlist (user_id, book_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_exchange_announcement_requester ON exchange');
        $this->addSql('ALTER TABLE message CHANGE exchange_id exchange_id INT DEFAULT NULL, CHANGE sender_id sender_id INT DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_wishlist_user_book ON wishlist');
        $this->addSql('ALTER TABLE wishlist CHANGE book_id book_id INT DEFAULT NULL');
    }
}
