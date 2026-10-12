<?php

namespace App\Form;

use App\Entity\SocialNetworkSetting;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire réduit au champ externalId.
 * enabled / autoPublish passent par les toggles CSRF du contrôleur,
 * jamais par un formulaire mass-assignable.
 */
class SocialSettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('externalId', TextType::class, [
                'label' => 'Identifiant de la cible',
                'required' => false,
                'attr' => ['placeholder' => 'Ex : ID de la page Facebook, URN LinkedIn…'],
                'help' => 'Valeur NON sensible (visible publiquement). Les tokens d\'API restent dans le fichier .env.local du serveur.',
                'constraints' => [
                    new Assert\Length(
                        max: 255,
                        maxMessage: 'L\'identifiant ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SocialNetworkSetting::class,
        ]);
    }
}