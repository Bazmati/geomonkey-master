<?php

namespace App\Form;

use App\Entity\BlogPost;
use App\Entity\GalleryImage;
use App\Enum\ImageVisibility;
use App\Entity\BlogCategory;
use App\Entity\BlogTag;
use App\Entity\Event;
use App\Form\BlogImageType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use App\Repository\GalleryImageRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BlogPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex : Notre assemblée générale 2026'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre est obligatoire.'),
                    new Assert\Length(
                        max: 255,
                        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('summary', TextareaType::class, [
                'label' => 'Résumé (meta description)',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'class' => 'form-control',
                    'data-summary-counter' => 'summaryCounter',
                    'placeholder' => 'Accroche qui donne envie de cliquer et contient les mots-clés de l\'article. 150 à 160 caractères : c\'est ce que Google affiche dans ses résultats.',
                ],
                'help' => 'Idéal : 150 à 160 caractères (minimum 80). C\'est ce texte que Google affiche sous le titre de la page dans ses résultats — il doit résumer l\'article et donner envie de cliquer.',
                'constraints' => [
                    new Assert\Length(
                        min: 80,
                        max: 160,
                        minMessage: 'Le résumé doit faire au moins {{ limit }} caractères pour une meta description efficace.',
                        maxMessage: 'Le résumé ne peut pas dépasser {{ limit }} caractères (limite de troncature Google).'
                    ),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'rows' => 15,
                    'placeholder' => "Introduction de l'article...\n\n## Un sous-titre\nTexte du paragraphe...\n\n- élément de liste\n- autre élément\n\n> Une citation éventuelle",
                ],
                'help' => 'Laissez une ligne vide entre les paragraphes. ## Titre = sous-titre, ### = sous-sous-titre, "- " = liste à puces, "> " = citation.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le contenu est obligatoire.'),
                ],
            ])
                        ->add('category', EntityType::class, [
                'class' => BlogCategory::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '— Aucune catégorie —',
                'label' => 'Catégorie',
            ])
            ->add('tags', EntityType::class, [
                'class' => BlogTag::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,   // cases à cocher : parfait pour < 20 tags
                'required' => false,
                'label' => 'Tags',
                'help' => 'Un article peut avoir plusieurs tags.',
            ])
            ->add('events', EntityType::class, [
                'class' => Event::class,
                'choice_label' => 'title',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Événements liés',
                'help' => 'Rattachez cet article à un ou plusieurs événements (annonce, bilan…).',
            ])
            ->add('images', CollectionType::class, [
                'entry_type' => BlogImageType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'delete_empty' => true,
                'required' => false,
                'label' => 'Images dans l\'article',
                'help' => 'Choisissez une image de la galerie, sa position (début/milieu/fin) et son ordre.',
            ])
            ->add('galleryImages', EntityType::class, [
                'class' => GalleryImage::class,
                'choice_label' => 'title',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Images de la galerie associées',
                'query_builder' => fn (GalleryImageRepository $r) => $r->createQueryBuilder('g')
                    ->where('g.visibility = :public')
                    ->andWhere('g.isPublished = true')
                    ->setParameter('public', ImageVisibility::Public)
                    ->orderBy('g.title', 'ASC'),
                'help' => 'Seules les images publiques et publiées sont proposées : elles seront visibles par tous les visiteurs.',
            ])
            ->add('newImageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Image de couverture (téléverser)',
                'help' => 'JPG, PNG, GIF, WebP (max 5 Mo). L\'image sera créée dans la galerie en « Publique », non publiée en attendant validation du président.',
                'constraints' => [
                    new Assert\File(
                        maxSize: '5M',
                        maxSizeMessage: 'L\'image ne doit pas dépasser {{ limit }}.',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Formats autorisés : JPG, PNG, GIF, WebP.'
                    ),
                ],
            ])
            ->add('newImageAlt', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Description alternative (alt)',
                'help' => 'Obligatoire si vous téléversez une image — nécessaire pour l\'accessibilité.',
                'attr' => ['placeholder' => 'Décrivez ce que montre l\'image'],
            ])
            ->add('newImageConsent', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Consentement RGPD : les personnes identifiables sur cette image ont donné leur accord',
                'attr' => ['class' => 'form-check-input'],
            ]);

        // Validation croisée : fichier soumis ⇒ alt + consentement obligatoires
        $builder->addEventListener(FormEvents::POST_SUBMIT, function ($event) {
            $form = $event->getForm();
            $file = $form->get('newImageFile')->getData();

            if ($file === null) {
                return;
            }

            if (trim((string) $form->get('newImageAlt')->getData()) === '') {
                $form->get('newImageAlt')->addError(new FormError(
                    'La description alternative (alt) est obligatoire pour l\'accessibilité.'
                ));
            }

            if (!$form->get('newImageConsent')->getData()) {
                $form->get('newImageConsent')->addError(new FormError(
                    'Le consentement RGPD est obligatoire pour une image publique. Si l\'image ne montre aucune personne identifiable, cochez en confiance.'
                ));
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BlogPost::class,
        ]);
    }
}