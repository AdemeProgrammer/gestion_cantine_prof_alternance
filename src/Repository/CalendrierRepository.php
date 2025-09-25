<?php

namespace App\Repository;

use App\Entity\Calendrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Promo;


/**
 * @extends ServiceEntityRepository<Calendrier>
 */
class CalendrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Calendrier::class);
    }

    //    /**
    //     * @return Calendrier[] Returns an array of Calendrier objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Calendrier
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function findByPromoBetween(Promo $promo, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.refPromo = :promo')
            ->andWhere('c.date BETWEEN :s AND :e')
            ->setParameter('promo', $promo)
            ->setParameter('s', $start->format('Y-m-d'))
            ->setParameter('e', $end->format('Y-m-d'))
            ->orderBy('c.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
