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

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => ['class' => 'form-control', 'placeholder' => 'votre@email.com'],
                'constraints' => [
                    new Assert\NotBlank(message: 'L\'email est obligatoire'),
                    new Assert\Email(message: 'Veuillez fournir une adresse email valide'),
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Jean'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prénom est obligatoire'),
                    new Assert\Length(max: 50),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Dupont'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire'),
                    new Assert\Length(max: 50),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe est obligatoire'),
                    new Assert\Length(min: 8, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères'),
                ],
            ])
            ->add('requestedFunction', ChoiceType::class, [
                'label' => 'Statut souhaité',
                'choices' => $this->getAvailableRequestedFunctions(),
                'choice_label' => function($value) {
                    return match ($value) {
                        'membre_actif' => 'Membre actif (accès complet, adhésion payante)',
                        null => 'Simple visiteur (gratuit, accès public)',
                        default => $value,
                    };
                },
                'choice_value' => function($value) {
                    return $value;
                },
                'required' => false,
                'placeholder' => 'Simple visiteur (gratuit)',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte les [Conditions Générales d\'Adhésion](/cgu) et la [Politique de Confidentialité](/confidentialite)',
                'mapped' => false,
                'constraints' => [
                    new Assert\IsTrue(message: 'Vous devez accepter les conditions pour vous inscrire'),
                ],
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('imageConsent', CheckboxType::class, [
                'label' => 'J\'accepte que mes photos prises lors des événements soient publiées sur le site',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
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
