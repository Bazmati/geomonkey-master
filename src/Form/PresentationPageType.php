<?php

namespace App\Form;

use App\Entity\GalleryImage;
use App\Entity\PresentationPage;
use App\Enum\ImageVisibility;
use App\Repository\GalleryImageRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PresentationPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Ex : Qui sommes-nous ?'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre est obligatoire.'),
                    new Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'),
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => ['rows' => 10, 'placeholder' => 'Le contenu de la page (visible par les visiteurs)'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le contenu est obligatoire.'),
                ],
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'help' => 'Ordre d\'affichage dans la page « Présentation » (1, 2, 3…)',
                'attr' => ['min' => 1],
                'constraints' => [
                    new Assert\NotBlank(message: 'La position est obligatoire.'),
                    new Assert\Positive(message: 'La position doit être un nombre positif.'),
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Page visible publiquement',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => 'Décochez pour garder la page en brouillon (invisible pour les visiteurs).',
            ])
            ->add('galleryImage', EntityType::class, [
                'class' => GalleryImage::class,
                'choice_label' => 'title',
                'required' => false,
                'placeholder' => '— Aucune image —',
                'label' => 'Image de la galerie',
                'query_builder' => fn (GalleryImageRepository $r) => $r->createQueryBuilder('g')
                    ->where('g.visibility = :public')
                    ->andWhere('g.isPublished = true')
                    ->setParameter('public', ImageVisibility::Public)
                    ->orderBy('g.title', 'ASC'),
                'help' => 'Seules les images publiques et publiées sont proposées : elles sont visibles par tous les visiteurs.',
            ])
            ->add('newImageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => '… ou téléverser une nouvelle image',
                'help' => 'JPG, PNG, GIF, WebP (max 5 Mo). L\'image sera créée dans la galerie en « Publique », non publiée en attendant validation du président.',
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
            'data_class' => PresentationPage::class,
        ]);
    }
}