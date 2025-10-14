<?php

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Adds a 'choice_extra' form property
 * 
 * Useful to pass custom variables to choice templates
 * 
 * Usage:

    $field->add(EntityType::class, [
        'label' => 'Match',
        'class' => InterfacMatch::class,
        'multiple' => false,
        'expanded' => true,
        'placeholder' => 'Choisir un match',
        'choice_extra' => function (InterfacMatch $m): array {
            $a = $m->participantForSide(Side::A)?->getPlayer()?->getDispos();
            $b = $m->participantForSide(Side::B)?->getPlayer()?->getDispos();
            return ['a' => (string)($a ?? ''), 'b' => (string)($b ?? '')];
        },
    ]);

 */
final class ChoiceExtraExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [ChoiceType::class]; // covers EntityType
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Callable: fn ($entity): array
        $resolver->setDefault('choice_extra', null);
    }

    /**
     * For ChoiceType, the radio children are created in finishView(), not buildView().
     * We need to work on children, so we must use finishView(), not buildView().
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$options['expanded']) {
            return; // only relevant for radios/checkboxes
        }

        // Build a value => entity map (flatten groups if any)
        $choices = $view->vars['choices'] ?? [];
        $valueToEntity = [];
        $stack = $choices;
        while ($stack) {
            $c = array_pop($stack);
            if ($c instanceof \Symfony\Component\Form\ChoiceList\View\ChoiceGroupView) {
                foreach ($c->choices as $sub) {
                    $stack[] = $sub;
                }
                continue;
            }
            // $c is ChoiceView
            $valueToEntity[(string)$c->value] = $c->data ?? null;
        }

        $callable = \is_callable($options['choice_extra']) ? $options['choice_extra'] : null;

        foreach ($view->children as $child) {
            // Always initialize to avoid "key does not exist"
            $child->vars['extra'] = [];

            $value = (string)($child->vars['value'] ?? '');
            $entity = $valueToEntity[$value] ?? null;

            if ($entity && $callable) {
                $data = $callable($entity);
                if (\is_array($data)) {
                    $child->vars['extra'] = $data;
                }
            }
        }
    }
}
