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

/**
 * Formulaire de don libre — non mappé à l'entité :
 * le montant est saisi en euros, l'entité stocke des centimes
 * (conversion dans le contrôleur, même logique que MembershipFee).
 * Nom, email et message sont optionnels : un don anonyme est valide.
 */
class DonationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', NumberType::class, [
                'label' => 'Montant de ton don (€)',
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
                    new Assert\NotBlank(message: 'Merci de choisir un montant.'),
                    new Assert\Type(
                        type: 'numeric',
                        message: 'Le montant doit être un nombre.',
                    ),
                    new Assert\Range(
                        min: 1,
                        max: 5000,
                        notInRangeMessage: 'Le don doit être compris entre {{ min }}€ et {{ max }}€. Pour un don supérieur à {{ max }}€, contacte-nous directement pour un virement bancaire.',
                    ),
                ],
            ])
            ->add('donorName', TextType::class, [
                'label' => 'Ton nom (optionnel)',
                'required' => false,
                'attr' => ['placeholder' => 'Marcel Monkey'],
            ])
            ->add('donorEmail', EmailType::class, [
                'label' => 'Ton email (optionnel, pour recevoir un accusé de réception)',
                'required' => false,
                'attr' => ['placeholder' => 'marcel@exemple.fr'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Un petit mot pour l\'asso (optionnel)',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Continuez comme ça !'],
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
