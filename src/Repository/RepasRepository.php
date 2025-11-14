<?php

namespace App\Repository;

use App\Entity\Calendrier;
use App\Entity\Professeur;
use App\Entity\Repas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RepasRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Repas::class);
    }

    /** Repas pour un set d’ids calendrier & profs (brut) */
    public function findByCalendarsAndProfs(array $calendarIds, array $profIds): array
    {
        if (!$calendarIds || !$profIds) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->select('IDENTITY(r.refCalendrier) AS calId, IDENTITY(r.Professeur) AS profId')
            ->andWhere('IDENTITY(r.refCalendrier) IN (:cals)')
            ->andWhere('IDENTITY(r.Professeur) IN (:profs)')
            ->setParameter('cals', $calendarIds)
            ->setParameter('profs', $profIds)
            ->getQuery()
            ->getArrayResult();
    }

    public function findOneByCalAndProf(Calendrier $cal, Professeur $prof): ?Repas
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.refCalendrier = :cal')
            ->andWhere('r.Professeur = :prof')
            ->setParameter('cal', $cal)
            ->setParameter('prof', $prof)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
