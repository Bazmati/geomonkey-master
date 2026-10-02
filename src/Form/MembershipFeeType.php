<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MembershipFeeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'Montant de l\'adhésion (€)',
                'currency' => 'EUR',
                'scale' => 2,
                'data' => $options['default_amount'],
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\Positive(),
                    new \Symfony\Component\Validator\Constraints\NotBlank(),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0.01',
                ],
            ])
            ->add('effectiveFrom', DateType::class, [
                'label' => 'Date d\'effet',
                'widget' => 'single_text',
                'html5' => true,
                'required' => false,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes (ex: "Tarif 2025 voté en AG du 15/10/2024")',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Ex: Tarif 2025 voté en AG du 15/10/2024',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'default_amount' => 15.00,
        ]);
        $resolver->setDefined('default_amount');
    }
}
