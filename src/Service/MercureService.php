<?php

namespace App\Service;

use App\Entity\Competition;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

class MercureService
{
    private HubInterface $hub;
    private Environment $twig;

    public function __construct(
        HubInterface $hub,
        Environment $twig
    ) {
        $this->hub = $hub;
        $this->twig = $twig;
    }

    public function publishCompetitionUpdate(Competition $competition)
    {
        $renderedHtml = $this->twig->render('public/_competition_item.html.twig', [
            'competition' => $competition,
        ]);
        $topic = sprintf('/competitions/%d', $competition->getId());
        $update = new Update(
            $topic,
            json_encode([
                'html' => $renderedHtml,
                'id' => $competition->getId(),
            ])
        );

        $this->hub->publish($update);
    }
}
