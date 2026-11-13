<?php

namespace App\Form;

use App\Entity\GalleryImage;
use App\Entity\ProjectTask;
use App\Entity\User;
use App\Enum\ProjectTaskState;
use App\Repository\GalleryImageRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All as AllConstraint;
use Symfony\Component\Validator\Constraints\File;

class ProjectTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la tâche',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
                'help' => 'Détail de la tâche : quoi faire, comment, avec quoi…',
            ])
            ->add('state', ChoiceType::class, [
                'label' => 'État',
                'choices' => ProjectTaskState::cases(),
                'choice_label' => fn (ProjectTaskState $state) => $state->getLabel(),
                'choice_value' => fn (?ProjectTaskState $state) => $state?->value,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('assignee', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn (User $user) => $user->getFirstName() . ' ' . $user->getLastName(),
                'required' => false,
                'label' => 'Responsable de la tâche',
                'help' => 'Lui et le chef de projet peuvent changer l\'état de cette tâche.',
                'query_builder' => fn (UserRepository $er) => $er->createQueryBuilder('u')
                    ->orderBy('u.lastName', 'ASC')
                    ->addOrderBy('u.firstName', 'ASC'),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('galleryImages', EntityType::class, [
                'class' => GalleryImage::class,
                'choice_label' => 'title',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Images de la galerie liées',
                'query_builder' => fn (GalleryImageRepository $er) => $er->createQueryBuilder('g')
                    ->where('g.isPublished = true')
                    ->orderBy('g.title', 'ASC'),
                'attr' => ['class' => 'form-select'],
            ])
            ->add('newDocuments', FileType::class, [
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'label' => 'Ajouter des documents',
                'help' => 'PDF, images, bureautique… (10 Mo max par fichier).',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new AllConstraint([
                        new File(
                            maxSize: '10M',
                            mimeTypes: [
                                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                                'application/pdf', 'application/zip',
                                'text/plain', 'text/csv',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                            ],
                            mimeTypesMessage: 'Type de fichier non autorisé.'
                        ),
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectTask::class,
        ]);
    }
}
