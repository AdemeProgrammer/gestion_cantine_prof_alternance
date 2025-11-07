<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251107160806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée le trigger AFTER INSERT ON description: seed des repas puis création des facturations à partir des repas.';
    }

    public function up(Schema $schema): void
    {
        // Supprimer l’éventuel trigger existant
        $this->addSql("DROP TRIGGER IF EXISTS trg_repas_on_description_insert");

        // Créer le trigger combiné
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_repas_on_description_insert
AFTER INSERT ON description
FOR EACH ROW
BEGIN
    /* Étape A : seed des repas (d’après ton trigger d’origine) */
    INSERT INTO repas (ref_calendrier_id, professeur_id, est_consomme)
    SELECT
        c.id,
        NEW.ref_professeur_id,
        0
    FROM calendrier c
    WHERE c.ref_promo_id = NEW.ref_promo_id
      AND TRIM(UPPER(c.type_jour)) IN ('SEMAINE','COURS')
      AND (
            (DAYOFWEEK(c.`date`)=2 AND NEW.lundi=1) OR
            (DAYOFWEEK(c.`date`)=3 AND NEW.mardi=1) OR
            (DAYOFWEEK(c.`date`)=4 AND NEW.mercredi=1) OR
            (DAYOFWEEK(c.`date`)=5 AND NEW.jeudi=1) OR
            (DAYOFWEEK(c.`date`)=6 AND NEW.vendredi=1)
          )
      AND NOT EXISTS (
            SELECT 1
            FROM repas r
            WHERE r.ref_calendrier_id = c.id
              AND r.professeur_id     = NEW.ref_professeur_id
      );

    /* Étape B : facturations à partir des repas réellement présents */
    INSERT INTO facturation (
        ref_professeur_id,
        mois,
        nb_repas,
        montant_total,
        montant_regle,
        montant_restant,
        report_m_1,
        statut
    )
    SELECT
        NEW.ref_professeur_id                                  AS ref_professeur_id,
        M.mois                                                  AS mois,
        IFNULL(A.nb_repas, 0)                                   AS nb_repas,
        ROUND(IFNULL(A.nb_repas, 0) * NEW.prix_u, 2)            AS montant_total,
        0.00                                                    AS montant_regle,
        ROUND((IFNULL(A.nb_repas, 0) * NEW.prix_u)
              + (CASE WHEN SUBSTRING(M.mois, 6, 2) = '09'
                      THEN NEW.report ELSE 0 END), 2)           AS montant_restant,
        (CASE WHEN SUBSTRING(M.mois, 6, 2) = '09'
              THEN NEW.report ELSE 0 END)                       AS report_m_1,
        'EN_ATTENTE'                                            AS statut
    FROM
        (
            /* Mois de l’année scolaire présents dans calendrier (hors août) */
            SELECT DISTINCT DATE_FORMAT(c.`date`, '%Y-%m') AS mois
            FROM calendrier c
            WHERE c.ref_promo_id = NEW.ref_promo_id
              AND TRIM(UPPER(c.type_jour)) IN ('SEMAINE','COURS')
              AND MONTH(c.`date`) <> 8
        ) AS M
        LEFT JOIN
        (
            /* Nb de repas du prof par mois (YYYY-MM) sur cette promo */
            SELECT DATE_FORMAT(c.`date`, '%Y-%m') AS mois, COUNT(*) AS nb_repas
            FROM repas r
            INNER JOIN calendrier c ON c.id = r.ref_calendrier_id
            WHERE r.professeur_id = NEW.ref_professeur_id
              AND c.ref_promo_id   = NEW.ref_promo_id
            GROUP BY DATE_FORMAT(c.`date`, '%Y-%m')
        ) AS A
            ON A.mois = M.mois
    WHERE NOT EXISTS (
        SELECT 1
        FROM facturation f
        WHERE f.ref_professeur_id = NEW.ref_professeur_id
          AND f.mois              = M.mois
    );
END
SQL);
    }

    public function down(Schema $schema): void
    {
        // Supprimer le trigger combiné
        $this->addSql("DROP TRIGGER IF EXISTS trg_repas_on_description_insert");

        // Restaurer le trigger d’origine (seed des repas uniquement)
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
  AND TRIM(UPPER(c.type_jour)) IN ('SEMAINE','COURS')
  AND (
        (DAYOFWEEK(c.`date`)=2 AND NEW.lundi=1) OR
        (DAYOFWEEK(c.`date`)=3 AND NEW.mardi=1) OR
        (DAYOFWEEK(c.`date`)=4 AND NEW.mercredi=1) OR
        (DAYOFWEEK(c.`date`)=5 AND NEW.jeudi=1) OR
        (DAYOFWEEK(c.`date`)=6 AND NEW.vendredi=1)
      )
  AND NOT EXISTS (
        SELECT 1
        FROM repas r
        WHERE r.ref_calendrier_id = c.id
          AND r.professeur_id     = NEW.ref_professeur_id
  );
SQL);
    }
}
