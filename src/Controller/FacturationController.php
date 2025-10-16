<?php

namespace App\Controller;

use App\Entity\Facturation;
use App\Entity\Repas;
use App\Form\FacturationType;
use App\Repository\FacturationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/facturation')]
final class FacturationController extends AbstractController
{
    /**
     * ✅ Liste toutes les facturations
     */
    #[Route(name: 'app_facturation_index', methods: ['GET'])]
    public function index(FacturationRepository $facturationRepository): Response
    {
        return $this->render('facturation/index.html.twig', [
            'facturations' => $facturationRepository->findAll(),
        ]);
    }

    /**
     * ✅ Création d’une nouvelle facturation
     */
    #[Route('/new', name: 'app_facturation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $facturation = new Facturation();
        $form = $this->createForm(FacturationType::class, $facturation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $professeur = $facturation->getRefProfesseur();
            $mois = $facturation->getMois();

            if ($professeur && $mois) {
                $repasRepo = $em->getRepository(Repas::class);

                // On récupère les repas du professeur pour le mois choisi
                $repas = $repasRepo->createQueryBuilder('r')
                    ->where('r.professeur = :prof')
                    ->andWhere('MONTH(r.date) = :mois')
                    ->setParameter('prof', $professeur)
                    ->setParameter('mois', $this->moisEnNombre($mois))
                    ->getQuery()
                    ->getResult();

                $nbRepas = count($repas);
                $prixUnitaire = $professeur->getPrixU() ?? 0;
                $montantTotal = $nbRepas * $prixUnitaire;

                // Mise à jour automatique des montants
                $facturation
                    ->setNbRepas($nbRepas)
                    ->setMontantTotal($montantTotal)
                    ->setMontantRegle(0)
                    ->setMontantRestant($montantTotal);
            }

            $em->persist($facturation);
            $em->flush();

            $this->addFlash('success', '✅ Facturation générée automatiquement à partir des repas.');
            return $this->redirectToRoute('app_facturation_index');
        }

        return $this->render('facturation/new.html.twig', [
            'facturation' => $facturation,
            'form' => $form,
        ]);
    }

    /**
     * ✅ Affiche le détail d’une facturation
     */
    #[Route('/{id}', name: 'app_facturation_show', methods: ['GET'])]
    public function show(Facturation $facturation): Response
    {
        return $this->render('facturation/show.html.twig', [
            'facturation' => $facturation,
        ]);
    }

    /**
     * ✅ Édite une facturation (recalcule automatiquement)
     */
    #[Route('/{id}/edit', name: 'app_facturation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Facturation $facturation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FacturationType::class, $facturation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $professeur = $facturation->getRefProfesseur();
            $mois = $facturation->getMois();

            if ($professeur && $mois) {
                $repasRepo = $em->getRepository(Repas::class);
                $repas = $repasRepo->createQueryBuilder('r')
                    ->where('r.professeur = :prof')
                    ->andWhere('MONTH(r.date) = :mois')
                    ->setParameter('prof', $professeur)
                    ->setParameter('mois', $this->moisEnNombre($mois))
                    ->getQuery()
                    ->getResult();

                $nbRepas = count($repas);
                $prixUnitaire = $professeur->getPrixU() ?? 0;
                $montantTotal = $nbRepas * $prixUnitaire;

                // Mise à jour automatique des valeurs calculées
                $facturation
                    ->setNbRepas($nbRepas)
                    ->setMontantTotal($montantTotal)
                    ->setMontantRestant($montantTotal - ($facturation->getMontantRegle() ?? 0));
            }

            $em->flush();

            $this->addFlash('success', '✅ Facturation mise à jour automatiquement.');
            return $this->redirectToRoute('app_facturation_index');
        }

        return $this->render('facturation/edit.html.twig', [
            'facturation' => $facturation,
            'form' => $form,
        ]);
    }

    /**
     * 🗑️ Supprime une facturation
     */
    #[Route('/{id}', name: 'app_facturation_delete', methods: ['POST'])]
    public function delete(Request $request, Facturation $facturation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $facturation->getId(), $request->getPayload()->getString('_token'))) {
            $em->remove($facturation);
            $em->flush();
        }

        return $this->redirectToRoute('app_facturation_index');
    }

    /**
     * 🔢 Convertit le nom d’un mois (ex: "janvier") en son numéro (1)
     */
    private function moisEnNombre(string $mois): int
    {
        $moisMap = [
            'janvier' => 1, 'février' => 2, 'mars' => 3, 'avril' => 4,
            'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8,
            'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12
        ];
        return $moisMap[strtolower(trim($mois))] ?? 0;
    }
}
