<?php

namespace App\Controller;

use App\Entity\Promo;
use App\Entity\Repas;
use App\Repository\CalendrierRepository;
use App\Repository\DescriptionRepository;
use App\Repository\ProfesseurRepository;
use App\Repository\RepasRepository;
use App\Service\CalendarService;
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

    /**
     * MONTH = tableau cliquable
     * RÈGLE D’AFFICHAGE : CASE COCHÉE ⇢ il existe une ligne `repas` (on ignore totalement `description`)
     * On construit les colonnes à partir de la BDD `calendrier` (donc plus de décalage de jours).
     */
    #[Route('/month/{id}', name: 'month', methods: ['GET'])]
    public function month(
        Promo $promo,
        Request $request,
        CalendarService $calService,
        CalendrierRepository $calRepo,
        DescriptionRepository $descRepo,
        RepasRepository $repasRepo
    ): Response {
        $now   = new DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        $year  = (int) ($request->query->get('y') ?: $now->format('Y'));
        $month = (int) ($request->query->get('m') ?: $now->format('n'));

        $firstOfMonth = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month), new \DateTimeZone('Europe/Paris'));
        $lastOfMonth  = $firstOfMonth->modify('last day of this month')->setTime(23, 59, 59);

        // 1) Colonnes construites DEPUIS LA BDD calendrier (filtrées Mon-Fri)
        //    -> aucune recomposition côté PHP => zéro décalage
        $calRows = $calRepo->getMonthCalendars($firstOfMonth, $lastOfMonth, $promo->getId());
        $columns = [];           // liste ordonnée de colonnes [{ymd, calId, date, dowLabel}]
        $ymdKeys = [];           // ymd par colonne
        $calIds  = [];           // id calendrier par colonne
        $dateToCalId = [];       // ymd -> calId

        foreach ($calRows as $r) {
            /** @var DateTimeInterface $d */
            $d = $r['d'];
            if ((int)$d->format('N') > 5) {
                continue; // on garde uniquement lundi..vendredi
            }
            $ymd = $d->format('Y-m-d');
            $dateToCalId[$ymd] = (int) $r['id'];
            $columns[] = [
                'ymd'      => $ymd,
                'calId'    => (int) $r['id'],
                'date'     => $d,
                'dowLabel' => strtolower(\IntlDateFormatter::formatObject($d, 'EEE', 'fr_FR')), // ex: jeu., ven.
            ];
        }
        // Normalise l’ordre (au cas où le repo ne soit pas déjà trié)
        usort($columns, static fn($a, $b) => strcmp($a['ymd'], $b['ymd']));
        foreach ($columns as $col) {
            $ymdKeys[] = $col['ymd'];
            $calIds[]  = $col['calId'];
        }

        // 2) Types (ligne d’en-tête) depuis le service
        $typesMap = $calService->getMonthTypes($year, $month, $promo->getId());

        // 3) Profs affichés = profs ayant description active dans la promo (structure existante)
        $descs   = $descRepo->findByPromoWithActiveProfs($promo);
        $profIds = array_map(fn($d) => $d->getRefProfesseur()->getId(), $descs);

        // 4) Repas existants pour ces colonnes+profs (vérité d’affichage)
        $repasRows = [];
        if ($calIds && $profIds) {
            $repasRows = $repasRepo->findByCalendarsAndProfs($calIds, $profIds);
        }

        // 5) presence[profId][ymd] = true si une ligne repas existe (on NE regarde PAS est_consomme)
        $presence = [];
        foreach ($repasRows as $row) {
            $calId = (int) $row['calId'];
            $profId = (int) $row['profId'];
            // retrouves le ymd directement via $columns
            // (on peut garder une table calId->ymd pour éviter array_search)
            // construisons-la une fois :
        }
        $calIdToYmd = array_column($columns, 'ymd', 'calId'); // calId => ymd
        foreach ($repasRows as $row) {
            $profId = (int) $row['profId'];
            $ymd = $calIdToYmd[(int)$row['calId']] ?? null;
            if ($ymd) {
                $presence[$profId][$ymd] = true;
            }
        }

        // 6) Matrice rows/cells : final = 1 ssi repas existe (ignore totalement "expected")
        $rows = [];
        foreach ($descs as $desc) {
            $p   = $desc->getRefProfesseur();
            $pid = $p->getId();

            $cells = [];
            foreach ($columns as $col) {
                $ymd = $col['ymd'];
                $has = !empty($presence[$pid][$ymd]);
                $cells[] = [
                    'final'       => $has ? 1 : 0,
                    'hasOverride' => $has,
                    'ymd'         => $ymd,
                ];
            }

            $rows[] = ['prof' => $p, 'profId' => $pid, 'cells' => $cells];
        }

        $prev = $firstOfMonth->modify('-1 month');
        $next = $firstOfMonth->modify('+1 month');

        return $this->render('calendrier/month.html.twig', [
            'promo'         => $promo,
            'year'          => $year,
            'month'         => $month,
            'firstOfMonth'  => $firstOfMonth,

            // colonnes basées sur la BDD (plus fiables)
            'columns'       => $columns, // [{ymd, calId, date, dowLabel}]
            'ymdKeys'       => $ymdKeys,
            'typesMap'      => $typesMap,
            'rows'          => $rows,
            'calIds'        => $calIds,

            'prev'          => ['y'=>(int)$prev->format('Y'),'m'=>(int)$prev->format('n')],
            'next'          => ['y'=>(int)$next->format('Y'),'m'=>(int)$next->format('n')],
            'csrf'          => $this->container->get('security.csrf.token_manager')->getToken('cal_meal')->getValue(),
        ]);
    }

    /**
     * upsert AJAX d’une case
     * Cocher = créer la ligne `repas` si absente ; Décocher = supprimer la ligne.
     */
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

        if ($value) {
            if (!$repas) {
                $repas = (new Repas())
                    ->setRefCalendrier($cal)
                    ->setProfesseur($prof)
                    ->setEstConsomme(false);
                $em->persist($repas);
            }
        } else {
            if ($repas) {
                $em->remove($repas);
            }
        }

        $em->flush();

        return $this->json(['ok' => true]);
    }
}
