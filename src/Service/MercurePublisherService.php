<?php

namespace App\Service;

use App\Entity\Competition;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

class MercurePublisherService
{

    public const ANNOUNCEMENT_TOPIC = '/global_announcements';
    public const COMPETITIONS_TOPIC = '/competitions';

    private HubInterface $hub;
    private Environment $twig;

    public function __construct(
        HubInterface $hub,
        Environment $twig
    ) {
        $this->hub = $hub;
        $this->twig = $twig;
    }


    public function publishAnnouncement(string $status, string $message): void
    {

        $topic = self::ANNOUNCEMENT_TOPIC;
        $update = new Update(
            $topic, // The topic(s) to publish to
            json_encode([
                'type' => 'new_announcement', // A custom identifier for the JS
                'status' => $status,
                'message' => $message,
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            ]),
        );

        $this->hub->publish($update);
    }
    
    /**
     * Publishes a Mercure update for a specific competition.
     *
     * @param Competition $competition The competition entity that has been updated.
     */
    public function publishCompetitionUpdate(Competition $competition): void
    {
        $isPublic = in_array($competition->getStatus(), Competition::PUBLIC_STATUSES);

        $renderedHtml = null;

        if ($isPublic) {
            $renderedHtml = $this->twig->render('public/_competition_teaser.html.twig', [
                'competition' => $competition,
                'statusLabels' => Competition::STATUSES, 
                'showSubmitButton' => true,
            ]);
        }

        $topic = self::COMPETITIONS_TOPIC;

        $update = new Update(
            $topic,
            json_encode([
                'html' => $renderedHtml,
                'id' => $competition->getId(),
                'status' => $competition->getStatus(),
                'statusLabels' => Competition::STATUSES,
            ])
        );

        $this->hub->publish($update);
    }
}
