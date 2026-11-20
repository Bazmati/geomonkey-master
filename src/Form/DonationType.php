<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Formulaire de don libre — non mappé à l'entité :
 * le montant est saisi en euros, l'entité stocke des centimes
 * (conversion dans le contrôleur, même logique que MembershipFee).
 * Nom, email et message sont optionnels : un don anonyme est valide.
 *
 * i18n Lot 2b : libellés et messages de validation traduits via le
 * catalogue (domaine messages) à la construction du formulaire.
 */
class DonationType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $t = fn (string $key): string => $this->translator->trans($key);

        $builder
            ->add('amount', NumberType::class, [
                'label' => $t('form.amount_donation.label'),
                'required' => true,
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => 1,
                    'max' => 5000,
                    'step' => 0.5,
                    'class' => 'form-control form-control-lg donation-amount-input',
                    'placeholder' => '20',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: $t('form.amount_donation.not_blank')),
                    new Assert\Type(
                        type: 'numeric',
                        message: $t('form.amount_donation.numeric'),
                    ),
                    new Assert\Range(
                        min: 1,
                        max: 5000,
                        notInRangeMessage: $t('form.amount_donation.range'),
                    ),
                ],
            ])
            ->add('donorName', TextType::class, [
                'label' => $t('form.donor_name.label'),
                'required' => false,
                'attr' => ['placeholder' => 'Marcel Monkey'],
            ])
            ->add('donorEmail', EmailType::class, [
                'label' => $t('form.donor_email.label'),
                'required' => false,
                'attr' => ['placeholder' => 'marcel@exemple.fr'],
            ])
            ->add('message', TextareaType::class, [
                'label' => $t('form.message.label'),
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => $t('form.message.placeholder')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Formulaire volontairement non mappé : transformation euros -> centimes dans le contrôleur
        ]);
    }
}
