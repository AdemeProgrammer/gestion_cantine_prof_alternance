<?php

namespace App\Repository;

use App\Entity\Calendrier;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CalendrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Calendrier::class);
    }

    public function countBetween(DateTimeInterface $from, DateTimeInterface $to, ?int $promoId = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.date BETWEEN :from AND :to')
            ->setParameter('from', DateTimeImmutable::createFromInterface($from))
            ->setParameter('to',   DateTimeImmutable::createFromInterface($to));

        if ($promoId !== null) {
            $qb->andWhere('IDENTITY(c.refPromo) = :promoId')->setParameter('promoId', $promoId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findOneByDate(DateTimeInterface $date, ?int $promoId = null): ?Calendrier
    {
        $start = DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $end   = $start->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end',   $end)
            ->setMaxResults(1);

        if ($promoId !== null) {
            $qb->andWhere('IDENTITY(c.refPromo) = :promoId')->setParameter('promoId', $promoId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /** ['Y-m-d' => 'ouvre'/'ferie'/...] (converti enum → string si besoin) */
    public function getMonthTypesMap(DateTimeInterface $from, DateTimeInterface $to, ?int $promoId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.date AS d, c.type_jour AS t')
            ->andWhere('c.date BETWEEN :from AND :to')
            ->setParameter('from', DateTimeImmutable::createFromInterface($from))
            ->setParameter('to',   DateTimeImmutable::createFromInterface($to))
            ->orderBy('c.date', 'ASC');

        if ($promoId !== null) {
            $qb->andWhere('IDENTITY(c.refPromo) = :promoId')->setParameter('promoId', $promoId);
        }

        $rows = $qb->getQuery()->getArrayResult();
        $out = [];
        foreach ($rows as $r) {
            $t = $r['t'];
            if ($t instanceof \BackedEnum)      { $type = $t->value; }
            elseif ($t instanceof \UnitEnum)    { $type = $t->name; }
            else                                { $type = is_scalar($t) ? (string)$t : ''; }
            $out[$r['d']->format('Y-m-d')] = $type;
        }
        return $out;
    }

    /** [ ['id'=>int, 'd'=>\DateTimeImmutable], ... ] trié ASC */
    public function getMonthCalendars(DateTimeInterface $from, DateTimeInterface $to, ?int $promoId = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.id AS id, c.date AS d')
            ->andWhere('c.date BETWEEN :from AND :to')
            ->setParameter('from', DateTimeImmutable::createFromInterface($from))
            ->setParameter('to',   DateTimeImmutable::createFromInterface($to))
            ->orderBy('c.date', 'ASC');

        if ($promoId !== null) {
            $qb->andWhere('IDENTITY(c.refPromo) = :promoId')->setParameter('promoId', $promoId);
        }

        return $qb->getQuery()->getArrayResult();
    }
}
