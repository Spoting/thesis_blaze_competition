<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;



final class TestController extends AbstractController
{
    #[Route('/test/{id}', name: 'app_test',  requirements: ['id' => Requirement::DIGITS])]
    #[Route('/test_alias/{id}', name: 'app_alias_route')]
    public function index(int $id): JsonResponse
    {   
        return $this->json([
            'message' => 'Welcome to your new controller! --- ' . $id,
            'path' => 'src/Controller/TestController.php',
        ]);
    }


    #[Route('/test/{slug}', name: 'app_test_test')]
    public function show(string $slug): Response
    {
        // return $this->json([
        //     'message' => 'Welcome to your new controller! slagki --- ' . $slug,
        //     'path' => 'src/Controller/TestController.php',
        // ]);
        return $this->render('test.html.twig', [
            'body' => $slug,
        ]);
    }
}
