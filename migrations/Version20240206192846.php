<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240206192846 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE "order" (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, sum INT NOT NULL, is_paid BOOLEAN NOT NULL, is_cancelled BOOLEAN NOT NULL, PRIMARY KEY(id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE "order"');
    }
}
