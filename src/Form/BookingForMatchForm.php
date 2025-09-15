<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\InterfacMatch;
use App\Entity\Slot;
use App\Entity\Date;
use App\Form\Type\AjaxSubmitType;
use App\Repository\DateRepository;
use App\Repository\SlotRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

/**
 * Make a booking for a given match
 */
final class BookingForMatchForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder->add('date', EntityType::class, [
            'label' => 'Date',
            'class' => Date::class,
            'query_builder' => function (DateRepository $repo): QueryBuilder {
                return $repo->getFutureDatesWithAvailableSlotsQueryBuilder();
            },
            'multiple' => false,
            'expanded' => true,
            'autocomplete' => true,
            'mapped' => false,
            'placeholder' => 'choisir une date',
            'attr' => [
                'data-action' => 'change->ajax#submitOnChange',
            ]
        ]);

        $builder->addDependent('slot', 'date', function(DependentField $field, ?Date $selectedDate) {
            if ($selectedDate === null) {
                return;
            }
            $field->add(EntityType::class, [
                'label' => 'Créneau',
                'class' => Slot::class,
                'property_path' => 'slot',
                'query_builder' => function (SlotRepository $repo) use ($selectedDate): QueryBuilder {
                    return $repo->getFutureAvailableSlotsQueryBuilder($selectedDate);
                },
                'multiple' => false,
                'expanded' => true,
                'choice_label' => function (Slot $slot) {
                    return $slot->getTimeRange() . ' - ' . $slot->getCourt();
                },
            ]);
        });

        $builder->add('save', AjaxSubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
