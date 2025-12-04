<?php

namespace App\Controller;

use App\Entity\Professeur;
use App\Form\ProfesseurType;
use App\Repository\ProfesseurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/professeur')]
final class ProfesseurController extends AbstractController
{
    #[Route(name: 'app_professeur_index', methods: ['GET'])]
    public function index(ProfesseurRepository $professeurRepository): Response
    {
        return $this->render('professeur/index.html.twig', [
            'professeurs' => $professeurRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_professeur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $professeur = new Professeur();
        $form = $this->createForm(ProfesseurType::class, $professeur);
        $form->remove('est_actif');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $professeur->setEstActif(true);
            $entityManager->persist($professeur);
            $entityManager->flush();

            return $this->redirectToRoute('app_professeur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('professeur/new.html.twig', [
            'professeur' => $professeur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_professeur_show', methods: ['GET'])]
    public function show(Professeur $professeur): Response
    {
        // On récupère toutes les descriptions liées au professeur
        $descriptions = $professeur->getDescriptions();

        // On extrait les promotions liées sans doublons
        $promos = [];
        foreach ($descriptions as $desc) {
            $promo = $desc->getRefPromo();
            if (!in_array($promo, $promos, true)) {
                $promos[] = $promo;
            }
        }

        // Trier les promotions du plus récent au plus ancien
        usort($promos, function ($a, $b) {
            return $b->getAnneeDebut() <=> $a->getAnneeDebut();
        });

        // Associer les facturations aux promotions en fonction de l'année scolaire
        // Une année scolaire va de septembre (année N) à juillet (année N+1)
        $facturationsByPromo = [];
        foreach ($promos as $promo) {
            $facturationsByPromo[$promo->getId()] = [];

            foreach ($professeur->getFacturations() as $facturation) {
                // Extraire l'année du mois de facturation (format attendu: "YYYY-MM" ou "Septembre 2024", etc.)
                $mois = $facturation->getMois();

                // Essayer de parser le mois pour extraire l'année et le mois
                if (preg_match('/(\d{4})-(\d{2})/', $mois, $matches)) {
                    // Format YYYY-MM
                    $annee = (int)$matches[1];
                    $moisNum = (int)$matches[2];
                } elseif (preg_match('/(\d{4})/', $mois, $matches)) {
                    // Si on trouve juste une année
                    $annee = (int)$matches[1];
                    // Essayer de déduire le mois
                    if (stripos($mois, 'janvier') !== false) $moisNum = 1;
                    elseif (stripos($mois, 'février') !== false || stripos($mois, 'fevrier') !== false) $moisNum = 2;
                    elseif (stripos($mois, 'mars') !== false) $moisNum = 3;
                    elseif (stripos($mois, 'avril') !== false) $moisNum = 4;
                    elseif (stripos($mois, 'mai') !== false) $moisNum = 5;
                    elseif (stripos($mois, 'juin') !== false) $moisNum = 6;
                    elseif (stripos($mois, 'juillet') !== false) $moisNum = 7;
                    elseif (stripos($mois, 'août') !== false || stripos($mois, 'aout') !== false) $moisNum = 8;
                    elseif (stripos($mois, 'septembre') !== false) $moisNum = 9;
                    elseif (stripos($mois, 'octobre') !== false) $moisNum = 10;
                    elseif (stripos($mois, 'novembre') !== false) $moisNum = 11;
                    elseif (stripos($mois, 'décembre') !== false || stripos($mois, 'decembre') !== false) $moisNum = 12;
                    else $moisNum = 0;
                } else {
                    continue; // On ne peut pas parser cette facturation
                }

                // Déterminer à quelle promotion appartient cette facturation
                // Si le mois est >= 9 (septembre ou après), c'est l'année de début
                // Si le mois est < 9 (avant septembre), c'est l'année de fin
                if ($moisNum >= 9) {
                    // De septembre à décembre : on est dans l'année de début
                    if ($annee == $promo->getAnneeDebut()) {
                        $facturationsByPromo[$promo->getId()][] = $facturation;
                    }
                } else {
                    // De janvier à août : on est dans l'année de fin
                    if ($annee == $promo->getAnneeFin()) {
                        $facturationsByPromo[$promo->getId()][] = $facturation;
                    }
                }
            }
        }

        return $this->render('professeur/show.html.twig', [
            'professeur' => $professeur,
            'promotions' => $promos,
            'facturationsByPromo' => $facturationsByPromo,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_professeur_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Professeur $professeur, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProfesseurType::class, $professeur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_professeur_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('professeur/edit.html.twig', [
            'professeur' => $professeur,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_professeur_delete', methods: ['POST'])]
    public function delete(Request $request, Professeur $professeur, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $professeur->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($professeur);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_professeur_index', [], Response::HTTP_SEE_OTHER);
    }
}
