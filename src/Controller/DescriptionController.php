<?php

namespace App\Controller;

use App\Entity\Description;
use App\Entity\Professeur;
use App\Entity\Promo;
use App\Form\DescriptionType;
use App\Repository\DescriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/description')]
final class DescriptionController extends AbstractController
{
    #[Route(name: 'app_description_index', methods: ['GET'])]
    public function index(DescriptionRepository $descriptionRepository): Response
    {
        return $this->render('description/index.html.twig', [
            'descriptions' => $descriptionRepository->findAll(),
        ]);
    }

    #[Route('/{id}/new', name: 'app_description_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Promo $promo, DescriptionRepository $descriptionRepository): Response
    {
        $description = new Description();
        $form = $this->createForm(DescriptionType::class, $description);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $selectedProfs = $form->get('refProfesseur')->getData();
            $duplicates = [];
            foreach ($selectedProfs as $professeur) {
                $existing = $descriptionRepository->findOneBy([
                    'refPromo' => $promo,
                    'refProfesseur' => $professeur,
                ]);
                if ($existing) {
                    $duplicates[] = $professeur;
                }
            }

            if (!empty($duplicates)) {
                $names = array_map(fn($p) => $p->getNom() . ' ' . $p->getPrenom(), $duplicates);
                $this->addFlash('danger', 'Cette description existe déjà pour: ' . implode(', ', $names));
            }
        }

        if ($form->isSubmitted() && $form->isValid() && empty($duplicates)) {
            /** @var Professeur $professeur */
            foreach ($form->get("refProfesseur")->getData() as $professeur) {
                $descriptionClone = clone $description;
                $descriptionClone->setRefPromo($promo);
                $descriptionClone->setRefProfesseur($professeur);
                $entityManager->persist($descriptionClone);
            }
            $entityManager->flush();

            return $this->redirectToRoute('cal_summary', ['id' => $promo->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('description/new.html.twig', [
            'description' => $description,
            'promo' => $promo,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_description_show', methods: ['GET'])]
    public function show(Description $description): Response
    {
        return $this->render('description/show.html.twig', [
            'description' => $description,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_description_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Description $description, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DescriptionType::class, $description);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_description_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('description/edit.html.twig', [
            'description' => $description,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_description_delete', methods: ['POST'])]
    public function delete(Request $request, Description $description, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$description->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($description);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_description_index', [], Response::HTTP_SEE_OTHER);
    }
}
