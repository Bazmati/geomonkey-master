<?php

namespace App\Service;

use App\Entity\Donation;
use App\Entity\NewsletterSubscriber;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service pour l'envoi des emails transactionnels liés à l'adhésion
 * 
 * FLUX COMPLET DES EMAILS :
 * 
 * M1 - Inscription
 *   Déclencheur: Utilisateur s'inscrit
 *   Destinataire: L'utilisateur
 *   Contenu: Confirmation de réception (2 variantes: visiteur/actif)
 * 
 * M2 - Inscription « membre actif »
 *   Déclencheur: Utilisateur choisit "membre_actif"
 *   Destinataire: CONTACT_EMAIL (ex: geomonkey@gmx.fr)
 *   Contenu: Alerte "X souhaite rejoindre l'asso" + bouton vers /admin/membership/requests
 * 
 * M3 - Validation bureau
 *   Déclencheur: Bureau valide la demande
 *   Destinataire: L'adhérent
 *   Contenu: "Ta demande est acceptée ✅" + bouton de paiement Stripe
 * 
 * M4 - Webhook checkout.session.completed
 *   Déclencheur: Paiement Stripe réussi
 *   Destinataire: L'adhérent
 *   Contenu: Félicitations + ce qu'il peut faire + invitation WhatsApp + mention assurance
 * 
 * M5 - Cron rappel
 *   Déclencheur: Expiration sous 30 jours
 *   Destinataire: L'adhérent
 *   Contenu: Expiration sous 30 jours + lien de renouvellement
 * 
 * M6 - Vérification email
 *   Déclencheur: Inscription
 *   Destinataire: L'utilisateur
 *   Contenu: Lien de vérification (24h)
 * 
 * M7 - Reset mot de passe
 *   Déclencheur: Demande de réinitialisation
 *   Destinataire: L'utilisateur
 *   Contenu: Lien de reset (1h)
 * 
 * M8 - Webhook don checkout.session.completed
 *   Déclencheur: Don Stripe réussi (metadata type=donation)
 *   Destinataire: Le donateur (si email fourni)
 *   Contenu: Remerciement + accusé de réception du don
 * 
 * M9 - Alerte trésorerie don
 *   Déclencheur: Don Stripe réussi (metadata type=donation)
 *   Destinataire: CONTACT_EMAIL
 *   Contenu: Alerte "Don de X€ reçu" (suivi comptable)
 */
