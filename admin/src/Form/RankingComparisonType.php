<?php

namespace Admin\Form;

use App\Enum\Ranking;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RankingComparisonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('operator', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    '≥' => 'gte',
                    '≤' => 'lte',
                ],
                'required' => true,
                'placeholder' => '',
            ])
            ->add('ranking', EnumType::class, [
                'label' => false,
                'class' => Ranking::class,
                'required' => true,
                'placeholder' => '',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // value will be an array: ['operator' => ..., 'ranking' => Ranking::...]
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
