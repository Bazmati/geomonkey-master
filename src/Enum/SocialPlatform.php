<?php

namespace App\Enum;

enum SocialPlatform: string
{
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case LinkedIn = 'linkedin';
    case X = 'x';
    case WhatsApp = 'whatsapp';
    case TikTok = 'tiktok';

    /**
     * Libellé français pour l'interface d'administration.
     */
    public function label(): string
    {
        return match ($this) {
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::LinkedIn => 'LinkedIn',
            self::X => 'X (Twitter)',
            self::WhatsApp => 'WhatsApp',
            self::TikTok => 'TikTok',
        };
    }

    /**
     * Icône Bootstrap Icons pour les templates (boutons de partage,
     * onglet Paramètres → Réseaux sociaux).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Facebook => 'bi-facebook',
            self::Instagram => 'bi-instagram',
            self::LinkedIn => 'bi-linkedin',
            self::X => 'bi-twitter-x',
            self::WhatsApp => 'bi-whatsapp',
            self::TikTok => 'bi-tiktok',
        };
    }

    /**
     * Bouton Bootstrap (couleur outline) pour les partages.
     */
    public function buttonClass(): string
    {
        return match ($this) {
            self::Facebook => 'btn-outline-primary',
            self::Instagram => 'btn-outline-danger',
            self::LinkedIn => 'btn-outline-primary',
            self::X => 'btn-outline-dark',
            self::WhatsApp => 'btn-outline-success',
            self::TikTok => 'btn-outline-dark',
        };
    }

    /**
     * URL de partage web officielle (niveau 1 — aucun token requis).
     * Retourne null pour les réseaux sans partage web (TikTok).
     */
    public function shareUrl(string $url, string $text): ?string
    {
        return match ($this) {
            self::Facebook => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($url),
            self::X => 'https://twitter.com/intent/tweet?url=' . urlencode($url) . '&text=' . urlencode($text),
            self::WhatsApp => 'https://api.whatsapp.com/send?text=' . urlencode($text . ' — ' . $url),
            self::LinkedIn => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($url),
            self::Instagram, self::TikTok => null, // pas de partage web : API uniquement
        };
    }

    /**
     * Le réseau dispose-t-il d'une API de publication automatique (niveau 2) ?
     */
    public function supportsAutoPublish(): bool
    {
        return match ($this) {
            self::Facebook, self::Instagram, self::LinkedIn, self::X => true,
            self::WhatsApp, self::TikTok => false, // WhatsApp : opt-in requis ; TikTok : app approuvée uniquement
        };
    }

}