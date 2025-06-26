<?php

namespace App\Controller\Public;

use App\Service\AnnouncementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class AnnouncementController extends AbstractController
{
    private AnnouncementService $announcementService;

    public function __construct(
        AnnouncementService $announcementService,
    ) {
        $this->announcementService = $announcementService;
    }

    /**
     * Renders the announcements fragment for initial page load.
     * This is called via `{{ render(controller(...)) }}` in base.html.twig.
     */
    public function announcementsFragment(): Response
    {
        $announcements = $this->announcementService->getAnnouncements();

        return $this->render('public/_announcements.html.twig', [
            'announcements' => $announcements,
        ]);
    }
}