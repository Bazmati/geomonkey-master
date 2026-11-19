<?php

namespace App\Controller\Admin;

use App\Exception\MailboxUnavailableException;
use App\Service\MailboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

/**
 * Module Messages — consultation de la boîte mail de l'association
 * (lecture seule) depuis le backoffice, pour le bureau.
 *
 * La boîte reste gérée normalement dans le webmail GMX ; ici le bureau
 * lit, sans partager de mot de passe entre membres.
 *
 * Le total de messages est mis en cache de session (5 min) tant qu'on
 * navigue sans filtre : une passe IMAP de moins par page vue.
 */
#[IsGranted('ENTITY_MESSAGES')]
#[Route('/admin/message', name: 'app_admin_message_')]
class MessageController extends AbstractController
{
    private const TOTAL_CACHE_KEY = 'admin_message_inbox_total';
    private const TOTAL_CACHE_TTL = 300; // 5 minutes

    public function __construct(
        private readonly MailboxService $mailbox,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('q', ''));
        $unreadOnly = $request->query->getBoolean('unread');

        // Navigation « simple » (pas de filtre) : le total peut venir du cache
        $isSimpleList = $search === '' && !$unreadOnly;

        $session = $request->hasSession() ? $request->getSession() : null;
        $knownTotal = null;
        if ($isSimpleList && $session !== null) {
            $cached = $session->get(self::TOTAL_CACHE_KEY);
            if (\is_array($cached) && ($cached['ts'] ?? 0) > (time() - self::TOTAL_CACHE_TTL)) {
                $knownTotal = (int) $cached['total'];
            }
        }

        try {
            $data = $this->mailbox->getInboxPage($page, $search, $unreadOnly, $knownTotal);
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
                'q' => $search,
                'unreadOnly' => $unreadOnly,
            ]);
        }

        // Rafraîchir le cache de comptage quand le service vient de compter
        if ($isSimpleList && $knownTotal === null && $session !== null && $data['total'] !== null) {
            $session->set(self::TOTAL_CACHE_KEY, ['total' => $data['total'], 'ts' => time()]);
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
            'q' => $search,
            'unreadOnly' => $unreadOnly,
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
