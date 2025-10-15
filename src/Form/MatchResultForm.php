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
        ]);
        $builder->add('set1B', IntegerType::class, [
            'label' => 'Set 1',
        ]);
        $builder->add('set2A', IntegerType::class, [
            'label' => 'Set 2',
            'required' => false,
        ]);
        $builder->add('set2B', IntegerType::class, [
            'label' => 'Set 2',
            'required' => false,
        ]);
        $builder->add('set3A', IntegerType::class, [
            'label' => 'Set 3',
            'required' => false,

        ]);
        $builder->add('set3B', IntegerType::class, [
            'label' => 'Set 3',
            'required' => false,
        ]);
        $builder->add('pointsA', IntegerType::class, [
            'label' => 'Points',
        ]);
        $builder->add('pointsB', IntegerType::class, [
            'label' => 'Points',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MatchResult::class,
        ]);
    }
}
