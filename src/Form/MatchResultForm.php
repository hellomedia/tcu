<?php

namespace App\Form;

use App\Entity\MatchResult;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Score only: the points are computed by MatchResult.
 * Fields in entry order (set 1 A, set 1 B, set 2 A...), see score_entry_controller.js
 */
final class MatchResultForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tabindex = 1;

        foreach ([1, 2, 3] as $set) {
            foreach (['A', 'B'] as $side) {
                $builder->add("set$set$side", IntegerType::class, [
                    'label' => "Set $set",
                    'required' => $set === 1,
                    'attr' => [
                        'tabindex' => (string) $tabindex++,
                        // numeric keypad on phones
                        'inputmode' => 'numeric',
                        'pattern' => '[0-9]*',
                        'min' => 0,
                        'autocomplete' => 'off',
                        'data-score-entry-target' => 'game',
                        'data-set' => $set,
                        'data-side' => $side,
                        'data-action' => 'input->score-entry#next focus->score-entry#select',
                    ],
                ]);
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MatchResult::class,
        ]);
    }
}
