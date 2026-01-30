<?php

namespace App\Controller;

use App\Entity\Paiement;
use App\Entity\Professeur;
use App\Form\PaiementType;
use App\Repository\FacturationRepository;
use App\Repository\PaiementRepository;
use App\Repository\DescriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/paiement')]
final class PaiementController extends AbstractController
{
    #[Route(name: 'app_paiement_index', methods: ['GET'])]
    public function index(PaiementRepository $paiementRepository): Response
    {
        return $this->render('paiement/index.html.twig', [
            'paiements' => $paiementRepository->findAll(),
        ]);
    }

    #[Route('/new/{id}', name: 'app_paiement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Professeur $professeur): Response
    {
        $paiement = new Paiement();
        $form = $this->createForm(PaiementType::class, $paiement,[ "professeur" => $professeur ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($paiement);
            $entityManager->flush();

            return $this->redirectToRoute('app_paiement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('paiement/new.html.twig', [
            'paiement' => $paiement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_paiement_show', methods: ['GET'])]
    public function show(Paiement $paiement): Response
    {
        return $this->render('paiement/show.html.twig', [
            'paiement' => $paiement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_paiement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Paiement $paiement, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PaiementType::class, $paiement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_paiement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('paiement/edit.html.twig', [
            'paiement' => $paiement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_paiement_delete', methods: ['POST'])]
    public function delete(Request $request, Paiement $paiement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$paiement->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($paiement);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_paiement_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/ajax/max-montant/{descriptionId}', name: 'app_paiement_max_montant', methods: ['GET'])]
    public function getMaxMontant(
        int $descriptionId,
        DescriptionRepository $descriptionRepository,
        FacturationRepository $facturationRepository
    ): JsonResponse {
        $description = $descriptionRepository->find($descriptionId);

        if (!$description) {
            return new JsonResponse(['error' => 'Description non trouvée'], 404);
        }

        $professeur = $description->getRefProfesseur();
        $promo = $description->getRefPromo();

        if (!$professeur) {
            return new JsonResponse(['error' => 'Professeur non trouvé'], 404);
        }

        // Calculer le montant restant uniquement pour les facturations de cette promotion
        $montantRestantPromo = '0';

        if ($promo) {
            $anneeDebut = $promo->getAnneeDebut();
            $anneeFin = $promo->getAnneeFin();

            // Récupérer toutes les facturations du professeur
            $facturations = $facturationRepository->findBy(['refProfesseur' => $professeur]);

            // Filtrer les facturations par année de la promotion (ex: 2025-2026)
            foreach ($facturations as $facturation) {
                $mois = $facturation->getMois();

                // Vérifier si le mois contient l'année de début ou de fin de la promo
                if (str_contains($mois, (string)$anneeDebut) || str_contains($mois, (string)$anneeFin)) {
                    $montantRestant = $facturation->getMontantRestant();
                    if ($montantRestant !== null) {
                        $montantRestantPromo = bcadd($montantRestantPromo, $montantRestant, 2);
                    }
                }
            }
        }

        // S'assurer que le montant n'est pas négatif
        if (bccomp($montantRestantPromo, '0', 2) < 0) {
            $montantRestantPromo = '0';
        }

        return new JsonResponse([
            'maxMontant' => (float) $montantRestantPromo,
            'professeur' => [
                'nom' => $professeur->getNom(),
                'prenom' => $professeur->getPrenom(),
            ],
        ]);
    }
}
