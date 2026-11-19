<?php

namespace App\Entity;

use App\Dto\MessageSummary;
use App\Repository\MailboxMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cache local d'un en-tête de message de la boîte IMAP de l'asso.
 *
 * Rempli par app:mailbox:sync (cron), lu par la page Messages :
 * la navigation du backoffice ne fait plus d'IMAP du tout, la boîte
 * est juste « rafraîchie » périodiquement en tâche de fond.
 *
 * Seuls les en-têtes sont stockés (jamais les corps ni les pièces
 * jointes) : pas de données personnelles lourdes dans la base.
 */
#[ORM\Entity(repositoryClass: MailboxMessageRepository::class)]
#[ORM\Table(name: 'mailbox_messages')]
#[ORM\UniqueConstraint(name: 'uniq_mailbox_message_uid', columns: ['uid'])]
#[ORM\Index(name: 'idx_mailbox_message_sent_at', columns: ['sent_at'])]
class MailboxMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $uid;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromEmail = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $subject = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $seen = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $syncedAt;

    public function __construct()
    {
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function setUid(int $uid): self
    {
        $this->uid = $uid;
        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): self
    {
        $this->fromName = $fromName;
        return $this;
    }

    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(?string $fromEmail): self
    {
        $this->fromEmail = $fromEmail;
        return $this;
    }

    /**
     * Libellé « Nom <email> » affiché dans la colonne De.
     */
    public function getFromLabel(): string
    {
        if ($this->fromName !== null && $this->fromName !== ''
            && $this->fromEmail !== null && $this->fromEmail !== '') {
            return $this->fromName . ' <' . $this->fromEmail . '>';
        }

        return $this->fromEmail
            ?? $this->fromName
            ?? 'Expéditeur inconnu';
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function isSeen(): bool
    {
        return $this->seen;
    }

    public function setSeen(bool $seen): self
    {
        $this->seen = $seen;
        return $this;
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): self
    {
        $this->syncedAt = $syncedAt;
        return $this;
    }

    /**
     * Vue « liste » consommée par le template (même forme que l'IMAP direct).
     */
    public function toSummary(): MessageSummary
    {
        return new MessageSummary(
            uid: $this->uid,
            from: $this->getFromLabel() !== '' ? $this->getFromLabel() : 'Expéditeur inconnu',
            subject: $this->subject !== '' ? $this->subject : '(sans objet)',
            date: $this->sentAt,
            seen: $this->seen,
        );
    }
}
