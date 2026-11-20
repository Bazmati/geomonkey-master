<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formulaire d'édition du profil (front).
 * Volontairement restreint : les champs sensibles (email, mot de passe,
 * fonction bureau, rôles) n'y figurent pas, voir ProfileController.
 *
 * i18n Lot 2b : libellés et messages de validation traduits via le
 * catalogue (domaine messages) à la construction du formulaire.
 */
class ProfileType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $key): string => $this->translator->trans($key);

        $builder
            ->add('firstName', TextType::class, [
                'label' => $t('form.first_name.label'),
                'attr' => ['class' => 'form-control', 'placeholder' => $t('form.first_name.placeholder')],
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: $t('form.first_name.blank')),
                    new Assert\Length(max: 50, maxMessage: $t('form.first_name.max')),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => $t('form.last_name.label'),
                'attr' => ['class' => 'form-control', 'placeholder' => $t('form.last_name.placeholder')],
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(message: $t('form.last_name.blank')),
                    new Assert\Length(max: 50, maxMessage: $t('form.last_name.max')),
                ],
            ])
            ->add('imageConsent', CheckboxType::class, [
                'label' => $t('form.image_consent.label'),
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
}
