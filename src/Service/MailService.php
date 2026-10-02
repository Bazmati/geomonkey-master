<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service pour l'envoi des emails transactionnels liés à l'adhésion
 * 
 * FLUX COMPLET DES 5 EMAILS :
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
     * Met à jour l'assurance details (pour M4)
     */
    public function setAssuranceDetails(string $details): void
    {
        $this->assuranceDetails = $details;
    }
}
