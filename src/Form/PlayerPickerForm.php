<?php

namespace App\Form;

use App\Form\PlayerAutocompleteField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PlayerPickerForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('player', PlayerAutocompleteField::class, [
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