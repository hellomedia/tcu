<?php

namespace App\Form;

use App\Entity\MatchResult;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MatchResultForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('set1A', IntegerType::class, [
            'label' => 'Set 1',
            'attr' => [
                'tabindex' => '1',
            ],
        ]);
        $builder->add('set1B', IntegerType::class, [
            'label' => 'Set 1',
            'attr' => [
                'tabindex' => '2',
            ],
        ]);
        $builder->add('set2A', IntegerType::class, [
            'label' => 'Set 2',
            'required' => false,
            'attr' => [
                'tabindex' => '3',
            ],
        ]);
        $builder->add('set2B', IntegerType::class, [
            'label' => 'Set 2',
            'required' => false,
            'attr' => [
                'tabindex' => '4',
            ],
        ]);
        $builder->add('set3A', IntegerType::class, [
            'label' => 'Set 3',
            'required' => false,
            'attr' => [
                'tabindex' => '5',
            ],

        ]);
        $builder->add('set3B', IntegerType::class, [
            'label' => 'Set 3',
            'required' => false,
            'attr' => [
                'tabindex' => '6',
            ],
        ]);
        $builder->add('pointsA', IntegerType::class, [
            'label' => 'Points',
            'attr' => [
                'tabindex' => '7',
            ],
        ]);
        $builder->add('pointsB', IntegerType::class, [
            'label' => 'Points',
            'attr' => [
                'tabindex' => '8',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MatchResult::class,
        ]);
    }
}
