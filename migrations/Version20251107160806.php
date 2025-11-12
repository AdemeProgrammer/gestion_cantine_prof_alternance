<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251107160806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Triggers: (1) AFTER INSERT ON description => seed repas + seed facturation, (2) AFTER INSERT ON repas => MAJ facturation, (3) AFTER DELETE ON repas => MAJ facturation. Collations harmonisées.';
    }

    public function up(Schema $schema): void
    {
        /* --------- 1) (RE)CRÉE le trigger sur description (seed repas + seed facturation) --------- */
        $this->addSql("DROP TRIGGER IF EXISTS trg_repas_on_description_insert");
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
      AND TRIM(UPPER(c.type_jour) COLLATE utf8mb4_unicode_ci) IN ('SEMAINE','COURS')
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

    /* Étape B : facturations à partir des REPAS réellement présents (collations unifiées) */
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
        'En attente'                                            AS statut
    FROM
        (
            /* Mois de l’année scolaire présents dans calendrier (hors août) */
            SELECT DISTINCT
                   CAST(DATE_FORMAT(c.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci AS mois
            FROM calendrier c
            WHERE c.ref_promo_id = NEW.ref_promo_id
              AND TRIM(UPPER(c.type_jour) COLLATE utf8mb4_unicode_ci) IN ('SEMAINE','COURS')
              AND MONTH(c.`date`) <> 8
        ) AS M
        LEFT JOIN
        (
            /* Nb de repas du prof par mois (YYYY-MM) sur cette promo */
            SELECT CAST(DATE_FORMAT(c.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci AS mois,
                   COUNT(*) AS nb_repas
            FROM repas r
            INNER JOIN calendrier c ON c.id = r.ref_calendrier_id
            WHERE r.professeur_id = NEW.ref_professeur_id
              AND c.ref_promo_id   = NEW.ref_promo_id
            GROUP BY CAST(DATE_FORMAT(c.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci
        ) AS A
            ON A.mois = M.mois
    WHERE NOT EXISTS (
        SELECT 1
        FROM facturation f
        WHERE f.ref_professeur_id = NEW.ref_professeur_id
          AND f.mois COLLATE utf8mb4_unicode_ci = M.mois
    );
END
SQL);

        /* --------- 2) Trigger AFTER INSERT ON repas => recalcul immédiat (collations fixées) --------- */
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_insert_repas");
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_fact_after_insert_repas
AFTER INSERT ON repas
FOR EACH ROW
BEGIN
    DECLARE v_date DATE;
    DECLARE v_promo_id INT;
    DECLARE v_mois_key VARCHAR(7);
    DECLARE v_prof_id INT;
    DECLARE v_prix_u DECIMAL(10,2);
    DECLARE v_report DECIMAL(10,2);
    DECLARE v_nb INT;

    /* Récupère la date et la promo du calendrier associé */
    SELECT c.`date`, c.ref_promo_id
      INTO v_date, v_promo_id
    FROM calendrier c
    WHERE c.id = NEW.ref_calendrier_id
    LIMIT 1;

    IF v_date IS NOT NULL AND MONTH(v_date) <> 8 THEN
        SET v_prof_id  = NEW.professeur_id;
        SET v_mois_key = DATE_FORMAT(v_date, '%Y-%m');

        /* Prix et report de la description (prof, promo) */
        SELECT COALESCE(d.prix_u, 0), COALESCE(d.report, 0)
          INTO v_prix_u, v_report
        FROM description d
        WHERE d.ref_professeur_id = v_prof_id
          AND d.ref_promo_id      = v_promo_id
        LIMIT 1;

        /* S'assurer que la facture existe pour (prof, mois) */
        INSERT INTO facturation (
            ref_professeur_id, mois, nb_repas,
            montant_total, montant_regle, montant_restant,
            report_m_1, statut
        )
        SELECT
            v_prof_id,
            v_mois_key,
            0,
            0.00,
            0.00,
            ROUND(CASE WHEN MONTH(v_date) = 9 THEN v_report ELSE 0 END, 2),
            CASE WHEN MONTH(v_date) = 9 THEN v_report ELSE 0 END,
            'En attente'
        WHERE NOT EXISTS (
            SELECT 1
            FROM facturation f
            WHERE f.ref_professeur_id = v_prof_id
              AND f.mois COLLATE utf8mb4_unicode_ci = (v_mois_key) COLLATE utf8mb4_unicode_ci
        );

        /* nb_repas réel du mois (compte les lignes repas existantes) */
        SELECT COUNT(*)
          INTO v_nb
        FROM repas r
        INNER JOIN calendrier c2 ON c2.id = r.ref_calendrier_id
        WHERE r.professeur_id = v_prof_id
          AND c2.ref_promo_id = v_promo_id
          AND (CAST(DATE_FORMAT(c2.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci)
              = (v_mois_key) COLLATE utf8mb4_unicode_ci;

        /* Mise à jour de la facture (montant_total et restant) */
        UPDATE facturation f
        SET f.nb_repas        = v_nb,
            f.montant_total   = ROUND(v_nb * v_prix_u, 2),
            f.montant_restant = ROUND((v_nb * v_prix_u) + f.report_m_1 - f.montant_regle, 2)
        WHERE f.ref_professeur_id = v_prof_id
          AND f.mois COLLATE utf8mb4_unicode_ci = (v_mois_key) COLLATE utf8mb4_unicode_ci;
    END IF;
END
SQL);

        /* --------- 3) Trigger AFTER DELETE ON repas => recalcul immédiat (collations fixées) --------- */
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_delete_repas");
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_fact_after_delete_repas
AFTER DELETE ON repas
FOR EACH ROW
BEGIN
    DECLARE v_date DATE;
    DECLARE v_promo_id INT;
    DECLARE v_mois_key VARCHAR(7);
    DECLARE v_prof_id INT;
    DECLARE v_prix_u DECIMAL(10,2);
    DECLARE v_nb INT;

    /* Récupère la date et la promo du calendrier associé */
    SELECT c.`date`, c.ref_promo_id
      INTO v_date, v_promo_id
    FROM calendrier c
    WHERE c.id = OLD.ref_calendrier_id
    LIMIT 1;

    IF v_date IS NOT NULL AND MONTH(v_date) <> 8 THEN
        SET v_prof_id  = OLD.professeur_id;
        SET v_mois_key = DATE_FORMAT(v_date, '%Y-%m');

        /* Prix (depuis description) pour recalcul du total */
        SELECT COALESCE(d.prix_u, 0)
          INTO v_prix_u
        FROM description d
        WHERE d.ref_professeur_id = v_prof_id
          AND d.ref_promo_id      = v_promo_id
        LIMIT 1;

        /* nb_repas réel restant du mois (après suppression) */
        SELECT COUNT(*)
          INTO v_nb
        FROM repas r
        INNER JOIN calendrier c2 ON c2.id = r.ref_calendrier_id
        WHERE r.professeur_id = v_prof_id
          AND c2.ref_promo_id = v_promo_id
          AND (CAST(DATE_FORMAT(c2.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci)
              = (v_mois_key) COLLATE utf8mb4_unicode_ci;

        /* Mise à jour de la facture si elle existe */
        UPDATE facturation f
        SET f.nb_repas        = v_nb,
            f.montant_total   = ROUND(v_nb * v_prix_u, 2),
            f.montant_restant = ROUND((v_nb * v_prix_u) + f.report_m_1 - f.montant_regle, 2)
        WHERE f.ref_professeur_id = v_prof_id
          AND f.mois COLLATE utf8mb4_unicode_ci = (v_mois_key) COLLATE utf8mb4_unicode_ci;
    END IF;
END
SQL);
    }

    public function down(Schema $schema): void
    {
        /* Supprime les 2 triggers de mise à jour live */
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_insert_repas");
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_delete_repas");

        /* Supprime et restaure le trigger d'origine (repas only) si rollback */
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
  AND TRIM(UPPER(c.type_jour) COLLATE utf8mb4_unicode_ci) IN ('SEMAINE','COURS')
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
