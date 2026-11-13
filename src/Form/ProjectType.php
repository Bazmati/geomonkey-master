<?php

namespace App\Form;

use App\Entity\BlogPost;
use App\Entity\Event;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', null, [
                'label' => 'Titre',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 8],
            ])
            ->add('startDate', DateTimeType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'html5' => true,
                'required' => false,
                'help' => 'Optionnel : utile pour un projet daté (chantier, édition…).',
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => true,
                'required' => false,
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'En ligne',
                'required' => false,
                'help' => 'Si coché, le projet est visible sur le site public.',
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut public',
                'choices' => ProjectStatus::cases(),
                'choice_label' => fn (ProjectStatus $status) => $status->getLabel(),
                'choice_value' => fn (?ProjectStatus $status) => $status?->value,
                'help' => 'Statut visible par tous : "En cours" ou "Planifié" donne envie de rejoindre l\'association.',
            ])
            ->add('leader', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn (User $user) => $user->getFirstName() . ' ' . $user->getLastName(),
                'required' => false,
                'label' => 'Chef de projet',
                'help' => 'Adhérent actif qui conduit le projet : lui seul et les responsables de tâches font avancer le suivi.',
                'query_builder' => fn (UserRepository $er) => $er->createQueryBuilder('u')
                    ->join('u.memberships', 'm')
                    ->where('m.isActive = true')
                    ->orderBy('u.lastName', 'ASC')
                    ->addOrderBy('u.firstName', 'ASC'),
            ])
            ->add('events', EntityType::class, [
                'class' => Event::class,
                'choice_label' => 'title',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Événements rattachés',
            ])
            ->add('blogPosts', EntityType::class, [
                'class' => BlogPost::class,
                'choice_label' => 'title',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Articles rattachés',
            ])
            ->add('imageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Image du projet',
                'help' => 'JPG, PNG, GIF, WebP (max 5 Mo). Laisser vide pour conserver l\'image actuelle.',
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Format d\'image invalide (JPG, PNG, GIF ou WebP attendu).'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