class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $contactEmail,
        private ?string $whatsappGroupLink = null,
        private ?string $assuranceDetails = null,
        private string $fromAddress = 'noreply@geomonkey.fr'
    ) {}

    /**
     * Logo public de l'asso (même fichier que la navbar), en URL absolue :
     * un email ne peut pas charger un asset local.
     */
    private const string LOGO_URL = 'https://www.geomonkey.fr/images/Logo_geomonkey.png';
    private const string SITE_URL = 'https://www.geomonkey.fr';

    /**
     * Gabarit commun des emails transactionnels, dans l'identité visuelle du
     * site (fond anthracite #1C1C1A, carte #242422, accents jaune #E0C020),
     * cohérent avec les emails newsletter (M10/M11). Les webmails imposant
     * le CSS inline, tout est en style="..." et en tableau d'anciennes
     * générations : c'est voulu, ne pas "moderniser".
     */
    private function wrapEmailContent(string $title, string $innerHtml): string
    {

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#1C1C1A;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#1C1C1A;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#242422;border-radius:14px;padding:32px;color:#ffffff;">
        <tr><td align="center" style="padding-bottom:8px;">
          <img src="%%LOGO%%" alt="Logo GéoMonkey" width="64" height="64" style="display:block;border-radius:50%;" />
        </td></tr>
        <tr><td align="center" style="padding-bottom:4px;font-size:18px;font-weight:bold;color:#ffffff;">
          G&#233;o<span style="color:#E0C020;">Monkey</span>
        </td></tr>
        <tr><td align="center" style="padding-bottom:24px;">
          <h1 style="margin:0;font-size:20px;line-height:1.4;color:#ffffff;">%%TITLE%%</h1>
        </td></tr>
        <tr><td style="line-height:1.6;color:#cccccc;font-size:15px;">
          %%INNER%%
        </td></tr>
        <tr><td style="padding-top:24px;border-top:1px solid #3a3a38;color:#999999;font-size:12px;line-height:1.6;">
          L&#8217;&#233;quipe G&#233;oMonkey &#183; <a href="%%SITE%%" style="color:#E0C020;text-decoration:none;">www.geomonkey.fr</a>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

        return str_replace(
            ['%%LOGO%%', '%%TITLE%%', '%%INNER%%', '%%SITE%%'],
            [self::LOGO_URL, $title, $innerHtml, self::SITE_URL],
            $html
        );
    }

    /**
     * Bouton d'action stylé (vert GéoMonkey), pour les CTA des emails.
     */
    private function emailButton(string $url, string $label): string
    {
        return sprintf(
            '<div style="text-align:center;margin:20px 0;"><a href="%s" style="display:inline-block;padding:12px 24px;background:#00A040;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">%s</a></div>',
            htmlspecialchars($url),
            $label
        );
    }

    /**
     * M1: Email de confirmation de réception de l'inscription
     */
    public function sendRegistrationConfirmation(User $user): void
    {
        $subject = $user->needsBureauValidation() 
            ? 'Ta demande d\'adhésion à GéoMonkey est reçue !' 
            : 'Ton inscription à GéoMonkey est confirmée !';

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject($subject)
            ->html($this->renderRegistrationConfirmationEmail($user));

        $this->mailer->send($email);
    }

    /**
     * M2: Alerte au bureau pour une nouvelle demande d'adhésion
     */
    public function sendMembershipRequestAlert(User $user): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($this->contactEmail)
            ->subject(sprintf('Nouvelle demande d\'adhésion : %s', $user->getEmail()))
            ->html($this->renderMembershipRequestAlertEmail($user));

        $this->mailer->send($email);
    }

    /**
     * M3: Email de validation de la demande par le bureau
     */
    public function sendValidationEmail(User $user, string $paymentUrl): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject('Ta demande d\'adhésion est acceptée ✅')
            ->html($this->renderValidationEmail($user, $paymentUrl));

        $this->mailer->send($email);
    }

    /**
     * M4: Email de confirmation après paiement réussi
     */
    public function sendMembershipConfirmedEmail(User $user): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject('Bienvenue officiellement chez GéoMonkey ! 🎉')
            ->html($this->renderMembershipConfirmedEmail($user));

        $this->mailer->send($email);
    }

    /**
     * M5: Email de rappel d'expiration
     */
    public function sendExpirationReminderEmail(User $user, int $daysUntilExpiry): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject(sprintf('Ton adhésion GéoMonkey expire dans %d jours', $daysUntilExpiry))
            ->html($this->renderExpirationReminderEmail($user, $daysUntilExpiry));

        $this->mailer->send($email);
    }

    /**
     * M8: Email de remerciement après un don
     */
    public function sendDonationThankYouEmail(Donation $donation): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($donation->getDonorEmail())
            ->subject('Merci pour ton don à GéoMonkey ! 🙏')
            ->html($this->renderDonationThankYouEmail($donation));

        $this->mailer->send($email);
    }

    /**
     * M9: Alerte trésorerie pour un don reçu
     */
    public function sendDonationAlert(Donation $donation): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($this->contactEmail)
            ->subject(sprintf('🎁 Don de %.2f€ reçu', $donation->getAmountInEuros()))
            ->html($this->renderDonationAlertEmail($donation));

        $this->mailer->send($email);
    }

    // =========================================================================
    // RENDU DES TEMPLATES EMAIL
    // =========================================================================

    /**
     * Rend le template M1: Confirmation d'inscription
     */
    private function renderRegistrationConfirmationEmail(User $user): string
    {
        $needsValidation = $user->needsBureauValidation();

        $title = $needsValidation ? 'Demande d&#8217;adh&#233;sion re&#231;ue !' : 'Inscription confirm&#233;e !';

        if ($needsValidation) {
            $inner = '<p style="margin:0 0 12px;">Merci pour ton int&#233;r&#234;t &#224; rejoindre G&#233;oMonkey en tant que membre actif.</p>';
            $inner .= '<p style="margin:0 0 12px;">Notre bureau va examiner ta demande et te contactera dans les plus brefs d&#233;lais pour validation.</p>';
            $inner .= '<p style="margin:0 0 12px;"><strong>Une fois valid&#233;e, tu recevras un lien pour effectuer le paiement de ton adh&#233;sion.</strong></p>';
        } else {
            $inner = '<p style="margin:0 0 12px;">Merci de t&#8217;&#234;tre inscrit en tant que visiteur sur G&#233;oMonkey.</p>';
            $inner .= '<p style="margin:0 0 12px;">Tu as maintenant acc&#232;s &#224; tous les contenus publics de notre association.</p>';
            $inner .= '<p style="margin:0 0 12px;">Pour devenir membre actif avec acc&#232;s complet, tu peux faire une demande d&#8217;adh&#233;sion payante depuis ton espace.</p>';
        }

        return $this->wrapEmailContent($title, $inner);
    }

    /**
     * Rend le template M2: Alerte au bureau
     */
    private function renderMembershipRequestAlertEmail(User $user): string
    {
        $adminUrl = $this->urlGenerator->generate('app_admin_membership_requests', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $inner = '<p style="margin:0 0 12px;"><strong>Email :</strong> ' . htmlspecialchars($user->getEmail()) . '</p>';
        $inner .= '<p style="margin:0 0 12px;"><strong>Nom :</strong> ' . htmlspecialchars($user->getLastName() . ' ' . $user->getFirstName()) . '</p>';
        $inner .= '<p style="margin:0 0 12px;"><strong>Date :</strong> ' . (new \DateTime())->format('d/m/Y H:i') . '</p>';
        $inner .= $this->emailButton($adminUrl, 'Valider la demande');
        $inner .= '<p style="margin:0;color:#999999;">Merci de valider cette demande dans les plus brefs d&#233;lais.</p>';

        return $this->wrapEmailContent('&#9888;&#65039; Nouvelle demande d&#8217;adh&#233;sion', $inner);
    }

    /**
     * Rend le template M3: Validation par le bureau
     */
    private function renderValidationEmail(User $user, string $paymentUrl): string
    {
        $inner = '<p style="margin:0 0 12px;">F&#233;licitations ' . htmlspecialchars($user->getFirstName()) . ' !</p>';
        $inner .= '<p style="margin:0 0 12px;">Le bureau de G&#233;oMonkey a valid&#233; ta demande d&#8217;adh&#233;sion en tant que membre actif.</p>';
        $inner .= '<p style="margin:0 0 12px;">Pour finaliser ton adh&#233;sion, il ne te reste plus qu&#8217;&#224; effectuer le paiement :</p>';
        $inner .= $this->emailButton($paymentUrl, 'Payer mon adh&#233;sion');
        $inner .= '<p style="margin:0;color:#999999;"><small>Le montant de l&#8217;adh&#233;sion annuelle a &#233;t&#233; vot&#233; en Assembl&#233;e G&#233;n&#233;rale.</small></p>';

        return $this->wrapEmailContent('&#9989; Ta demande est accept&#233;e !', $inner);
    }

    /**
     * Rend le template M4: Confirmation après paiement
     */
    private function renderMembershipConfirmedEmail(User $user): string
    {
        $whatsappText = $this->whatsappGroupLink 
            ? '<p style="margin:0 0 12px;">Rejoins notre groupe WhatsApp pour rester inform&#233; des &#233;v&#233;nements : <a href="' . $this->whatsappGroupLink . '" style="color:#E0C020;">Rejoindre le groupe</a></p>'
            : '';
        
        $assuranceText = $this->assuranceDetails
            ? '<p style="margin:0 0 12px;"><strong>Assurance :</strong> ' . htmlspecialchars($this->assuranceDetails) . '</p>'
            : '<p style="margin:0 0 12px;"><strong>Assurance :</strong> Ton adh&#233;sion inclut une assurance responsabilit&#233; civile et individuelle accident souscrite par l&#8217;association.</p>';

        $inner = '<p style="margin:0 0 12px;">Ton adh&#233;sion a &#233;t&#233; confirm&#233;e avec succ&#232;s.</p>';
        $inner .= '<p style="margin:16px 0 8px;font-weight:bold;color:#ffffff;">Ce que tu peux faire maintenant :</p>';
        $inner .= '<ul style="margin:0 0 12px;padding-left:20px;">';
        $inner .= '<li style="margin-bottom:6px;">Acc&#233;der &#224; tous les contenus r&#233;serv&#233;s aux membres</li>';
        $inner .= '<li style="margin-bottom:6px;">Participer &#224; tous nos &#233;v&#233;nements</li>';
        $inner .= '<li>Voter lors des Assembl&#233;es G&#233;n&#233;rales</li>';
        $inner .= '</ul>';
        $inner .= $whatsappText;
        $inner .= $assuranceText;
        $inner .= '<p style="margin:16px 0 0;color:#999999;">Merci de faire partie de notre association !</p>';

        return $this->wrapEmailContent('&#127881; Bienvenue officiellement chez G&#233;oMonkey !', $inner);
    }

    /**
     * Rend le template M5: Rappel d'expiration
     */
    private function renderExpirationReminderEmail(User $user, int $daysUntilExpiry): string
    {
        $renewalUrl = $this->urlGenerator->generate('app_membership_register', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $inner = '<p style="margin:0 0 12px;">Bonjour ' . htmlspecialchars($user->getFirstName()) . ',</p>';
        $inner .= sprintf('<p style="margin:0 0 12px;">Ton adh&#233;sion &#224; G&#233;oMonkey expire dans <strong style="color:#E0C020;">%d jours</strong>.</p>', $daysUntilExpiry);
        $inner .= '<p style="margin:0 0 12px;">Pour continuer &#224; profiter de tous les avantages, renouv&#232;le ton adh&#233;sion d&#232;s maintenant :</p>';
        $inner .= $this->emailButton($renewalUrl, 'Renouveler mon adh&#233;sion');
        $inner .= '<p style="margin:0;color:#999999;"><small>Le tarif reste le m&#234;me que celui que tu as pay&#233; l&#8217;ann&#233;e derni&#232;re.</small></p>';

        return $this->wrapEmailContent('&#9200; Ton adh&#233;sion expire bient&#244;t', $inner);
    }

    /**
     * Rend le template M8: Remerciement don
     */
    private function renderDonationThankYouEmail(Donation $donation): string
    {
        $inner = '<p style="margin:0 0 12px;">Bonjour ' . htmlspecialchars($donation->getDonorName() ?: 'à toi') . ',</p>';
        $inner .= sprintf(
            '<p style="margin:0 0 12px;">Nous avons bien re&#231;u ton don de <strong style="color:#E0C020;">%.2f&#8364;</strong> &#224; l&#8217;association G&#233;oMonkey. Il contribue directement au financement de nos &#233;v&#233;nements et de nos projets.</p>',
            $donation->getAmountInEuros()
        );
        
        if ($donation->getMessage()) {
            $inner .= '<div style="margin:15px 0;padding:12px;background:#1C1C1A;border-left:4px solid #E0C020;border-radius:4px;">';
            $inner .= '<p style="margin:0;"><em>&#171; ' . htmlspecialchars($donation->getMessage()) . ' &#187;</em></p>';
            $inner .= '</div>';
        }
        
        $inner .= '<p style="margin:0 0 12px;">Ce message t&#8217; sert d&#8217;accus&#233; de r&#233;ception. Conserve-le : il peut te servir de justificatif de paiement.</p>';
        $inner .= '<p style="margin:16px 0 0;color:#999999;">Un grand merci de la part de toute l&#8217;&#233;quipe G&#233;oMonkey &#128018;</p>';

        return $this->wrapEmailContent('&#128591; Merci pour ton don !', $inner);
    }

    /**
     * Rend le template M9: Alerte trésorerie
     */
    private function renderDonationAlertEmail(Donation $donation): string
    {
        $inner = '<p style="margin:0 0 12px;"><strong>Montant :</strong> <strong style="color:#E0C020;">' . sprintf('%.2f&#8364;', $donation->getAmountInEuros()) . '</strong></p>';
        $inner .= '<p style="margin:0 0 12px;"><strong>Donateur :</strong> ' . htmlspecialchars($donation->getDonorName() ?: 'Anonyme') . '</p>';
        $inner .= '<p style="margin:0 0 12px;"><strong>Email :</strong> ' . htmlspecialchars($donation->getDonorEmail() ?: 'non fourni') . '</p>';
        $inner .= '<p style="margin:0 0 12px;"><strong>Date :</strong> ' . $donation->getCreatedAt()->format('d/m/Y H:i') . '</p>';
        
        if ($donation->getMessage()) {
            $inner .= '<p style="margin:0 0 12px;"><strong>Message :</strong> <em>&#171; ' . htmlspecialchars($donation->getMessage()) . ' &#187;</em></p>';
        }
        
        $inner .= '<p style="margin:0;color:#999999;"><small>R&#233;f&#233;rence session Stripe : ' . htmlspecialchars((string) $donation->getStripeSessionId()) . '</small></p>';

        return $this->wrapEmailContent('&#127873; Nouveau don re&#231;u', $inner);
    }

    /**
     * Met à jour l'assurance details (pour M4)
     */
    public function setAssuranceDetails(string $details): void
    {
        $this->assuranceDetails = $details;
    }

    /**
     * M6: Email de vérification d'email
     * Envoi d'un lien pour valider son adresse email
     */
    public function sendEmailVerification(User $user, string $verificationUrl): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject('Vérifie ton adresse email pour GéoMonkey')
            ->html($this->renderEmailVerificationEmail($user, $verificationUrl));

        $this->mailer->send($email);
    }

    /**
     * M7: Email de reset de mot de passe
     * Envoi d'un lien pour réinitialiser son mot de passe
     */
    public function sendPasswordReset(User $user, string $resetUrl): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject('Réinitialisation de ton mot de passe GéoMonkey')
            ->html($this->renderPasswordResetEmail($user, $resetUrl));

        $this->mailer->send($email);
    }

    /**
     * Rend le template pour la vérification d'email
     */
    private function renderEmailVerificationEmail(User $user, string $verificationUrl): string
    {
        $inner = '<p style="margin:0 0 12px;">Bonjour ' . htmlspecialchars($user->getFirstName()) . ',</p>';
        $inner .= '<p style="margin:0 0 12px;">Merci pour ton inscription sur G&#233;oMonkey !</p>';
        $inner .= '<p style="margin:0 0 12px;">Pour finaliser ton inscription, clique sur le lien ci-dessous pour v&#233;rifier ton adresse email :</p>';
        $inner .= $this->emailButton($verificationUrl, 'V&#233;rifier mon email');
        $inner .= '<p style="margin:0;color:#999999;"><small>Ce lien expirera dans 24 heures. Si tu n&#8217;as pas demand&#233; cette inscription, ignore cet email.</small></p>';

        return $this->wrapEmailContent('&#9993;&#65039; V&#233;rifie ton adresse email', $inner);
    }

    /**
     * Rend le template pour le reset de mot de passe
     */
    private function renderPasswordResetEmail(User $user, string $resetUrl): string
    {
        $inner = '<p style="margin:0 0 12px;">Bonjour ' . htmlspecialchars($user->getFirstName()) . ',</p>';
        $inner .= '<p style="margin:0 0 12px;">Tu as demand&#233; une r&#233;initialisation de ton mot de passe G&#233;oMonkey.</p>';
        $inner .= '<p style="margin:0 0 12px;">Clique sur le lien ci-dessous pour cr&#233;er un nouveau mot de passe :</p>';
        $inner .= $this->emailButton($resetUrl, 'R&#233;initialiser mon mot de passe');
        $inner .= '<p style="margin:0;color:#999999;"><small>Ce lien expirera dans 1 heure. Si tu n&#8217;as pas demand&#233; cette r&#233;initialisation, ignore cet email.</small></p>';

        return $this->wrapEmailContent('&#128274; R&#233;initialisation de mot de passe', $inner);
    }

    /**
     * M10: Email de bienvenue newsletter (avec lien de désinscription RGPD)
     */
    public function sendNewsletterWelcome(NewsletterSubscriber $subscriber): void
    {
        $unsubscribeUrl = $this->urlGenerator->generate(
            'app_newsletter_unsubscribe',
            ['token' => $subscriber->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = '<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#1C1C1A;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#1C1C1A;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#242422;border-radius:14px;padding:32px;color:#ffffff;">
        <tr><td align="center" style="font-size:40px;">&#128018;</td></tr>
        <tr><td align="center" style="padding:8px 0 0;">
          <h1 style="margin:0;font-size:22px;color:#ffffff;">Bienvenue dans la newsletter G&#233;oMonkey !</h1>
        </td></tr>
        <tr><td style="padding:16px 0 0;line-height:1.6;color:#cccccc;font-size:15px;">
          Merci de ton inscription. Tu recevras en avant-premi&#232;re les news de l&#8217;asso et les nouvelles dates d&#8217;&#233;v&#233;nements.
        </td></tr>
        <tr><td style="padding:24px 0 0;color:#999999;font-size:12px;line-height:1.6;">
          Tu peux te d&#233;sinscrire &#224; tout moment en cliquant sur ce lien :
          <br><a href="' . $unsubscribeUrl . '" style="color:#E0C020;">Me d&#233;sinscrire de la newsletter</a>
          <br>Ou en &#233;crivant &#224; geomonkey@gmx.fr.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($subscriber->getEmail())
            ->subject('Bienvenue dans la newsletter GéoMonkey 🐒')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * M11: Campagne newsletter — corps fourni par la commande app:newsletter:send,
     * enveloppe GéoMonkey + pied de page désinscription ajouté pour chaque destinataire (RGPD).
     */
    public function sendNewsletterCampaign(NewsletterSubscriber $subscriber, string $subject, string $body): void
    {
        $unsubscribeUrl = $this->urlGenerator->generate(
            'app_newsletter_unsubscribe',
            ['token' => $subscriber->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $html = '<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#1C1C1A;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#1C1C1A;padding:32px 16px;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#242422;border-radius:14px;padding:32px;color:#ffffff;">
        <tr><td align="center" style="font-size:28px;padding-bottom:16px;">
          <span style="font-weight:bold;color:#E0C020;">G&#233;o</span><span style="font-weight:bold;color:#00A040;">Monkey</span> &#128018;
        </td></tr>
        <tr><td style="line-height:1.6;color:#cccccc;font-size:15px;">
          ' . $body . '
        </td></tr>
        <tr><td style="padding-top:24px;border-top:1px solid #444444;margin-top:16px;color:#999999;font-size:12px;line-height:1.6;">
          Tu re&#231;ois cet email car tu es inscrit &#224; la newsletter G&#233;oMonkey.
          <br><a href="' . $unsubscribeUrl . '" style="color:#E0C020;">Me d&#233;sinscrire</a> &#8212; ou &#233;cris &#224; geomonkey@gmx.fr.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($subscriber->getEmail())
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);
    }
}
