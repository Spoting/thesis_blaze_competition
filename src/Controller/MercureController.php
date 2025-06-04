<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;

class MercureController extends AbstractController
{
    #[Route('/publish-update', name: 'app_publish_update')]
    public function publish(HubInterface $hub): Response
    {
        $update = new Update(
            'http://example.com/books/1', // The topic URL
            json_encode(['status' => 'out of stock']) // The data to publish
        );

        $hub->publish($update);

        return new Response('Update published!');
    }
}