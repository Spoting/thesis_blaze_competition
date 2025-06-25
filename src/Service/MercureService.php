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

    // public function publishCompetitionUpdate(Competition $competition)
    // {
    //     $renderedHtml = $this->twig->render('public/_competition_item.html.twig', [
    //         'competition' => $competition,
    //     ]);
    //     $topic = sprintf('/competitions/%d', $competition->getId());
    //     $update = new Update(
    //         $topic,
    //         json_encode([
    //             'html' => $renderedHtml,
    //             'id' => $competition->getId(),
    //         ])
    //     );

    //     $this->hub->publish($update);
    // }

    /**
     * Publishes a Mercure update for a specific competition.
     *
     * @param Competition $competition The competition entity that has been updated.
     */
    public function publishCompetitionUpdate(Competition $competition): void
    {
        // Determine if the competition's new status is considered public
        // by checking against the PUBLIC_STATUSES constant in the Competition entity.
        $isPublic = in_array($competition->getStatus(), Competition::PUBLIC_STATUSES);

        $renderedHtml = null; // Initialize HTML to null. This indicates removal if the status is not public.

        if ($isPublic) {
            // If the competition is public, render its HTML content for the teaser card.
            // This partial is used for the list view, so 'showSubmitButton' is true.
            $renderedHtml = $this->twig->render('public/_competition_teaser.html.twig', [
                'competition' => $competition,
                'statusLabels' => Competition::STATUSES, // Pass all status labels from the entity
                'showSubmitButton' => true, // Teasers in the list should have the submit button
            ]);
        }

        // Define the general Mercure topic for all competition updates.
        // This topic must match what the frontend JavaScript is subscribing to.
        $topic = '/competitions';

        // Create the Mercure Update object.
        // The payload includes the updated HTML, the competition ID, its new status,
        // and the full list of status labels (for client-side consistency).
        $update = new Update(
            $topic,
            json_encode([
                'html' => $renderedHtml, // Will be null if the competition is no longer public
                'id' => $competition->getId(),
                'status' => $competition->getStatus(),
                'statusLabels' => Competition::STATUSES, // Important for JS to update messages/labels consistently
            ])
        );

        // Publish the update to the Mercure hub.
        $this->hub->publish($update);
    }
}
