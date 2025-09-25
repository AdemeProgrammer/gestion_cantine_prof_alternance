<?php

namespace App\Controller;

use App\Entity\Promo;
use App\Entity\Calendrier;
use App\Repository\CalendrierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

#[Route('/promo/{id}/calendrier', requirements: ['id' => '\d+'])]
final class CalendrierController extends AbstractController
{
    #[Route('', name: 'cal_summary', methods: ['GET'])]
    public function summary(Promo $promo, CalendrierRepository $repo): Response
    {
        $start = new \DateTime($promo->getAnneeDebut().'-09-01');
        $end   = new \DateTime($promo->getAnneeFin().'-08-31');

        $days = $repo->findByPromoBetween($promo, $start, $end);

        $byMonth = [];
        foreach ($days as $d) {
            $ym = $d->getDate()->format('Y-m');
            $byMonth[$ym] ??= [
                'label' => $d->getDate()->format('F Y'),
                'total' => 0, 'Semaine'=>0, 'Weekend'=>0, 'Férié'=>0, 'Vacances'=>0
            ];
            $byMonth[$ym]['total']++;
            $type = $d->getTypeJour()->value; // Enum -> string: Semaine/Weekend/Férié/Vacances/...
            if (isset($byMonth[$ym][$type])) $byMonth[$ym][$type]++;
        }
        ksort($byMonth);

        return $this->render('calendrier/summary.html.twig', [
            'promo'  => $promo,
            'months' => $byMonth,
        ]);
    }

    #[Route('/{year}/{month}', name: 'cal_month', methods: ['GET'], requirements: ['year'=>'\d{4}','month'=>'\d{1,2}'])]
    public function month(Promo $promo, CalendrierRepository $repo, int $year, int $month): Response
    {
        $first = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $last  = (clone $first)->modify('last day of this month');

        $days = $repo->findByPromoBetween($promo, $first, $last);

        $map = [];
        foreach ($days as $d) $map[$d->getDate()->format('Y-m-d')] = $d;

        $gridStart = (clone $first)->modify('monday this week');
        $gridEnd   = (clone $last)->modify('sunday this week');

        $weeks = [];
        $cur = clone $gridStart;
        while ($cur <= $gridEnd) {
            $row = [];
            for ($i=0; $i<7; $i++) {
                $key = $cur->format('Y-m-d');
                $row[] = [
                    'date'    => clone $cur,
                    'item'    => $map[$key] ?? null,
                    'inMonth' => ((int)$cur->format('m') === $month),
                ];
                $cur->modify('+1 day');
            }
            $weeks[] = $row;
        }

        return $this->render('calendrier/month.html.twig', [
            'promo' => $promo,
            'year'  => $year,
            'month' => $month,
            'weeks' => $weeks,
        ]);
    }

    #[Route('/save-meal', name: 'cal_save_meal', methods: ['POST'])]
    public function saveMeal(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $calId = (int) $request->request->get('cal_id');
        $menu  = trim((string) $request->request->get('menu'));

        /** @var Calendrier|null $cal */
        $cal = $em->getRepository(Calendrier::class)->find($calId);
        if (!$cal) {
            $this->addFlash('danger', 'Jour introuvable.');
            return $this->redirect($request->headers->get('referer') ?? '/');
        }

        // On tente d'abord setMenuProfs(), sinon setCommentaire(), sinon warning.
        $saved = false;
        if (method_exists($cal, 'setMenuProfs')) {
            $cal->setMenuProfs($menu);
            $saved = true;
        } elseif (method_exists($cal, 'setCommentaire')) {
            $cal->setCommentaire($menu);
            $saved = true;
        }

        if ($saved) {
            $em->flush();
            $this->addFlash('success', 'Repas profs enregistré.');
        } else {
            $this->addFlash('warning', "Ajoute un champ 'menu_profs' (TEXT) ou 'commentaire' à l'entité Calendrier.");
        }

        return $this->redirect($request->headers->get('referer') ?? '/');
    }
}
