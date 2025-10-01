<?php

namespace App\Controller;

use App\Entity\Promo;
use App\Entity\Repas;
use App\Repository\CalendrierRepository;
use App\Repository\DescriptionRepository;
use App\Repository\ProfesseurRepository;
use App\Repository\RepasRepository;
use App\Service\CalendarService;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cal', name: 'cal_')]
final class CalendrierController extends AbstractController
{
    /** SUMMARY = 12 mois (sept→août) */
    #[Route('/summary/{id}', name: 'summary', methods: ['GET'])]
    public function summary(Promo $promo, CalendarService $cal): Response
    {
        $startYear = (int) $promo->getAnneeDebut();
        $endYear   = (int) $promo->getAnneeFin();

        $months = [];
        for ($m = 9; $m <= 12; $m++) $months[] = ['y' => $startYear, 'm' => $m];
        for ($m = 1; $m <= 7;  $m++) $months[] = ['y' => $endYear,   'm' => $m];

        $cards = [];
        foreach ($months as $mm) {
            $y = $mm['y']; $m = $mm['m'];
            $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m), new \DateTimeZone('Europe/Paris'));
            $cards[] = [
                'year'       => $y,
                'month'      => $m,
                'labelLong'  => ucfirst(\IntlDateFormatter::formatObject($first, 'LLLL yyyy', 'fr_FR')),
                'labelShort' => ucfirst(\IntlDateFormatter::formatObject($first, 'LLL', 'fr_FR')),
                'daysCount'  => $cal->getMonthDaysCount($y, $m, $promo->getId()),
            ];
        }

        return $this->render('calendrier/summary.html.twig', [
            'promo' => $promo,
            'cards' => $cards,
        ]);
    }

    /** MONTH = détail avec cases cochables (final = override ?? expected) */
    #[Route('/month/{id}', name: 'month', methods: ['GET'])]
    public function month(
        Promo $promo,
        Request $request,
        CalendarService $cal,
        CalendrierRepository $calRepo,
        DescriptionRepository $descRepo,
        RepasRepository $repasRepo
    ): Response {
        $now   = new DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        $year  = (int) ($request->query->get('y') ?: $now->format('Y'));
        $month = (int) ($request->query->get('m') ?: $now->format('n'));

        $firstOfMonth = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), new \DateTimeZone('Europe/Paris'));
        $lastOfMonth  = $firstOfMonth->modify('last day of this month')->setTime(23, 59, 59);

        // jours ouvrés
        $period = new DatePeriod($firstOfMonth, new DateInterval('P1D'), $lastOfMonth->modify('+1 day'));
        $businessDays = array_values(array_filter(iterator_to_array($period), fn(DateTimeInterface $d) => (int)$d->format('N') <= 5));
        $ymdKeys = array_map(fn(DateTimeInterface $d) => $d->format('Y-m-d'), $businessDays);

        // types pour la ligne de tête
        $typesMap = $cal->getMonthTypes($year, $month, $promo->getId());

        // date -> id calendrier
        $calRows = $calRepo->getMonthCalendars($firstOfMonth, $lastOfMonth, $promo->getId());
        $dateToCalId = [];
        $calIdsOrdered = [];
        foreach ($calRows as $r) { $dateToCalId[$r['d']->format('Y-m-d')] = (int) $r['id']; }
        foreach ($ymdKeys as $k) { $calIdsOrdered[] = $dateToCalId[$k] ?? null; }

        // descriptions (profs actifs)
        $descs   = $descRepo->findByPromoWithActiveProfs($promo);
        $profIds = array_map(fn($d) => $d->getRefProfesseur()->getId(), $descs);

        // overrides (repas en BDD) — créés par tes triggers
        $repasRows = $repasRepo->findByCalendarsAndProfs(array_values(array_filter($calIdsOrdered)), $profIds);
        $override = []; // [$profId][$ymd] = 1/0
        foreach ($repasRows as $row) {
            $ymd = array_search((int)$row['calId'], $dateToCalId, true);
            if ($ymd !== false) {
                $override[(int)$row['profId']][$ymd] = $row['consomme'] ? 1 : 0;
            }
        }

        // matrice
        $rows = [];
        foreach ($descs as $desc) {
            $p   = $desc->getRefProfesseur();
            $pid = $p->getId();
            $weekFlags = [
                1 => (bool) $desc->isLundi(),
                2 => (bool) $desc->isMardi(),
                3 => (bool) $desc->isMercredi(),
                4 => (bool) $desc->isJeudi(),
                5 => (bool) $desc->isVendredi(),
            ];
            $cells = [];
            foreach ($businessDays as $d) {
                $n   = (int) $d->format('N');
                $ymd = $d->format('Y-m-d');
                $expected    = !empty($weekFlags[$n]) ? 1 : 0;
                $hasOverride = isset($override[$pid][$ymd]);
                $final       = $hasOverride ? $override[$pid][$ymd] : $expected;
                $cells[] = ['final'=>$final, 'expected'=>$expected, 'hasOverride'=>$hasOverride, 'ymd'=>$ymd];
            }
            $rows[] = ['prof'=>$p, 'profId'=>$pid, 'cells'=>$cells];
        }

        $prev = $firstOfMonth->modify('-1 month');
        $next = $firstOfMonth->modify('+1 month');

        return $this->render('calendrier/month.html.twig', [
            'promo'         => $promo,
            'year'          => $year,
            'month'         => $month,
            'firstOfMonth'  => $firstOfMonth,
            'businessDays'  => $businessDays,
            'weekdayNames'  => array_map(fn($d) => $cal->getWeekdayName($d), $businessDays),
            'ymdKeys'       => $ymdKeys,
            'typesMap'      => $typesMap,
            'rows'          => $rows,
            'calIds'        => $calIdsOrdered,
            'prev'          => ['y'=>(int)$prev->format('Y'),'m'=>(int)$prev->format('n')],
            'next'          => ['y'=>(int)$next->format('Y'),'m'=>(int)$next->format('n')],
            'csrf'          => $this->container->get('security.csrf.token_manager')->getToken('cal_meal')->getValue(),
        ]);
    }

    /** upsert AJAX d’une case */
    #[Route('/save', name: 'save', methods: ['POST'])]
    public function save(
        Request $request,
        EntityManagerInterface $em,
        CalendrierRepository $calRepo,
        ProfesseurRepository $profRepo,
        RepasRepository $repasRepo
    ): JsonResponse {
        $data  = json_decode($request->getContent(), true) ?? [];
        $token = $data['_token'] ?? '';
        if (!$this->isCsrfTokenValid('cal_meal', $token)) {
            return $this->json(['ok' => false, 'error' => 'csrf'], 419);
        }

        $cal  = $calRepo->find((int)($data['calId'] ?? 0));
        $prof = $profRepo->find((int)($data['profId'] ?? 0));
        if (!$cal || !$prof) return $this->json(['ok' => false, 'error' => 'not_found'], 404);

        $value = (bool)($data['value'] ?? false);

        $repas = $repasRepo->findOneByCalAndProf($cal, $prof);
        if (!$repas) {
            $repas = (new Repas())
                ->setRefCalendrier($cal)
                ->setProfesseur($prof);
        }
        $repas->setEstConsomme($value);

        $em->persist($repas);
        $em->flush();

        return $this->json(['ok' => true]);
    }
}
