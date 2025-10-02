<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251001CreateTriggerOnDescription extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Trigger description→repas : insère UNIQUEMENT sur jours ouvrés (type_jour 'Semaine'/'COURS').";
    }

    // DDL/CREATE TRIGGER peut ne pas être transactionnel
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !in_array($this->connection->getDatabasePlatform()->getName(), ['mysql','mariadb'], true),
            'Migration prévue pour MySQL/MariaDB.'
        );

        // (Re)création du trigger en single-statement (pas de BEGIN/END, pas de DELIMITER)
        $this->addSql("DROP TRIGGER IF EXISTS trg_repas_on_description_insert");

        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_repas_on_description_insert
AFTER INSERT ON description
FOR EACH ROW
INSERT INTO repas (ref_calendrier_id, professeur_id, est_consomme)
SELECT
    c.id,
    NEW.ref_professeur_id,
    0
FROM calendrier c
WHERE c.ref_promo_id = NEW.ref_promo_id
  -- jours ouvrés réels : normalisation pour éviter les soucis de casse/espaces
  AND TRIM(UPPER(c.type_jour)) IN ('SEMAINE','COURS')
  -- MySQL DAYOFWEEK: 1=Dim, 2=Lun, ... 6=Ven
  AND (
        (DAYOFWEEK(c.`date`)=2 AND NEW.lundi=1) OR
        (DAYOFWEEK(c.`date`)=3 AND NEW.mardi=1) OR
        (DAYOFWEEK(c.`date`)=4 AND NEW.mercredi=1) OR
        (DAYOFWEEK(c.`date`)=5 AND NEW.jeudi=1) OR
        (DAYOFWEEK(c.`date`)=6 AND NEW.vendredi=1)
      )
  -- anti-doublon
  AND NOT EXISTS (
        SELECT 1
        FROM repas r
        WHERE r.ref_calendrier_id = c.id
          AND r.professeur_id     = NEW.ref_professeur_id
  )
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !in_array($this->connection->getDatabasePlatform()->getName(), ['mysql','mariadb'], true),
            'Migration prévue pour MySQL/MariaDB.'
        );

        $this->addSql("DROP TRIGGER IF EXISTS trg_repas_on_description_insert");
    }
}
