<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DescriptionController extends AbstractController
{
    #[Route('/description', name: 'app_description')]
    public function index(): Response
    {
        return $this->render('description/index.html.twig', [
            'controller_name' => 'DescriptionController',
        ]);
    }
}
