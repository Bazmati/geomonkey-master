<?php

namespace App\Form;

use App\Entity\Event;
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
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Types autorisés : JPG, PNG, GIF, WebP'
                    ),
                ],
            ])
            ->add('deleteImage', CheckboxType::class, [
                'label' => 'Supprimer l\'image actuelle',
                'required' => false,
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
