<?php

namespace Pack\Security\Form\Field;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Usage
 * 
 * $builder->add('occupation', HoneypotField::class);
 * 
 * - Use a normal input text, not type hidden
 * - Use a normal sounding field name (occupation)
 * - Hide in CSS, with sth less obvious than display:none
 * - Process form ==> give regular feedback
 */
class HoneypotField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'required' => false,
            'label' => 'Occupation',
            'attr' => [
                'autocomplete' => 'off',
                'tabindex' => '-1',
                'aria-hidden' => 'true',
            ],
            'row_attr' => [
                'class' => 'occupation',
            ],
        ]);
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
