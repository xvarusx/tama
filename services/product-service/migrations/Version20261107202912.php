<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261107202912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product RENAME INDEX idx_d34a04ad44f5d353 TO IDX_D34A04AD44F5D008');
        $this->addSql('ALTER TABLE product_media RENAME INDEX idx_2a83fb354584665a TO IDX_CB70DA504584665A');
        $this->addSql('ALTER TABLE product_variant RENAME INDEX uniq_209aa412f9038c4 TO UNIQ_209AA41DF9038C4');
        $this->addSql('ALTER TABLE product_variant RENAME INDEX idx_209aa4124584665a TO IDX_209AA41D4584665A');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product RENAME INDEX idx_d34a04ad44f5d008 TO IDX_D34A04AD44F5D353');
        $this->addSql('ALTER TABLE product_media RENAME INDEX idx_cb70da504584665a TO IDX_2A83FB354584665A');
        $this->addSql('ALTER TABLE product_variant RENAME INDEX idx_209aa41d4584665a TO IDX_209AA4124584665A');
        $this->addSql('ALTER TABLE product_variant RENAME INDEX uniq_209aa41df9038c4 TO UNIQ_209AA412F9038C4');
    }
}
