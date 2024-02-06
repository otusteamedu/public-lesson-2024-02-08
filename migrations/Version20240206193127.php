<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240206193127 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (100, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (200, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (300, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (400, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (500, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (600, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (700, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (800, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (900, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (1000, false, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (1100, true, false)');
        $this->addSql('INSERT INTO "order" (sum, is_paid, is_cancelled) VALUES (1200, false, true)');
    }
}
