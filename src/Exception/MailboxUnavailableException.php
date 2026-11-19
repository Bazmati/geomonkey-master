<?php

namespace App\Exception;

/**
 * Levée quand la boîte mail de l'association est injoignable :
 * configuration absente, identifiants refusés, serveur indisponible…
 * Le contrôleur affiche une alerte propre au lieu d'une page d'erreur.
 */
final class MailboxUnavailableException extends \RuntimeException
{
}
