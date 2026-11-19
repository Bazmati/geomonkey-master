<?php

namespace App\Controller\Admin;

use App\Exception\MailboxUnavailableException;
use App\Service\MailboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

/**
 * Module Messages — consultation de la boîte mail de l'association
 * (lecture seule) depuis le backoffice, pour le bureau.
 *
 * La boîte reste gérée normalement dans le webmail GMX ; ici le bureau
 * lit, sans partager de mot de passe entre membres.
 */
#[IsGranted('ENTITY_MESSAGES')]
#[Route('/admin/message', name: 'app_admin_message_')]
class MessageController extends AbstractController
{
    public function __construct(
        private readonly MailboxService $mailbox,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        try {
            $data = $this->mailbox->getInboxPage($page);
        } catch (MailboxUnavailableException $e) {
            return $this->render('admin/message/index.html.twig', [
                'available' => false,
                'configured' => $this->mailbox->isConfigured(),
                'error' => $e->getMessage(),
                'items' => [],
                'total' => 0,
                'page' => 1,
                'lastPage' => 1,
                'perPage' => MailboxService::PER_PAGE,
            ]);
        }

        return $this->render('admin/message/index.html.twig', [
            'available' => true,
            'configured' => true,
            'error' => null,
            'items' => $data['items'],
            'total' => $data['total'],
            'page' => $data['page'],
            'lastPage' => $data['total'] !== null
                ? max(1, (int) ceil($data['total'] / MailboxService::PER_PAGE))
                : null,
            'perPage' => MailboxService::PER_PAGE,
        ]);
    }

    #[Route('/{uid<\d+>}', name: 'show')]
    public function show(int $uid): Response
    {
        try {
            $message = $this->mailbox->getMessage($uid);
        } catch (MailboxUnavailableException $e) {
            $this->addFlash('error', 'Message introuvable ou boîte mail indisponible.');
            return $this->redirectToRoute('app_admin_message_index');
        }

        return $this->render('admin/message/show.html.twig', [
            'message' => $message,
            'summary' => $this->mailbox->summarize($message),
        ]);
    }
}
