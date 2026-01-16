<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260116153931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la redistribution automatique des paiements lors de modification de facturation';
    }

    public function up(Schema $schema): void
    {
        // Suppression des anciens trigger/procédure si existants
        $this->addSql("DROP TRIGGER IF EXISTS trg_facturation_after_update");
        $this->addSql("DROP PROCEDURE IF EXISTS sp_redistribute_payments_for_description");

        // Création de la procédure stockée pour redistribuer les paiements
        $this->addSql(<<<'SQL'
CREATE PROCEDURE sp_redistribute_payments_for_description(
    IN p_prof_id INT,
    IN p_promo_id INT
)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_paiement_montant DECIMAL(10,2);
    DECLARE v_montant_rest DECIMAL(10,2);
    DECLARE v_fact_id INT;
    DECLARE v_fact_total DECIMAL(10,2);
    DECLARE v_fact_regle DECIMAL(10,2);
    DECLARE v_fact_report DECIMAL(10,2);
    DECLARE v_fact_restant DECIMAL(10,2);

    -- Cursor pour itérer sur tous les paiements de cette Description
    DECLARE paiement_cursor CURSOR FOR
        SELECT p.montant
        FROM paiement p
        INNER JOIN description d ON d.id = p.ref_description_id_id
        WHERE d.ref_professeur_id = p_prof_id
          AND d.ref_promo_id = p_promo_id
        ORDER BY p.date_paiement ASC, p.id ASC;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Étape 1 : Réinitialiser toutes les facturations de ce professeur pour cette promo
    UPDATE facturation f
    SET f.montant_regle = 0.00,
        f.statut = 'En attente'
    WHERE f.ref_professeur_id = p_prof_id
      AND EXISTS (
          SELECT 1
          FROM calendrier c
          WHERE c.ref_promo_id = p_promo_id
            AND CAST(DATE_FORMAT(c.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci = f.mois COLLATE utf8mb4_unicode_ci
          LIMIT 1
      );

    -- Étape 2 : Redistribuer chaque paiement chronologiquement
    OPEN paiement_cursor;

    payment_loop: LOOP
        FETCH paiement_cursor INTO v_paiement_montant;

        IF done THEN
            LEAVE payment_loop;
        END IF;

        SET v_montant_rest = v_paiement_montant;

        -- Distribuer ce paiement sur les facturations non payées
        distribution_loop: WHILE v_montant_rest > 0 DO
            SET v_fact_id = NULL;

            -- Trouver la prochaine facturation non payée
            SELECT f.id, f.montant_total, f.montant_regle, f.report_m_1
              INTO v_fact_id, v_fact_total, v_fact_regle, v_fact_report
            FROM facturation f
            WHERE f.ref_professeur_id = p_prof_id
              AND ROUND((f.montant_total + f.report_m_1 - f.montant_regle), 2) > 0
              AND f.statut = 'En attente'
              AND EXISTS (
                  SELECT 1
                  FROM calendrier c
                  WHERE c.ref_promo_id = p_promo_id
                    AND CAST(DATE_FORMAT(c.`date`, '%Y-%m') AS CHAR(7)) COLLATE utf8mb4_unicode_ci = f.mois COLLATE utf8mb4_unicode_ci
                  LIMIT 1
              )
            ORDER BY f.mois ASC
            LIMIT 1;

            -- Plus de facturations non payées
            IF v_fact_id IS NULL THEN
                LEAVE distribution_loop;
            END IF;

            SET v_fact_restant = ROUND((v_fact_total + v_fact_report - v_fact_regle), 2);

            IF v_fact_restant <= 0 THEN
                LEAVE distribution_loop;
            END IF;

            -- Appliquer le paiement sur cette facturation
            IF v_montant_rest >= v_fact_restant THEN
                -- Le paiement couvre complètement cette facturation
                UPDATE facturation f
                SET f.montant_regle = ROUND(f.montant_regle + v_fact_restant, 2),
                    f.statut = 'Payé'
                WHERE f.id = v_fact_id;

                SET v_montant_rest = ROUND(v_montant_rest - v_fact_restant, 2);
            ELSE
                -- Le paiement couvre partiellement cette facturation
                UPDATE facturation f
                SET f.montant_regle = ROUND(f.montant_regle + v_montant_rest, 2)
                WHERE f.id = v_fact_id;

                SET v_montant_rest = 0.00;
            END IF;

        END WHILE distribution_loop;

    END LOOP payment_loop;

    CLOSE paiement_cursor;
END
SQL);

        // Création du trigger AFTER UPDATE sur facturation
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_facturation_after_update
AFTER UPDATE ON facturation
FOR EACH ROW
BEGIN
    DECLARE v_promo_id INT;

    -- Déclencher uniquement si les champs impactant les paiements ont changé
    IF OLD.montant_total <> NEW.montant_total
       OR OLD.nb_repas <> NEW.nb_repas
       OR OLD.report_m_1 <> NEW.report_m_1 THEN

        -- Récupérer le promo_id associé à ce professeur
        SELECT d.ref_promo_id
          INTO v_promo_id
        FROM description d
        WHERE d.ref_professeur_id = NEW.ref_professeur_id
        LIMIT 1;

        -- Redistribuer tous les paiements pour cette Description
        IF v_promo_id IS NOT NULL THEN
            CALL sp_redistribute_payments_for_description(NEW.ref_professeur_id, v_promo_id);
        END IF;

    END IF;
END
SQL);
    }

    public function down(Schema $schema): void
    {
        // Suppression du trigger et de la procédure stockée
        $this->addSql("DROP TRIGGER IF EXISTS trg_facturation_after_update");
        $this->addSql("DROP PROCEDURE IF EXISTS sp_redistribute_payments_for_description");
    }
}
