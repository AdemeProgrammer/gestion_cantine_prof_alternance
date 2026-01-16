<?php

namespace App\Service;

use App\Entity\Facturation;
use Doctrine\DBAL\Connection;

class PaymentRedistributionService
{
    public function __construct(
        private Connection $connection
    ) {}

    /**
     * Redistribue tous les paiements pour un professeur/promo donnés
     */
    public function redistributePaymentsForFacturation(Facturation $facturation): void
    {
        $professorId = $facturation->getRefProfesseur()?->getId();

        if (!$professorId) {
            return;
        }

        // Récupérer le promo_id associé au professeur
        $sql = "
            SELECT d.ref_promo_id
            FROM description d
            WHERE d.ref_professeur_id = :prof_id
            LIMIT 1
        ";

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->executeQuery(['prof_id' => $professorId]);
        $promoId = $result->fetchOne();

        if (!$promoId) {
            return;
        }

        // Appeler la procédure stockée de redistribution
        $this->connection->executeStatement(
            'CALL sp_redistribute_payments_for_description(:prof_id, :promo_id)',
            [
                'prof_id' => $professorId,
                'promo_id' => $promoId
            ]
        );
    }
}
