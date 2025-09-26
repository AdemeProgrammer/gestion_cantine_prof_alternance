<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250926100151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Triggers: description -> repas (AFTER INSERT / AFTER UPDATE)';
    }

    // Important: certains MySQL n’aiment pas CREATE TRIGGER dans une transaction
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // MySQL/MariaDB uniquement
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform !== 'mysql') {
            $this->abortIf(true, 'Migration prévue pour MySQL/MariaDB uniquement.');
        }

        // Idempotent: supprimer d’abord si déjà présents
        $this->addSql('DROP TRIGGER IF EXISTS after_description_insert');
        $this->addSql('DROP TRIGGER IF EXISTS after_description_update');

        // Trigger AFTER INSERT sur description -> alimente repas (set-based)
        // Par défaut on met est_consomme = 1 (modifie en 0 si tu préfères)
        $this->addSql(<<<'SQL'
CREATE TRIGGER after_description_insert
AFTER INSERT ON description
FOR EACH ROW
INSERT INTO repas (ref_calendrier_id, professeur_id, est_consomme)
SELECT c.id, NEW.ref_professeur_id, 1
FROM calendrier c
WHERE c.ref_promo_id = NEW.ref_promo_id
  AND (
       (DAYOFWEEK(c.date) = 2 AND NEW.lundi    = 1) OR
       (DAYOFWEEK(c.date) = 3 AND NEW.mardi    = 1) OR
       (DAYOFWEEK(c.date) = 4 AND NEW.mercredi = 1) OR
       (DAYOFWEEK(c.date) = 5 AND NEW.jeudi    = 1) OR
       (DAYOFWEEK(c.date) = 6 AND NEW.vendredi = 1)
  )
ON DUPLICATE KEY UPDATE
  est_consomme = VALUES(est_consomme)
SQL);

        // Trigger AFTER UPDATE sur description -> complète uniquement les jours passés de 0 à 1
        $this->addSql(<<<'SQL'
CREATE TRIGGER after_description_update
AFTER UPDATE ON description
FOR EACH ROW
INSERT INTO repas (ref_calendrier_id, professeur_id, est_consomme)
SELECT c.id, NEW.ref_professeur_id, 1
FROM calendrier c
WHERE c.ref_promo_id = NEW.ref_promo_id
  AND (
       (DAYOFWEEK(c.date) = 2 AND NEW.lundi    = 1 AND OLD.lundi    = 0) OR
       (DAYOFWEEK(c.date) = 3 AND NEW.mardi    = 1 AND OLD.mardi    = 0) OR
       (DAYOFWEEK(c.date) = 4 AND NEW.mercredi = 1 AND OLD.mercredi = 0) OR
       (DAYOFWEEK(c.date) = 5 AND NEW.jeudi    = 1 AND OLD.jeudi    = 0) OR
       (DAYOFWEEK(c.date) = 6 AND NEW.vendredi = 1 AND OLD.vendredi = 0)
  )
ON DUPLICATE KEY UPDATE
  est_consomme = VALUES(est_consomme)
SQL);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform !== 'mysql') {
            $this->abortIf(true, 'Migration prévue pour MySQL/MariaDB uniquement.');
        }

        $this->addSql('DROP TRIGGER IF EXISTS after_description_insert');
        $this->addSql('DROP TRIGGER IF EXISTS after_description_update');
    }
}
