<?php

namespace App\Validator\Constraints;

use App\Entity\GalleryImage;
use App\Enum\ImageVisibility;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class RgpdConsentForPublicImageValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RgpdConsentForPublicImage) {
            throw new UnexpectedTypeException($constraint, RgpdConsentForPublicImage::class);
        }

        // Vérifier que l'objet a les méthodes nécessaires
        if (!method_exists($value, 'getVisibility') || !method_exists($value, 'hasRgpdConsent')) {
            throw new UnexpectedValueException($value, 'GalleryImage');
        }

        $visibility = $value->getVisibility();

        // Si l'image est publique mais n'a pas de consentement RGPD VALIDE (coché + daté)
        if ($visibility === ImageVisibility::Public && !$value->hasValidConsent()) {
            $this->context->buildViolation($constraint->message)
                ->atPath($constraint->propertyPath)
                ->addViolation();
        }
    }
}