<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;
use Zemasterkrom\CloudflareTurnstileBundle\Form\Type\CloudflareTurnstileType;

/**
 * i18n Lot 2b : libellés et messages de validation traduits via le
 * catalogue (domaine messages) à la construction du formulaire.
 */
class RegistrationFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $key): string => $this->translator->trans($key);

        $builder
            ->add('email', EmailType::class, [
                'label' => $t('form.email'),
                'attr' => ['class' => 'form-control', 'placeholder' => 'votre@email.com'],
                'constraints' => [
                    new Assert\NotBlank(message: $t('form.email.blank')),
                    new Assert\Email(message: $t('form.email.invalid')),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => $t('form.first_name.label'),
                'attr' => ['class' => 'form-control', 'placeholder' => $t('form.first_name.placeholder')],
                'constraints' => [
                    new Assert\NotBlank(message: $t('form.first_name.blank')),
                    new Assert\Length(max: 50),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => $t('form.last_name.label'),
                'attr' => ['class' => 'form-control', 'placeholder' => $t('form.last_name.placeholder')],
                'constraints' => [
                    new Assert\NotBlank(message: $t('form.last_name.blank')),
                    new Assert\Length(max: 50),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $t('form.password.label'),
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(message: $t('form.password.blank')),
                    new Assert\PasswordStrength(minScore: 4, message: $t('form.password.strong')),
                    new Assert\Length(min: 12, minMessage: $t('form.password.min')),
                ],
            ])
            ->add('requestedFunction', ChoiceType::class, [
                'label' => $t('form.requested_function.label'),
                'choices' => [
                    'membre_actif' => 'membre_actif',
                ],
                'required' => false,
                'expanded' => true,        // ← rend des radios au lieu d'un select
                'placeholder' => false,     // pas d'option vide supplémentaire
                'choice_label' => function ($value) use ($t) {
                    return match ($value) {
                        'membre_actif' => $t('form.requested_function.active_member'),
                        default => $t('form.requested_function.visitor'),
                    };
                },
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => $t('form.agree_terms.label'),
                'label_html' => true,
                'mapped' => false,
                'constraints' => [
                    new Assert\IsTrue(message: $t('form.agree_terms.error')),
                ],
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('imageConsent', CheckboxType::class, [
                'label' => $t('form.image_consent.register.label'),
                'label_html' => true,
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            // Cloudflare Turnstile CAPTCHA
            ->add('cf_turnstile_response', CloudflareTurnstileType::class, [
                'mapped' => false,
                'label' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }

    /**
     * Retourne les statuts disponibles pour l'inscription publique
     */
    private function getAvailableRequestedFunctions(): array
    {
        return [
            null => null,
            'membre_actif' => 'membre_actif',
        ];
    }
}
