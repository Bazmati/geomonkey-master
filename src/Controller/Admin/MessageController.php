<?php

namespace App\Controller\Admin;

use App\Exception\MailboxUnavailableException;
use App\Repository\MailboxMessageRepository;
use App\Service\MailboxSendService;
use App\Service\MailboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

/**
 * Module Messages — boîte mail de l'association dans le backoffice.
 *
 * La liste lit le CACHE LOCAL (mailbox_messages, rempli par le cron
 * app:mailbox:sync) : la page est instantanée et ne fait aucun appel
 * IMAP. Le détail d'un message reste une lecture IMAP en direct.
 *
 * Répondre / écrire : envoi SMTP (MAILER_DSN, From = MAILBOX_USER)
 * avec archivage automatique dans le dossier « Envoyés » de GMX :
 * le bureau peut tout faire depuis le backoffice sans partage de
 * mot de passe, et l'historique reste complet dans le webmail.
 */
#[IsGranted('ENTITY_MESSAGES')]
#[Route('/admin/message', name: 'app_admin_message_')]
class MessageController extends AbstractController
{
    private const MAX_BODY_LENGTH = 100000;

    public function __construct(
        private readonly MailboxService $mailbox,
        private readonly MailboxSendService $sender,
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

    /**
     * Écrire / répondre : formulaire (GET) puis envoi (POST).
     * Les composants du message reviennent préremplis en cas d'erreur.
     */
    #[Route('/compose', name: 'compose', methods: ['GET', 'POST'])]
    public function compose(Request $request): Response
    {
        if (!$this->mailbox->isConfigured()) {
            $this->addFlash('error', 'La boîte mail n’est pas configurée (MAILBOX_* dans .env.local).');
            return $this->redirectToRoute('app_admin_message_index');
        }

        $to = trim((string) $request->query->get('to', ''));
        $subject = trim((string) $request->query->get('subject', ''));
        $body = '';
        $error = null;

        if ($request->isMethod('POST')) {
            $to = trim((string) $request->request->get('to', ''));
            $subject = trim((string) $request->request->get('subject', ''));
            $body = trim((string) $request->request->get('body', ''));
            $token = (string) $request->request->get('_csrf_token', '');

            if (!$this->isCsrfTokenValid('mailbox_send', $token)) {
                $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');
                return $this->redirectToRoute('app_admin_message_compose', ['to' => $to, 'subject' => $subject]);
            }

            // Destinataires : une ou plusieurs adresses séparées par des virgules.
            $recipients = array_values(array_filter(
                array_map('trim', explode(',', $to)),
                static fn (string $r) => $r !== ''
            ));

            if ($recipients === []) {
                $error = 'Indique au moins un destinataire.';
            } elseif (array_filter($recipients, static fn (string $r) => filter_var($r, FILTER_VALIDATE_EMAIL) === false)) {
                $error = 'Une des adresses n’est pas valide : vérifie la saisie (sépare par des virgules).';
            } elseif ($subject === '') {
                $error = 'L’objet est obligatoire.';
            } elseif ($body === '') {
                $error = 'Le message est vide.';
            } elseif (\strlen($body) > self::MAX_BODY_LENGTH) {
                $error = 'Message trop long : envoie-le en plusieurs fois.';
            }

            if ($error === null) {
                try {
                    $archived = $this->sender->send($recipients, $subject, $body);

                    if ($archived) {
                        $this->addFlash('success', sprintf('Message envoyé à %s et archivé dans « Envoyés ».', implode(', ', $recipients)));
                    } else {
                        $this->addFlash('success', sprintf('Message envoyé à %s.', implode(', ', $recipients)));
                        $this->addFlash('warning', 'La copie dans « Envoyés » a échoué (voir les logs) : l’envoi lui-même a bien abouti.');
                    }

                    return $this->redirectToRoute('app_admin_message_index');
                } catch (TransportExceptionInterface $e) {
                    // Erreur SMTP : le brouillon reste affiché, rien n'est perdu.
                    $error = 'Échec de l’envoi SMTP : ' . $e->getMessage();
                    $this->logger->error('Envoi messagerie impossible', ['to' => $to, 'error' => $e->getMessage()]);
                }
            }
        }

        return $this->render('admin/message/compose.html.twig', [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'error' => $error,
            'fromAddress' => $this->mailbox->getFromAddress(),
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

        // Adresse de réponse propre (sans le libellé « Nom <...> »).
        $replyTo = null;
        try {
            $first = $message->getFrom()?->first();
            if ($first !== null) {
                $mail = trim((string) ($first->mail ?? ''));
                $replyTo = $mail !== '' ? $mail : null;
            }
        } catch (\Throwable) {
            // From illisible : pas de bouton Répondre, c'est tout.
        }

        return $this->render('admin/message/show.html.twig', [
            'message' => $message,
            'summary' => $this->mailbox->summarize($message),
            'replyTo' => $replyTo,
        ]);
    }
}
