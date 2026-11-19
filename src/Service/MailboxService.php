<?php

namespace App\Service;

use App\Dto\MessageSummary;
use App\Exception\MailboxUnavailableException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;

/**
 * Consultation de la boîte mail de l'association (IMAP, lecture seule).
 *
 * Choix d'architecture (règle "bundle utile / code maison produit") :
 * le protocole IMAP est délégué à webklex/php-imap (pure PHP, aucune
 * extension serveur requise), MAIS l'application ne parle qu'au présent
 * service. Si la bibliothèque change un jour, un seul fichier bouge.
 *
 * Lecture seule assumée : on liste, on lit, on marque comme lu (\Seen).
 * Répondre, supprimer, déplacer : c'est le rôle du webmail GMX complet.
 *
 * Performance : la page de liste ne charge QUE les en-têtes
 * (setFetchBody(false)) — les corps ne sont récupérés que sur la page
 * de détail d'un message.
 */
final class MailboxService
{
    public const PER_PAGE = 10;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->username !== '' && $this->password !== '';
    }

    /**
     * Page de la boîte de réception (les plus récents d'abord).
     *
     * $search : filtre plein texte sur l'objet (recherche serveur IMAP).
     * $unreadOnly : uniquement les messages non lus.
     * $knownTotal : si le contrôleur connaît déjà le total (cache session),
     *               on saute la requête de comptage.
     *
     * @return array{items: MessageSummary[], total: int|null, page: int}
     */
    public function getInboxPage(int $page, string $search = '', bool $unreadOnly = false, ?int $knownTotal = null): array
    {
        $page = max(1, $page);
        $search = trim($search);
        $folder = $this->getInboxFolder();

        $total = $knownTotal;
        if ($total === null) {
            try {
                $total = (int) $this->makeQuery($folder, $search, $unreadOnly)->count();
            } catch (\Throwable) {
                // Comptage refusé par ce serveur : la liste reste utilisable,
                // seule la pagination « page X / Y » devient approximative.
                $total = null;
            }
        }

        try {
            $messages = $this->makeQuery($folder, $search, $unreadOnly)
                ->setFetchOrder('desc')
                // En-têtes seuls : la liste n'utilise ni corps ni pièces jointes.
                // C'est le principal gain de performance du module.
                ->setFetchBody(false)
                ->limit(self::PER_PAGE, $page)
                ->get();
        } catch (\Throwable $e) {
            throw new MailboxUnavailableException('Lecture de la boîte impossible : ' . $e->getMessage(), 0, $e);
        }

        $items = [];
        foreach ($messages as $message) {
            $items[] = $this->summarize($message);
        }

        return ['items' => $items, 'total' => $total, 'page' => $page];
    }

    /**
     * Un message précis, marqué comme lu au passage (\Seen).
     * Ici le corps EST chargé (c'est la page de lecture).
     */
    public function getMessage(int $uid): Message
    {
        $folder = $this->getInboxFolder();
        try {
            $message = $folder->messages()->getMessageByUid((int) $uid);
        } catch (\Throwable $e) {
            throw new MailboxUnavailableException('Message introuvable : '.$e->getMessage(), 0, $e);
        }

        try {
            // Lecture seule "presque" : marquer \Seen est le comportement
            // naturel d'un lecteur de messagerie et ne modifie rien d'autre.
            $message->setFlag('Seen');
        } catch (\Throwable) {
            // Le marquage échoue parfois selon les serveurs : non bloquant.
        }

        return $message;
    }

    /**
     * Construit une requête de listing qualifiée :
     * - recherche sur l'objet si $search est fourni ;
     * - sinon « ALL » (GMX, comme tout serveur strict, refuse un SEARCH
     *   sans critère) ;
     * - filtre non lus si demandé.
     */
    private function makeQuery(Folder $folder, string $search, bool $unreadOnly): WhereQuery
    {
        /** @var WhereQuery $query */
        $query = $search !== ''
            ? $folder->messages()->whereSubject($search)
            : $folder->messages()->whereAll();

        if ($unreadOnly) {
            $query = $query->whereUnseen();
        }

        return $query;
    }

    /**
     * Connexion + selection INBOX, avec échec propre en MailboxUnavailableException.
     */
    private function getInboxFolder(): Folder
    {
        if (!$this->isConfigured()) {
            throw new MailboxUnavailableException('La boîte mail n’est pas configurée (MAILBOX_* dans .env.local).');
        }

        try {
            /** @var Client $client */
            $client = (new ClientManager())->make([
                'host' => $this->host,
                'port' => $this->port,
                'encryption' => $this->encryption,
                'validate_cert' => true,
                'protocol' => 'imap',
                'username' => $this->username,
                'password' => $this->password,
            ]);
            $client->connect();

            $folder = $client->getFolder('INBOX');
            if (!$folder instanceof Folder) {
                throw new MailboxUnavailableException('Dossier INBOX introuvable sur le serveur.');
            }

            return $folder;
        } catch (MailboxUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new MailboxUnavailableException('Connexion à la boîte mail impossible : ' . $e->getMessage(), 0, $e);
        }
    }

    public function summarize(Message $message): MessageSummary
    {
        $from = '';
        try {
            $addresses = $message->getFrom();
            $first = $addresses?->first();
            if ($first !== null) {
                $label = trim((string) ($first->personal ?? ''));
                $mail = trim((string) ($first->mail ?? ''));
                $from = $label !== '' ? ($label . ' <' . $mail . '>') : $mail;
            }
        } catch (\Throwable) {
            // Entête From illisible : on affiche un libellé neutre plutôt
            // que de casser toute la liste pour un message malformé.
        }

        $seen = false;
        try {
            $seen = $message->getFlags()->has('seen');
        } catch (\Throwable) {
        }

        $subject = mb_decode_mimeheader((string) ($message->getSubject() ?? ''));
        $from = mb_decode_mimeheader($from);
        return new MessageSummary(
            uid: (int) $message->getUid(),
            from: $from !== '' ? $from : 'Expéditeur inconnu',
            subject: $subject !== '' ? $subject : '(sans objet)',
            date: $message->getDate()->first(),
            seen: $seen,
        );
    }
}
