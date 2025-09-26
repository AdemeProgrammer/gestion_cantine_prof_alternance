<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250926_OnlyOpenDays extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Triggers: créer repas uniquement si calendrier.type_jour = Semaine';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform !== 'mysql') {
            $this->abortIf(true, 'MySQL/MariaDB uniquement.');
        }

        // On supprime avant de recréer (idempotent)
        $this->addSql('DROP TRIGGER IF EXISTS after_description_insert');
        $this->addSql('DROP TRIGGER IF EXISTS after_description_update');

        // AFTER INSERT — autorise UNIQUEMENT "Semaine"
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
  AND (c.type_jour IS NULL OR UPPER(c.type_jour) = 'SEMAINE')
ON DUPLICATE KEY UPDATE
  est_consomme = VALUES(est_consomme)
SQL);

        // AFTER UPDATE — idem
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
  AND (c.type_jour IS NULL OR UPPER(c.type_jour) = 'SEMAINE')
ON DUPLICATE KEY UPDATE
  est_consomme = VALUES(est_consomme)
SQL);
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform !== 'mysql') {
            $this->abortIf(true, 'MySQL/MariaDB uniquement.');
        }

        $this->addSql('DROP TRIGGER IF EXISTS after_description_insert');
        $this->addSql('DROP TRIGGER IF EXISTS after_description_update');
    }
}
