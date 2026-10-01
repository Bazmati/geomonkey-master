<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Vérifie que les images publiques ont un consentement RGPD.
 */
class RgpdConsentForPublicImage extends Constraint
{
    public string $message = 'Les images publiques nécessitent un consentement RGPD valide et daté.';
    public string $propertyPath = 'consentedAt';

    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}