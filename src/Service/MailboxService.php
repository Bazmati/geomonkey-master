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
 * Performance : la page de liste ne fait PLUS d'IMAP du tout — elle lit
 * le cache local rempli par MailboxSynchronizer (app:mailbox:sync).
 * Ce service ne sert plus qu'à :
 *   - la synchronisation (fetchHeadersChunk) ;
 *   - la lecture d'un message précis (getMessage).
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
     * Chunk d'en-têtes pour la synchronisation : les plus récents d'abord,
     * SANS les corps (setFetchBody(false)) — la synchro ne stocke jamais
     * de corps, jamais de pièces jointes.
     *
     * @return list<Message>
     */
    public function fetchHeadersChunk(int $page, int $size): array
    {
        $page = max(1, $page);
        $folder = $this->getInboxFolder();

        try {
            $collection = $folder->messages()
                ->whereAll()
                ->setFetchOrder('desc')
                ->setFetchBody(false)
                ->limit($size, $page)
                ->get();
        } catch (\Throwable $e) {
            throw new MailboxUnavailableException('Lecture de la boîte impossible : ' . $e->getMessage(), 0, $e);
        }

        $messages = [];
        foreach ($collection as $message) {
            $messages[] = $message;
        }

        return $messages;
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
