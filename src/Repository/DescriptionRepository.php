<?php

namespace App\Repository;

use App\Entity\Description;
use App\Entity\Promo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DescriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Description::class);
    }

    /** Descriptions de la promo avec profs ACTIFS (Professeur.est_actif = true) */
    public function findByPromoWithActiveProfs(Promo $promo): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('p')
            ->join('d.refProfesseur', 'p')
            ->andWhere('d.refPromo = :promo')
            ->andWhere('p.est_actif = :actif')
            ->setParameter('promo', $promo)
            ->setParameter('actif', true)
            ->orderBy('p.nom', 'ASC')
            ->addOrderBy('p.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
