<?php

namespace App\Form\Type;

use App\Entity\Player;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PlayerPickerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('player', EntityType::class, [
            'label' => 'Entre ton nom',
            'class' => Player::class,
            'placeholder' => 'Roger',
            'required' => false,
            'autocomplete' => true,
            'multiple' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // allow GET submissions for idempotent filtering
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }
}