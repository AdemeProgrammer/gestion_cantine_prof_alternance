<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du trigger AFTER DELETE sur paiement pour redistribuer les paiements sur les facturations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DROP TRIGGER IF EXISTS trg_paiement_after_delete");

        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_paiement_after_delete
AFTER DELETE ON paiement
FOR EACH ROW
BEGIN
    DECLARE v_prof_id INT;
    DECLARE v_promo_id INT;

    SELECT d.ref_professeur_id, d.ref_promo_id
      INTO v_prof_id, v_promo_id
    FROM description d
    WHERE d.id = OLD.ref_description_id_id
    LIMIT 1;

    IF v_prof_id IS NOT NULL AND v_promo_id IS NOT NULL THEN
        CALL sp_redistribute_payments_for_description(v_prof_id, v_promo_id);
    END IF;
END
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TRIGGER IF EXISTS trg_paiement_after_delete");
    }
}
