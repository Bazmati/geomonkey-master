<?php

namespace App\Form;

use App\Enum\OfficeFunction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prénom est obligatoire'),
                    new Assert\Length(max: 50, maxMessage: 'Le prénom doit faire au maximum {{ limit }} caractères'),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire'),
                    new Assert\Length(max: 50, maxMessage: 'Le nom doit faire au maximum {{ limit }} caractères'),
                ],
            ])
            ->add('officeFunction', ChoiceType::class, [
                'label' => 'Fonction au bureau',
                'choices' => OfficeFunction::cases(),
                'choice_value' => fn (OfficeFunction $f) => $f->value,
                'choice_label' => fn (OfficeFunction $f) => match ($f) {
                    OfficeFunction::President => 'Président',
                    OfficeFunction::Treasurer => 'Trésorier',
                    OfficeFunction::Secretary => 'Secrétaire',
                    OfficeFunction::ActiveMember => 'Membre actif',
                    OfficeFunction::None => 'Aucune',
                },
                'attr' => ['class' => 'form-select'],
            ])
            ->add('isAdmin', CheckboxType::class, [
                'label' => 'Administrateur',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'data' => in_array('ROLE_ADMIN', $options['current_roles'] ?? [], true),
            ])
            ->add('membershipValidUntil', DateType::class, [
                'label' => 'Adhésion valide jusqu\'au',
                'widget' => 'single_text',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'input' => 'datetime',
            ]);

        // En création uniquement : mot de passe
        if ($options['is_new']) {
            $builder->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe est obligatoire'),
                    new Assert\Length(min: 8, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères'),
                ],
            ]);
        } else {
            // En édition : reset de mot de passe optionnel
            $builder->add('plainPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe (laisser vide pour ne pas changer)',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\Length(min: 8, minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères'),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => false,
            'current_roles' => [],
        ]);
    }
}