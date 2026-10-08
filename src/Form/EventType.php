<?php

namespace App\Form;

use App\Entity\Event;
use App\Entity\GalleryImage;
use App\Enum\ImageVisibility;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use App\Repository\GalleryImageRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('description')
            ->add('startDate', DateTimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'input_format' => 'd-m-Y\TH:i',
            ])
            ->add('endDate', DateTimeType::class, [
                'widget' => 'single_text',
                'html5' => true,
                'input_format' => 'd-m-Y\TH:i',
            ])
            ->add('location')
            ->add('isPublished', CheckboxType::class, [
                'required' => false,
            ])
            ->add('galleryImages', EntityType::class, [
                'class' => GalleryImage::class,
                'choice_label' => 'title',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'label' => 'Images de la galerie',
                'query_builder' => fn (GalleryImageRepository $r) => $r->createQueryBuilder('g')
                    ->where('g.visibility = :public')
                    ->andWhere('g.isPublished = true')
                    ->setParameter('public', ImageVisibility::Public)
                    ->orderBy('g.title', 'ASC'),
                'help' => 'Seules les images publiques et publiées sont proposées.',
            ])
            ->add('newImageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => '… ou téléverser une nouvelle image',
                'help' => 'JPG, PNG, GIF, WebP (max 5 Mo). Créée en « Publique », non publiée en attendant validation du président.',
                'constraints' => [new File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])],
            ])
            ->add('newImageAlt', TextType::class, [
                'mapped' => false, 'required' => false,
                'label' => 'Description alternative (alt)',
                'attr' => ['placeholder' => 'Décrivez ce que montre l\'image'],
            ])
            ->add('newImageConsent', CheckboxType::class, [
                'mapped' => false, 'required' => false,
                'label' => 'Consentement RGPD : les personnes identifiables ont donné leur accord',
                'attr' => ['class' => 'form-check-input'],
            ]);

            // Validation croisée : fichier soumis ⇒ alt + consentement obligatoires
            $builder->addEventListener(FormEvents::POST_SUBMIT, function ($event) {
                $form = $event->getForm();
                if ($form->get('newImageFile')->getData() === null) {
                    return;
                }

                if (trim((string) $form->get('newImageAlt')->getData()) === '') {
                    $form->get('newImageAlt')->addError(new FormError(
                        'La description alternative (alt) est obligatoire pour l\'accessibilité.'
                    ));
                }

                if (!$form->get('newImageConsent')->getData()) {
                    $form->get('newImageConsent')->addError(new FormError(
                        'Le consentement RGPD est obligatoire pour une image publique.'
                    ));
                }
            });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
