<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        // Données statique pour l'instant (à remplacer par des données réelles plus tard)
        $stats = [
            'events' => 0,
            'projects' => 0,
            'users' => 0,
        ];

        $upcomingEvents = []; // Vide pour l'instant

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}
