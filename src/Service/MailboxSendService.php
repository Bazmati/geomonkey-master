<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoi d'emails depuis la boîte de l'asso (module Messages du backoffice).
 *
 * Le transport SMTP est celui du MAILER_DSN (GMX, mail.gmx.com:465),
 * le From est exactement l'adresse authentifiée (geomonkey@gmx.fr via
 * MAILBOX_USER) : GMX refuse tout autre From.
 *
 * Après un envoi réussi, une copie est archivée dans le dossier
 * « Envoyés » de la boîte IMAP : le bureau garde l'historique complet
 * dans le webmail GMX. L'archive ne fait jamais échouer l'envoi.
 */
final class MailboxSendService
{
    private const SIGNATURE = "\n\n-- \nL'équipe GéoMonkey · https://www.geomonkey.fr";

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MailboxService $mailbox,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Envoie un email et l'archive dans « Envoyés ».
     *
     * @param list<string> $recipients adresses valides (une ou plusieurs)
     *
     * @return bool true si la copie a bien été archivée dans Envoyés
     *
     * @throws TransportExceptionInterface si le SMTP refuse l'envoi
     */
    public function send(array $recipients, string $subject, string $body): bool
    {
        $email = (new Email())
            ->from($this->mailbox->getFromAddress())
            ->to(...$recipients)
            ->subject($subject)
            ->text($body . self::SIGNATURE);

        // Envoi SMTP : echec -> TransportException remonte à l'appelant
        // (le contrôleur affiche une erreur et ne perd pas le brouillon).
        $this->mailer->send($email);

        // Archive dans le dossier « Envoyés » de GMX (non bloquant).
        $archived = $this->mailbox->appendToSent($email->toString());

        if (!$archived) {
            $this->logger->warning('Message envoyé mais non archivé dans Envoyés', [
                'to' => implode(', ', $recipients),
                'subject' => $subject,
            ]);
        }

        return $archived;
    }
}
