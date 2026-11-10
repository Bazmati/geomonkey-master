<?php

namespace App\Service;

use App\Entity\Donation;
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
 *   Destinataire: CONTACT_EMAIL (ex: contact@geomonkey.fr)
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
        private ?string $assuranceDetails = null
    ) {}

    /**
     * M1: Email de confirmation de réception de l'inscription
     */
    public function sendRegistrationConfirmation(User $user): void
    {
        $subject = $user->needsBureauValidation() 
            ? 'Ta demande d\'adhésion à GéoMonkey est reçue !' 
            : 'Ton inscription à GéoMonkey est confirmée !';

        $email = (new Email())
            ->from('noreply@geomonkey.fr')
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
            ->from('noreply@geomonkey.fr')
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
            ->from('noreply@geomonkey.fr')
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
            ->from('noreply@geomonkey.fr')
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
            ->from('noreply@geomonkey.fr')
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
            ->from('noreply@geomonkey.fr')
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
            ->from('noreply@geomonkey.fr')
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
        $isActiveMember = $user->needsBureauValidation();
        
        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">';
        $html .= '<div style="text-align: center; margin-bottom: 20px;">';
        $html .= '<img src="cid:logo" style="width: 80px; height: 80px; border-radius: 50%;" />';
        $html .= '<h2 style="color: #ffc107;">GéoMonkey</h2>';
        $html .= '</div>';
        
        if ($isActiveMember) {
            $html .= '<h3>Demande d\'adhésion reçue !</h3>';
            $html .= '<p>Merci pour ton intérêt à rejoindre GéoMonkey en tant que membre actif.</p>';
            $html .= '<p>Notre bureau va examiner ta demande et te contactera dans les plus brefs délais pour validation.</p>';
            $html .= '<p><strong>Une fois validée, tu recevras un lien pour effectuer le paiement de ton adhésion.</strong></p>';
        } else {
            $html .= '<h3>Inscription confirmée !</h3>';
            $html .= '<p>Merci de t\'être inscrit en tant que visiteur sur GéoMonkey.</p>';
            $html .= '<p>Tu as maintenant accès à tous les contenus publics de notre association.</p>';
            $html .= '<p>Pour devenir membre actif avec accès complet, tu peux faire une demande d\'adhésion payante depuis ton espace.</p>';
        }
        
        $html .= '<div style="margin-top: 20px; padding: 15px; background: #fff; border-radius: 8px;">';
        $html .= '<p style="margin: 0; color: #666;">Cordialement,<br>L\'équipe GéoMonkey</p>';
        $html .= '</div>';
        $html .= '</div></body></html>';
        
        return $html;
    }

    /**
     * Rend le template M2: Alerte au bureau
     */
    private function renderMembershipRequestAlertEmail(User $user): string
    {
        $adminUrl = $this->urlGenerator->generate('app_admin_membership_requests', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #fff3cd; padding: 20px; border-radius: 8px;">';
        $html .= '<h3>⚠️ Nouvelle demande d\'adhésion</h3>';
        $html .= '<p><strong>Email :</strong> ' . htmlspecialchars($user->getEmail()) . '</p>';
        $html .= '<p><strong>Nom :</strong> ' . htmlspecialchars($user->getLastName() . ' ' . $user->getFirstName()) . '</p>';
        $html .= '<p><strong>Date :</strong> ' . (new \DateTime())->format('d/m/Y H:i') . '</p>';
        $html .= '<div style="text-align: center; margin: 20px 0;">';
        $html .= sprintf('<a href="%s" style="display: inline-block; padding: 12px 24px; background: #ffc107; color: #000; text-decoration: none; border-radius: 4px;">', $adminUrl);
        $html .= 'Valider la demande';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '<p style="color: #666;">Merci de valider cette demande dans les plus brefs délais.</p>';
        $html .= '</div></body></html>';
        
        return $html;
    }

    /**
     * Rend le template M3: Validation par le bureau
     */
    private function renderValidationEmail(User $user, string $paymentUrl): string
    {
        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #d4edda; padding: 20px; border-radius: 8px;">';
        $html .= '<div style="text-align: center; margin-bottom: 20px;">';
        $html .= '<h3>✅ Ta demande est acceptée !</h3>';
        $html .= '<p>Félicitations ' . htmlspecialchars($user->getFirstName()) . ' !</p>';
        $html .= '<p>Le bureau de GéoMonkey a validé ta demande d\'adhésion en tant que membre actif.</p>';
        $html .= '<p>Pour finaliser ton adhésion, il ne te reste plus qu\'à effectuer le paiement :</p>';
        $html .= '<div style="text-align: center; margin: 20px 0;">';
        $html .= sprintf('<a href="%s" style="display: inline-block; padding: 12px 24px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px;">', $paymentUrl);
        $html .= 'Payer mon adhésion';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '<p><small>Le montant de l\'adhésion annuelle a été voté en Assemblée Générale.</small></p>';
        $html .= '</div></body></html>';
        
        return $html;
    }

    /**
     * Rend le template M4: Confirmation après paiement
     */
    private function renderMembershipConfirmedEmail(User $user): string
    {
        $whatsappText = $this->whatsappGroupLink 
            ? '<p>Rejoins notre groupe WhatsApp pour rester informé des événements : <a href="' . $this->whatsappGroupLink . '">Rejoindre le groupe</a></p>'
            : '';
        
        $assuranceText = $this->assuranceDetails
            ? '<p><strong>Assurance :</strong> ' . $this->assuranceDetails . '</p>'
            : '<p><strong>Assurance :</strong> Ton adhésion inclut une assurance responsabilité civile et individuelle accident souscrite par l\'association.</p>';

        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #d4edda; padding: 20px; border-radius: 8px;">';
        $html .= '<div style="text-align: center; margin-bottom: 20px;">';
        $html .= '<h3>🎉 Bienvenue officiellement chez GéoMonkey !</h3>';
        $html .= '<p>Ton adhésion a été confirmée avec succès.</p>';
        $html .= '</div>';
        
        $html .= '<h4>Ce que tu peux faire maintenant :</h4>';
        $html .= '<ul style="padding-left: 20px;">';
        $html .= '<li>Accéder à tous les contenus réservés aux membres</li>';
        $html .= '<li>Participer à tous nos événements</li>';
        $html .= '<li>Voter lors des Assemblées Générales</li>';
        $html .= '</ul>';
        
        $html .= $whatsappText;
        $html .= $assuranceText;
        
        $html .= '<p style="margin-top: 20px; color: #666;">Merci de faire partie de notre association !</p>';
        $html .= '</div></body></html>';
        
        return $html;
    }

    /**
     * Rend le template M5: Rappel d'expiration
     */
    private function renderExpirationReminderEmail(User $user, int $daysUntilExpiry): string
    {
        $renewalUrl = $this->urlGenerator->generate('app_membership_register', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #fff3cd; padding: 20px; border-radius: 8px;">';
        $html .= '<h3>⏰ Ton adhésion expire bientôt</h3>';
        $html .= '<p>Bonjour ' . htmlspecialchars($user->getFirstName()) . ',</p>';
        $html .= sprintf('<p>Ton adhésion à GéoMonkey expire dans <strong>%d jours</strong>.</p>', $daysUntilExpiry);
        $html .= '<p>Pour continuer à profiter de tous les avantages, renouvèle ton adhésion dès maintenant :</p>';
        $html .= '<div style="text-align: center; margin: 20px 0;">';
        $html .= sprintf('<a href="%s" style="display: inline-block; padding: 12px 24px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px;">', $renewalUrl);
        $html .= 'Renouveler mon adhésion';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '<p><small>Le tarif reste le même que celui que tu as payé l\'année dernière.</small></p>';
        $html .= '</div></body></html>';
        
        return $html;
    }

    /**
     * Rend le template M8: Remerciement don
     */
    private function renderDonationThankYouEmail(Donation $donation): string
    {
        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #d4edda; padding: 20px; border-radius: 8px;">';
        $html .= '<div style="text-align: center; margin-bottom: 20px;">';
        $html .= '<h3>🙏 Merci pour ton don !</h3>';
        $html .= '</div>';
        
        $name = $donation->getDonorName() ?: 'à toi';
        $html .= '<p>Bonjour ' . htmlspecialchars($name) . ',</p>';
        $html .= sprintf(
            '<p>Nous avons bien reçu ton don de <strong>%.2f€</strong> à l\'association GéoMonkey. Il contribue directement au financement de nos événements et de nos projets.</p>',
            $donation->getAmountInEuros()
        );
        
        if ($donation->getMessage()) {
            $html .= '<div style="margin: 15px 0; padding: 12px; background: #fff; border-left: 4px solid #ffc107; border-radius: 4px;">';
            $html .= '<p style="margin: 0;"><em>« ' . htmlspecialchars($donation->getMessage()) . ' »</em></p>';
            $html .= '</div>';
        }
        
        $html .= '<p>Ce message t\' sert d\'accusé de réception. Conserve-le : il peut te servir de justificatif de paiement.</p>';
        $html .= '<p style="margin-top: 20px; color: #666;">Un grand merci de la part de toute l\'équipe GéoMonkey 🐒</p>';
        $html .= '</div></body></html>';
        
        return $html;
    }

    /**
     * Rend le template M9: Alerte trésorerie
     */
    private function renderDonationAlertEmail(Donation $donation): string
    {
        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #d4edda; padding: 20px; border-radius: 8px;">';
        $html .= '<h3>🎁 Nouveau don reçu</h3>';
        $html .= '<p><strong>Montant :</strong> ' . sprintf('%.2f€', $donation->getAmountInEuros()) . '</p>';
        $html .= '<p><strong>Donateur :</strong> ' . htmlspecialchars($donation->getDonorName() ?: 'Anonyme') . '</p>';
        $html .= '<p><strong>Email :</strong> ' . htmlspecialchars($donation->getDonorEmail() ?: 'non fourni') . '</p>';
        $html .= '<p><strong>Date :</strong> ' . $donation->getCreatedAt()->format('d/m/Y H:i') . '</p>';
        
        if ($donation->getMessage()) {
            $html .= '<p><strong>Message :</strong> <em>« ' . htmlspecialchars($donation->getMessage()) . ' »</em></p>';
        }
        
        $html .= '<p style="color: #666;"><small>Référence session Stripe : ' . htmlspecialchars((string) $donation->getStripeSessionId()) . '</small></p>';
        $html .= '</div></body></html>';
        
        return $html;
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
            ->from('noreply@geomonkey.fr')
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
            ->from('noreply@geomonkey.fr')
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
        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #fff3cd; padding: 20px; border-radius: 8px;">';
        $html .= '<div style="text-align: center; margin-bottom: 20px;">';
        $html .= '<h3>✉️ Vérifie ton adresse email</h3>';
        $html .= '</div>';
        $html .= '<p>Bonjour ' . htmlspecialchars($user->getFirstName()) . ',</p>';
        $html .= '<p>Merci pour ton inscription sur GéoMonkey !</p>';
        $html .= '<p>Pour finaliser ton inscription, clique sur le lien ci-dessous pour vérifier ton adresse email :</p>';
        $html .= '<div style="text-align: center; margin: 20px 0;">';
        $html .= sprintf('<a href="%s" style="display: inline-block; padding: 12px 24px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px;">', $verificationUrl);
        $html .= 'Vérifier mon email';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '<p><small>Ce lien expirera dans 24 heures. Si tu n\'as pas demandé cette inscription, ignore cet email.</small></p>';
        $html .= '<p style="margin-top: 20px; color: #666;">Cordialement,<br>L\'équipe GéoMonkey</p>';
        $html .= '</div></body></html>';
        
        return $html;
    }

    /**
     * Rend le template pour le reset de mot de passe
     */
    private function renderPasswordResetEmail(User $user, string $resetUrl): string
    {
        $html = '<!DOCTYPE html><html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $html .= '<div style="background: #fff3cd; padding: 20px; border-radius: 8px;">';
        $html .= '<div style="text-align: center; margin-bottom: 20px;">';
        $html .= '<h3>🔐 Réinitialisation de mot de passe</h3>';
        $html .= '</div>';
        $html .= '<p>Bonjour ' . htmlspecialchars($user->getFirstName()) . ',</p>';
        $html .= '<p>Tu as demandé une réinitialisation de ton mot de passe GéoMonkey.</p>';
        $html .= '<p>Clique sur le lien ci-dessous pour créer un nouveau mot de passe :</p>';
        $html .= '<div style="text-align: center; margin: 20px 0;">';
        $html .= sprintf('<a href="%s" style="display: inline-block; padding: 12px 24px; background: #28a745; color: #fff; text-decoration: none; border-radius: 4px;">', $resetUrl);
        $html .= 'Réinitialiser mon mot de passe';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '<p><small>Ce lien expirera dans 1 heure. Si tu n\'as pas demandé cette réinitialisation, ignore cet email.</small></p>';
        $html .= '<p style="margin-top: 20px; color: #666;">Cordialement,<br>L\'équipe GéoMonkey</p>';
        $html .= '</div></body></html>';
        
        return $html;
    }
}
