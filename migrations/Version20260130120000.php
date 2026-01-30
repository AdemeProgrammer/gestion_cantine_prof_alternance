<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trigger BEFORE UPDATE pour passer le statut à Payé quand montant_total = montant_regle';
    }

    public function up(Schema $schema): void
    {
        // Création du trigger BEFORE UPDATE
        $this->addSql("DROP TRIGGER IF EXISTS trg_facturation_before_update");

        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_facturation_before_update
BEFORE UPDATE ON facturation
FOR EACH ROW
BEGIN
    IF ROUND(NEW.montant_total + NEW.report_m_1 - NEW.montant_regle, 2) <= 0 THEN
        SET NEW.statut = 'Payé';
    END IF;
END
SQL);

        // Corriger les données existantes
        $this->addSql("
            UPDATE facturation f
            SET f.statut = 'Payé'
            WHERE f.statut = 'En attente'
              AND ROUND(f.montant_total + f.report_m_1 - f.montant_regle, 2) <= 0
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TRIGGER IF EXISTS trg_facturation_before_update");
    }
}
