<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250919080955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User.code_compta nullable + unique (repas) + triggers description -> repas';
    }

    // Important : certains MySQL n’aiment pas CREATE TRIGGER dans une transaction
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // MySQL/MariaDB uniquement
        $platform = $this->connection->getDatabasePlatform()->getName();
        if (!in_array($platform, ['mysql'])) {
            $this->abortIf(true, 'Migration prévue pour MySQL/MariaDB uniquement.');
        }

        // ce que tu avais déjà
        $this->addSql('ALTER TABLE user CHANGE code_compta code_compta INT DEFAULT NULL');

        // unicité anti-doublon (repas)
        $this->addSql("ALTER TABLE repas ADD CONSTRAINT uq_repas_prof_cal UNIQUE (ref_calendrier_id, professeur_id)");

        // trigger INSERT
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

        // trigger UPDATE (complète seulement 0→1)
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
        if (!in_array($platform, ['mysql'])) {
            $this->abortIf(true, 'Migration prévue pour MySQL/MariaDB uniquement.');
        }

        // rollback de ce que tu avais
        $this->addSql('ALTER TABLE `user` CHANGE code_compta code_compta INT NOT NULL');

        // drop triggers + index unique
        $this->addSql('DROP TRIGGER IF EXISTS after_description_insert');
        $this->addSql('DROP TRIGGER IF EXISTS after_description_update');
        $this->addSql('ALTER TABLE repas DROP INDEX uq_repas_prof_cal');
    }
}
