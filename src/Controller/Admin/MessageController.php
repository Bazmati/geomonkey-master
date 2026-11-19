<?php

namespace App\Controller\Admin;

use App\Exception\MailboxUnavailableException;
use App\Repository\MailboxMessageRepository;
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
 * La liste lit le CACHE LOCAL (mailbox_messages, rempli par le cron
 * app:mailbox:sync) : la page est instantanée et ne fait aucun appel
 * IMAP. Le détail d'un message reste une lecture IMAP en direct.
 * La boîte continue d'être gérée dans le webmail GMX ; ici le bureau
 * lit, sans partager de mot de passe entre membres.
 */
#[IsGranted('ENTITY_MESSAGES')]
#[Route('/admin/message', name: 'app_admin_message_')]
class MessageController extends AbstractController
{
    public function __construct(
        private readonly MailboxService $mailbox,
        private readonly MailboxMessageRepository $cache,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('q', ''));
        $unreadOnly = $request->query->getBoolean('unread');

        // Boîte non configurée : même alerte de configuration qu'avant
        // (les tests kernel s'appuient dessus).
        if (!$this->mailbox->isConfigured()) {
            return $this->render('admin/message/index.html.twig', [
                'available' => false,
                'configured' => false,
                'error' => null,
                'items' => [],
                'total' => 0,
                'page' => 1,
                'lastPage' => 1,
                'perPage' => MailboxService::PER_PAGE,
                'q' => $search,
                'unreadOnly' => $unreadOnly,
                'lastSyncedAt' => null,
            ]);
        }

        // Lecture 100 % locale : PostgreSQL, zéro IMAP.
        $data = $this->cache->findPage($page, MailboxService::PER_PAGE, $search, $unreadOnly);

        // Vue « liste » (MessageSummary) : le template ne change pas.
        $items = array_map(static fn ($m) => $m->toSummary(), $data['items']);

        $lastPage = $data['total'] > 0
            ? (int) ceil($data['total'] / MailboxService::PER_PAGE)
            : 1;

        return $this->render('admin/message/index.html.twig', [
            'available' => true,
            'configured' => true,
            'error' => null,
            'items' => $items,
            'total' => $data['total'],
            'page' => $page,
            'lastPage' => $lastPage,
            'perPage' => MailboxService::PER_PAGE,
            'q' => $search,
            'unreadOnly' => $unreadOnly,
            'lastSyncedAt' => $this->cache->getLastSyncedAt(),
        ]);
    }

    #[Route('/{uid<\d+>}', name: 'show')]
    public function show(int $uid): Response
    {
        try {
            // Lecture directe IMAP : le corps du message n'est jamais mis
            // en cache (volumineux, et l'intérêt est la lecture à la demande).
            $message = $this->mailbox->getMessage($uid);
        } catch (MailboxUnavailableException $e) {
            $this->addFlash('error', 'Message introuvable ou boîte mail indisponible.');
            return $this->redirectToRoute('app_admin_message_index');
        }

        // Garder le cache local cohérent avec le \Seen posé côté serveur.
        try {
            $this->cache->markSeenByUid($uid);
        } catch (\Throwable $e) {
            // Le cache resterait marqué non lu : cosmétique, non bloquant.
            $this->logger->warning('Cache messagerie : markSeen impossible', ['uid' => $uid, 'error' => $e->getMessage()]);
        }

        return $this->render('admin/message/show.html.twig', [
            'message' => $message,
            'summary' => $this->mailbox->summarize($message),
        ]);
    }
}
