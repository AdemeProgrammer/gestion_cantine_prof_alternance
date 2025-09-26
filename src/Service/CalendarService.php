<?php

namespace App\Service;

use App\Repository\CalendrierRepository;
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;

final class CalendarService
{
    public function __construct(
        private readonly CalendrierRepository $calRepo,
        private readonly string $tz  = 'Europe/Paris',
        private readonly string $loc = 'fr_FR',
    ) {}

    public function getMonthDaysCount(int $year, int $month, ?int $promoId = null): int
    {
        $from = (new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month)))->setTimezone(new \DateTimeZone($this->tz));
        $to   = $from->modify('last day of this month')->setTime(23, 59, 59);
        return $this->calRepo->countBetween($from, $to, $promoId);
    }

    public function getDayType(DateTimeInterface $date, ?int $promoId = null): ?string
    {
        $d = DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone($this->tz))->setTime(0, 0, 0);
        $row = $this->calRepo->findOneByDate($d, $promoId);
        if (!$row) return null;
        $t = $row->getTypeJour();
        if ($t instanceof \BackedEnum) return (string)$t->value;
        if ($t instanceof \UnitEnum)   return (string)$t->name;
        return is_scalar($t) ? (string)$t : null;
    }

    public function getWeekdayName(DateTimeInterface $date): string
    {
        $fmt = new IntlDateFormatter($this->loc, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $this->tz, IntlDateFormatter::GREGORIAN, 'EEEE');
        $d = DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone($this->tz))->setTime(0,0,0);
        return $fmt->format($d);
    }

    public function getWeekdayIndex(DateTimeInterface $date): int
    {
        return ((int) DateTimeImmutable::createFromInterface($date)->format('N')) - 1; // 0=lun .. 6=dim
    }

    public function getMonthTypes(int $year, int $month, ?int $promoId = null): array
    {
        $from = (new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month)))->setTimezone(new \DateTimeZone($this->tz));
        $to   = $from->modify('last day of this month')->setTime(23, 59, 59);
        return $this->calRepo->getMonthTypesMap($from, $to, $promoId);
    }
}
