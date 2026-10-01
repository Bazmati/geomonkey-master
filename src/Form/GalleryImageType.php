<?php

namespace App\Form;

use App\Entity\GalleryImage;
use App\Enum\ImageVisibility;
use App\Validator\Constraints\RgpdConsentForPublicImage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire pour les images de la galerie.
 * 
 * Règles d'or :
 * - alt non nullable → validation automatique
 * - Visibilité Public nécessite consentement RGPD
 */
class GalleryImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est obligatoire']),
                    new Length(['max' => 255])
                ],
                'attr' => ['placeholder' => 'Titre de l\'image']
            ])
            ->add('alt', TextType::class, [
                'label' => 'Description alternative (Accessibilité)',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'La description alternative (alt) est obligatoire pour l\'accessibilité'
                    ]),
                    new Length(['max' => 500])
                ],
                'help' => 'Description pour les lecteurs d\'écran. Obligatoire pour l\'accessibilité.',
                'attr' => ['placeholder' => 'Décrivez ce que montre l\'image']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'constraints' => [
                    new Length(['max' => 2000])
                ],
                'attr' => [
                    'placeholder' => 'Description détaillée (optionnelle)',
                    'rows' => 3
                ]
            ])
            ->add('visibility', ChoiceType::class, [
                'label' => 'Visibilité',
                'choices' => ImageVisibility::all(),
                'choice_label' => function(ImageVisibility $visibility) {
                    return $visibility->getLabel();
                },
                'choice_attr' => function(ImageVisibility $visibility) {
                    return ['data-description' => $visibility->getDescription()];
                },
                'expanded' => false,
                'multiple' => false,
                'data' => ImageVisibility::Members, // Default
                'constraints' => [
                    new NotBlank(['message' => 'La visibilité est obligatoire'])
                ]
            ])
            ->add('rgpdConsent', CheckboxType::class, [
                'label' => 'Consentement RGPD',
                'required' => false,
                'help' => 'Cochez si les personnes identifiables dans cette image ont donné leur consentement.',
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('consentDetail', TextareaType::class, [
                'label' => 'Détails du consentement',
                'required' => false,
                'help' => 'Qui a consenti, quand, dans quel contexte (contrat, message, etc.)',
                'attr' => [
                    'placeholder' => 'Ex: "Marie D. - consentement par email du 15/03/2024 pour publication sur le site"',
                    'rows' => 2
                ]
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'required' => false, // Optionnel pour les modifications
                'mapped' => false, // Géré manuellement dans le contrôleur
                'help' => 'Formats autorisés: JPG, PNG, GIF, WebP (max 5MB)',
                'attr' => ['accept' => 'image/jpeg,image/png,image/gif,image/webp']
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Publier',
                'required' => false,
                'help' => 'Cochez pour rendre cette image visible selon sa visibilité.',
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GalleryImage::class,
            'constraints' => [
                new RgpdConsentForPublicImage(),
            ],
        ]);
    }
}