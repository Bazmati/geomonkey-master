<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoi d'emails depuis la boîte de l'asso (module Messages du backoffice).
 *
 * Le transport SMTP est celui du MAILER_DSN (GMX, mail.gmx.net:587),
 * le From est exactement l'adresse authentifiée (geomonkey@gmx.fr via
 * MAILBOX_USER) : GMX refuse tout autre From.
 *
 * Emails en deux parties : texte brut (repli universel) + HTML avec la
 * bannière de signature aux couleurs du site (mêmes couleurs que la
 * signature du webmail GMX, pour une identité cohérente).
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
            ->text($body . self::SIGNATURE)
            ->html($this->buildHtmlBody($body));

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

    /**
     * Corps HTML : message de l'utilisateur (échappé) + bannière de signature.
     * Tables + styles inline uniquement : compatibilité Gmail/Outlook/Webmail.
     */
    private function buildHtmlBody(string $body): string
    {
        $escaped = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));

        return <<<HTML
        <!DOCTYPE html>
        <html lang="fr">
        <body style="margin:0;padding:0;background-color:#F4F4F2;">
        <div style="max-width:560px;margin:0 auto;padding:24px 16px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:#1C1C1A;">
            <div style="background-color:#FFFFFF;border:1px solid #E4E4E0;border-radius:6px;padding:20px;">
                <p style="margin:0 0 16px;">{$escaped}</p>
            </div>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin-top:16px;border-top:3px solid #00A040;">
                <tr>
                    <td style="background-color:#1C1C1A;padding:20px 24px;border-radius:0 0 6px 6px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td style="font-size:20px;font-weight:bold;color:#EDEDE8;letter-spacing:0.5px;">
                                    Géo<span style="color:#00A040;">Monkey</span>&nbsp;🐒
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-top:6px;font-size:12px;color:#B8B8B2;">Association loi 1901</td>
                            </tr>
                            <tr>
                                <td style="padding-top:12px;font-size:13px;">
                                    <a href="https://www.geomonkey.fr" style="color:#00A040;text-decoration:none;font-weight:bold;">www.geomonkey.fr</a>
                                    <span style="color:#B8B8B2;">&nbsp;&middot;&nbsp;</span>
                                    <a href="mailto:geomonkey@gmx.fr" style="color:#EDEDE8;text-decoration:none;">geomonkey@gmx.fr</a>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding-top:14px;">
                                    <span style="display:inline-block;width:40px;height:4px;background-color:#E0C020;border-radius:2px;"></span>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
        </body>
        </html>
        HTML;
    }
}