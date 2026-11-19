<?php

namespace App\Service;

use App\Entity\MailboxMessage;
use App\Exception\MailboxUnavailableException;
use App\Repository\MailboxMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Webklex\PHPIMAP\Message;

/**
 * Synchronise les en-têtes de la boîte IMAP vers le cache local
 * (table mailbox_messages), pour que la page Messages du backoffice
 * ne fasse plus aucun appel IMAP.
 *
 * Appelé par app:mailbox:sync (cron recommandé : toutes les 5 minutes).
 *
 * Stratégie :
 * - incrémental (défaut) : on parcourt la boîte par chunks les plus récents
 *   d'abord, et on s'arrête dès qu'un chunk ne ramène AUCUN message inconnu :
 *   on a rejoint la profondeur déjà synchronisée. Les toggles lu/non lu des
 *   messages récents sont rafraîchis au passage.
 * - --full : on parcourt toute la boîte (première fois, ou rattrapage).
 * Les uids IMAP étant uniques et stables, l'upsert est idempotent.
 */
final class MailboxSynchronizer
{
    public const DEFAULT_CHUNK_SIZE = 500;

    public function __construct(
        private readonly MailboxService $mailbox,
        private readonly MailboxMessageRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return array{created: int, updated: int}
     */
    public function synchronize(bool $full = false, ?callable $onChunk = null): array
    {
        if (!$this->mailbox->isConfigured()) {
            throw new MailboxUnavailableException('La boîte mail n’est pas configurée (MAILBOX_* dans .env.local).');
        }

        $created = 0;
        $updated = 0;
        $page = 1;

        while (true) {
            $messages = $this->mailbox->fetchHeadersChunk($page, self::DEFAULT_CHUNK_SIZE);
            if ($messages === []) {
                break;
            }

            $uids = array_map(static fn (Message $m) => (int) $m->getUid(), $messages);
            $existing = array_flip($this->repository->findExistingUids($uids));

            $newInChunk = 0;
            $now = new \DateTimeImmutable();

            foreach ($messages as $message) {
                $uid = (int) $message->getUid();
                $entity = $this->repository->findOneByUid($uid);

                if ($entity === null) {
                    $entity = (new MailboxMessage())->setUid($uid);
                    $this->em->persist($entity);
                    ++$created;
                    ++$newInChunk;
                } else {
                    ++$updated;
                }

                [$fromName, $fromEmail] = $this->parseFrom($message);
                $entity
                    ->setFromName($fromName)
                    ->setFromEmail($fromEmail)
                    ->setSubject(mb_decode_mimeheader((string) ($message->getSubject() ?? '')))
                    ->setSentAt($this->parseDate($message))
                    ->setSeen($this->parseSeen($message))
                    ->setSyncedAt($now);
            }

            $this->em->flush();
            $this->em->clear();

            if ($onChunk !== null) {
                $onChunk($page, \count($messages), $created, $updated);
            }

            // Fin naturelle de la boîte
            if (\count($messages) < self::DEFAULT_CHUNK_SIZE) {
                break;
            }

            // Incrémental : ce chunk était entièrement déjà connu -> profondeur
            // de synchro atteinte, inutile de continuer.
            if (!$full && $newInChunk === 0) {
                break;
            }

            ++$page;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @return array{0: ?string, 1: ?string}  [nom affiché, adresse email]
     */
    private function parseFrom(Message $message): array
    {
        try {
            $first = $message->getFrom()?->first();
            if ($first !== null) {
                $name = mb_decode_mimeheader(trim((string) ($first->personal ?? '')));
                $mail = trim((string) ($first->mail ?? ''));

                return [
                    $name !== '' ? $name : null,
                    $mail !== '' ? $mail : null,
                ];
            }
        } catch (\Throwable) {
            // Entête From illisible : colonnes null plutôt qu'une synchro en échec.
        }

        return [null, null];
    }

    private function parseDate(Message $message): ?\DateTimeImmutable
    {
        try {
            $date = $message->getDate()->first();

            return $date instanceof \DateTimeImmutable ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseSeen(Message $message): bool
    {
        try {
            return $message->getFlags()->has('seen');
        } catch (\Throwable) {
            return false;
        }
    }
}
