<?php

namespace App\Form;

use App\Entity\BlogImage;
use App\Entity\GalleryImage;
use App\Enum\ImagePosition;
use App\Repository\GalleryImageRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BlogImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('galleryImage', EntityType::class, [
                'class' => GalleryImage::class,
                'choice_label' => 'title',
                'label' => 'Image (galerie)',
                'query_builder' => fn (GalleryImageRepository $r) => $r->createQueryBuilder('g')
                    ->andWhere('g.isPublished = true')
                    ->orderBy('g.title', 'ASC'),
                'help' => 'Seules les images publiées de la galerie sont proposées.',
            ])
            ->add('position', ChoiceType::class, [
                'class' => ImagePosition::class,
                'choice_label' => fn (?ImagePosition $p) => $p?->label(),
                'label' => 'Position',
                'help' => 'Début, milieu ou fin de l\'article.',
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Ordre',
                'attr' => ['min' => 0, 'max' => 20],
                'help' => 'Ordre entre les images de même position (0, 1, 2…).',
            ])
            ->add('caption', TextType::class, [
                'label' => 'Légende (optionnel)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BlogImage::class]);
    }
}