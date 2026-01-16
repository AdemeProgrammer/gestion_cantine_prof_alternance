<?php

namespace App\Controller;

use App\Entity\Facturation;
use App\Form\FacturationType;
use App\Repository\FacturationRepository;
use App\Service\PaymentRedistributionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/facturation')]
final class FacturationController extends AbstractController
{
    #[Route(name: 'app_facturation_index', methods: ['GET'])]
    public function index(FacturationRepository $facturationRepository): Response
    {
        return $this->render('facturation/index.html.twig', [
            'facturations' => $facturationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_facturation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $facturation = new Facturation();
        $form = $this->createForm(FacturationType::class, $facturation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($facturation);
            $entityManager->flush();

            return $this->redirectToRoute('app_facturation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('facturation/new.html.twig', [
            'facturation' => $facturation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_facturation_show', methods: ['GET'])]
    public function show(Facturation $facturation): Response
    {
        return $this->render('facturation/show.html.twig', [
            'facturation' => $facturation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_facturation_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Facturation $facturation,
        EntityManagerInterface $entityManager,
        PaymentRedistributionService $redistributionService
    ): Response {
        // Sauvegarder les valeurs originales pour détecter les changements
        $originalMontantTotal = $facturation->getMontantTotal();
        $originalNbRepas = $facturation->getNbRepas();
        $originalReportM1 = $facturation->getReportM1();

        $form = $this->createForm(FacturationType::class, $facturation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Détecter si les champs impactant les paiements ont changé
            $hasRelevantChanges =
                $originalMontantTotal !== $facturation->getMontantTotal() ||
                $originalNbRepas !== $facturation->getNbRepas() ||
                $originalReportM1 !== $facturation->getReportM1();

            $entityManager->flush();

            // Redistribuer les paiements uniquement si nécessaire
            if ($hasRelevantChanges) {
                $redistributionService->redistributePaymentsForFacturation($facturation);
                $this->addFlash('info', 'Facturation modifiée. Les paiements ont été redistribués automatiquement.');
            } else {
                $this->addFlash('success', 'Facturation modifiée.');
            }

            return $this->redirectToRoute('app_facturation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('facturation/edit.html.twig', [
            'facturation' => $facturation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_facturation_delete', methods: ['POST'])]
    public function delete(Request $request, Facturation $facturation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$facturation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($facturation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_facturation_index', [], Response::HTTP_SEE_OTHER);
    }
}
