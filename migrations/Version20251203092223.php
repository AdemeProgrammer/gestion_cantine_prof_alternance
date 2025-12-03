<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251203092223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Triggers: (1) AFTER INSERT ON description => seed repas + seed facturation, (2) AFTER INSERT ON repas => MAJ facturation, (3) AFTER DELETE ON repas => MAJ facturation, (4) AFTER INSERT ON paiement => répartition du paiement sur les factures. Collations harmonisées.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DROP TRIGGER IF EXISTS trg_repas_on_description_insert");
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_repas_on_description_insert
AFTER INSERT ON description
FOR EACH ROW
BEGIN
    INSERT INTO repas (ref_calendrier_id, professeur_id)
    SELECT
        c.id,
        NEW.ref_professeur_id
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
            SELECT DISTINCT
                   CAST(DATE_FORMAT(c.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci AS mois
            FROM calendrier c
            WHERE c.ref_promo_id = NEW.ref_promo_id
              AND TRIM(UPPER(c.type_jour) COLLATE utf8mb4_unicode_ci) IN ('SEMAINE','COURS')
              AND MONTH(c.`date`) <> 8
        ) AS M
        LEFT JOIN
        (
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

    SELECT c.`date`, c.ref_promo_id
      INTO v_date, v_promo_id
    FROM calendrier c
    WHERE c.id = NEW.ref_calendrier_id
    LIMIT 1;

    IF v_date IS NOT NULL AND MONTH(v_date) <> 8 THEN
        SET v_prof_id  = NEW.professeur_id;
        SET v_mois_key = DATE_FORMAT(v_date, '%Y-%m');

        SELECT COALESCE(d.prix_u, 0), COALESCE(d.report, 0)
          INTO v_prix_u, v_report
        FROM description d
        WHERE d.ref_professeur_id = v_prof_id
          AND d.ref_promo_id      = v_promo_id
        LIMIT 1;

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

        SELECT COUNT(*)
          INTO v_nb
        FROM repas r
        INNER JOIN calendrier c2 ON c2.id = r.ref_calendrier_id
        WHERE r.professeur_id = v_prof_id
          AND c2.ref_promo_id = v_promo_id
          AND (CAST(DATE_FORMAT(c2.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci)
              = (v_mois_key) COLLATE utf8mb4_unicode_ci;

        UPDATE facturation f
        SET f.nb_repas        = v_nb,
            f.montant_total   = ROUND(v_nb * v_prix_u, 2),
            f.montant_restant = ROUND((v_nb * v_prix_u) + f.report_m_1 - f.montant_regle, 2)
        WHERE f.ref_professeur_id = v_prof_id
          AND f.mois COLLATE utf8mb4_unicode_ci = (v_mois_key) COLLATE utf8mb4_unicode_ci;
    END IF;
END
SQL);

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

    SELECT c.`date`, c.ref_promo_id
      INTO v_date, v_promo_id
    FROM calendrier c
    WHERE c.id = OLD.ref_calendrier_id
    LIMIT 1;

    IF v_date IS NOT NULL AND MONTH(v_date) <> 8 THEN
        SET v_prof_id  = OLD.professeur_id;
        SET v_mois_key = DATE_FORMAT(v_date, '%Y-%m');

        SELECT COALESCE(d.prix_u, 0)
          INTO v_prix_u
        FROM description d
        WHERE d.ref_professeur_id = v_prof_id
          AND d.ref_promo_id      = v_promo_id
        LIMIT 1;

        SELECT COUNT(*)
          INTO v_nb
        FROM repas r
        INNER JOIN calendrier c2 ON c2.id = r.ref_calendrier_id
        WHERE r.professeur_id = v_prof_id
          AND c2.ref_promo_id = v_promo_id
          AND (CAST(DATE_FORMAT(c2.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci)
              = (v_mois_key) COLLATE utf8mb4_unicode_ci;

        UPDATE facturation f
        SET f.nb_repas        = v_nb,
            f.montant_total   = ROUND(v_nb * v_prix_u, 2),
            f.montant_restant = ROUND((v_nb * v_prix_u) + f.report_m_1 - f.montant_regle, 2)
        WHERE f.ref_professeur_id = v_prof_id
          AND f.mois COLLATE utf8mb4_unicode_ci = (v_mois_key) COLLATE utf8mb4_unicode_ci;
    END IF;
END
SQL);

        $this->addSql("DROP TRIGGER IF EXISTS trg_paiement_after_insert");
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_paiement_after_insert
AFTER INSERT ON paiement
FOR EACH ROW
BEGIN
    DECLARE v_prof_id INT;
    DECLARE v_promo_id INT;
    DECLARE v_montant_rest DECIMAL(10,2);
    DECLARE v_fact_id INT;
    DECLARE v_fact_restant DECIMAL(10,2);

    SELECT d.ref_professeur_id, d.ref_promo_id
      INTO v_prof_id, v_promo_id
    FROM description d
    WHERE d.id = NEW.ref_description_id_id
    LIMIT 1;

    SET v_montant_rest = NEW.montant;

    IF v_prof_id IS NOT NULL AND v_promo_id IS NOT NULL AND v_montant_rest > 0 THEN

        paiement_loop: WHILE v_montant_rest > 0 DO

            SET v_fact_id = NULL;
            SET v_fact_restant = 0;

            SELECT f.id, f.montant_restant
              INTO v_fact_id, v_fact_restant
            FROM facturation f
            WHERE f.ref_professeur_id = v_prof_id
              AND f.montant_restant > 0
              AND f.statut = 'En attente'
              AND EXISTS (
                  SELECT 1
                  FROM calendrier c
                  WHERE c.ref_promo_id = v_promo_id
                    AND CAST(DATE_FORMAT(c.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci = f.mois COLLATE utf8mb4_unicode_ci
                  LIMIT 1
              )
            ORDER BY f.mois ASC
            LIMIT 1;

            IF v_fact_id IS NULL OR v_fact_restant IS NULL OR v_fact_restant <= 0 THEN
                LEAVE paiement_loop;
            END IF;

            IF v_montant_rest >= v_fact_restant THEN
                UPDATE facturation f
                SET f.montant_regle   = ROUND(f.montant_regle + v_fact_restant, 2),
                    f.montant_restant = 0.00,
                    f.statut          = 'Payé'
                WHERE f.id = v_fact_id;

                SET v_montant_rest = ROUND(v_montant_rest - v_fact_restant, 2);
            ELSE
                UPDATE facturation f
                SET f.montant_regle   = ROUND(f.montant_regle + v_montant_rest, 2),
                    f.montant_restant = ROUND(f.montant_restant - v_montant_rest, 2)
                WHERE f.id = v_fact_id;

                SET v_montant_rest = 0.00;
            END IF;

        END WHILE paiement_loop;

    END IF;
END
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TRIGGER IF EXISTS trg_paiement_after_insert");
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_insert_repas");
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_delete_repas");

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
