<?php

namespace Admin\Form;

use App\Entity\Player;
use App\Enum\Birthyear;
use App\Enum\Gender;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Nouveau joueur créé depuis le formulaire "Nouvelle inscription" (PlayerSeasonCrudController)
 *
 * Mêmes champs que le formulaire Joueur de l'admin (PlayerCrudController), sans les dispos.
 */
class PlayerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'required' => false,
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'empty_data' => '',
                'constraints' => [new NotBlank()],
            ])
            ->add('affiliationNumber', TextType::class, [
                'label' => 'N° d\'affiliation',
                'required' => false,
                'help' => 'Numéro d\'affiliation à la fédération. Permet de récupérer le classement du joueur.',
            ])
            // crée le compte du joueur (PlayerAccountManager)
            ->add('accountEmail', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'help' => 'Un compte est créé avec cet email ; la personne est ensuite invitée à choisir son mot de passe (« Inviter » dans la liste des joueurs).',
            ])
            ->add('gender', EnumType::class, [
                'label' => 'H/F',
                'class' => Gender::class,
                'choice_label' => fn(Gender $gender) => $gender->value,
                'placeholder' => '',
                'constraints' => [new NotNull()],
            ])
            ->add('birthyear', EnumType::class, [
                'label' => 'Année de naissance',
                'class' => Birthyear::class,
                'choice_label' => fn(Birthyear $birthyear) => $birthyear->value,
                'placeholder' => '',
                'required' => false,
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Player::class,
        ]);
    }
}
