<?php

namespace App\Controller;

use App\Entity\Promo;
use App\Form\PromoType;
use App\Repository\PromoRepository;
use App\Service\CalendarGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/promo')]
final class PromoController extends AbstractController
{
    #[Route(name: 'app_promo_index', methods: ['GET'])]
    public function index(PromoRepository $promoRepository): Response
    {
        return $this->render('promo/index.html.twig', [
            'promos' => $promoRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_promo_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        CalendarGenerator $calendarGenerator
    ): Response {
        $promo = new Promo();
        $form = $this->createForm(PromoType::class, $promo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarde de la promo
            $entityManager->persist($promo);
            $entityManager->flush();

            // Génération du calendrier lié à la promo
            $calendriers = $calendarGenerator->generate($promo);

            foreach ($calendriers as $cal) {
                $entityManager->persist($cal);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Promo et calendrier générés avec succès.');

            return $this->redirectToRoute('app_promo_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('promo/new.html.twig', [
            'promo' => $promo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_promo_show', methods: ['GET'])]
    public function show(Promo $promo): Response
    {
        return $this->render('promo/show.html.twig', [
            'promo' => $promo,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_promo_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Promo $promo,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(PromoType::class, $promo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Promo modifiée avec succès.');

            return $this->redirectToRoute('app_promo_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('promo/edit.html.twig', [
            'promo' => $promo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_promo_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Promo $promo,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $promo->getId(), $request->request->get('_token'))) {
            $entityManager->remove($promo);
            $entityManager->flush();

            $this->addFlash('success', 'Promo supprimée avec succès.');
        }

        return $this->redirectToRoute('app_promo_index', [], Response::HTTP_SEE_OTHER);
    }
}
