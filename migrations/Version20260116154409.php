<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260116154409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correction : appel de la redistribution des paiements depuis les triggers de repas au lieu d\'un trigger sur facturation';
    }

    public function up(Schema $schema): void
    {
        // Suppression du trigger problématique
        $this->addSql("DROP TRIGGER IF EXISTS trg_facturation_after_update");

        // Recréation des triggers de repas avec redistribution des paiements
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_insert_repas");
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_delete_repas");

        // Trigger 2 modifié : AFTER INSERT ON repas => MAJ facturation + redistribution paiements
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_fact_after_insert_repas
AFTER INSERT ON repas
FOR EACH ROW
BEGIN
    DECLARE v_date DATE;
    DECLARE v_promo_id INT;
    DECLARE v_mois_key VARCHAR(7);
        DECLARE INT;
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
            montant_total, montant_regle,
            report_m_1, statut
        )
        SELECT
            v_prof_id,
            v_mois_key,
            0,
            0.00,
            0.00,
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
            f.montant_total   = ROUND(v_nb * v_prix_u, 2)
        WHERE f.ref_professeur_id = v_prof_id
          AND f.mois COLLATE utf8mb4_unicode_ci = (v_mois_key) COLLATE utf8mb4_unicode_ci;

        -- NOUVELLE PARTIE : Redistribuer les paiements après modification
        IF v_promo_id IS NOT NULL THEN
            CALL sp_redistribute_payments_for_description(v_prof_id, v_promo_id);
        END IF;
    END IF;
END
SQL);

        // Trigger 3 modifié : AFTER DELETE ON repas => MAJ facturation + redistribution paiements
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
            f.montant_total   = ROUND(v_nb * v_prix_u, 2)
        WHERE f.ref_professeur_id = v_prof_id
          AND f.mois COLLATE utf8mb4_unicode_ci = (v_mois_key) COLLATE utf8mb4_unicode_ci;

        -- NOUVELLE PARTIE : Redistribuer les paiements après modification
        IF v_promo_id IS NOT NULL THEN
            CALL sp_redistribute_payments_for_description(v_prof_id, v_promo_id);
        END IF;
    END IF;
END
SQL);
    }

    public function down(Schema $schema): void
    {
        // Restaurer les triggers sans redistribution
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_insert_repas");
        $this->addSql("DROP TRIGGER IF EXISTS trg_fact_after_delete_repas");

        // Note: il faudrait recréer les anciens triggers ici pour un rollback complet
    }
}
