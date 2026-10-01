<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * Vérifie que les images publiques ont un consentement RGPD.
 */
#[
    Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY),
    Constraint(definition: 'App\Validator\Constraints\RgpdConsentForPublicImageValidator')
]
class RgpdConsentForPublicImage extends Constraint
{
    public string $message = 'Les images publiques nécessitent un consentement RGPD.';
    public string $propertyPath = 'rgpdConsent';

    public function validatedBy(): string
    {
        return RgpdConsentForPublicImageValidator::class;
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}