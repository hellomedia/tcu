<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\Slot;
use App\Entity\Date;
use App\Entity\Player;
use App\Repository\DateRepository;
use App\Repository\SlotRepository;
use App\Service\AvailabilityGrid;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

/**
 * Make a booking for a given match : choose a date, then a free slot of that date
 *
 * Option 'current_slot' : when moving an already scheduled match,
 * the current slot (and its date) stay selectable and are pre-selected.
 */
final class BookingForMatchForm extends AbstractType
{
    public function __construct(
        private SlotRepository $slotRepository,
        private AvailabilityGrid $availabilityGrid,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $currentSlot = $options['current_slot'];
        \assert($currentSlot === null || $currentSlot instanceof Slot);

        $builder->add('date', EntityType::class, [
            'label' => 'Date',
            'class' => Date::class,
            'query_builder' => function (DateRepository $repo) use ($currentSlot): QueryBuilder {
                return $repo->getFutureDatesWithAvailableSlotsQueryBuilder($currentSlot?->getDate());
            },
            'multiple' => false,
            'expanded' => true,
            'mapped' => false,
            'data' => $currentSlot?->getDate(),
            'placeholder' => false,
            'attr' => [
                'data-action' => 'change->ajax#submitOnChange',
            ],
            // ChoiceExtraExtension
            'choice_extra' => function (Date $date) use ($currentSlot): array {
                return ['current' => $date === $currentSlot?->getDate()];
            },
        ]);

        $builder->addDependent('slot', 'date', function(DependentField $field, ?Date $selectedDate) use ($currentSlot) {
            if ($selectedDate === null) {
                return;
            }
            $field->add(EntityType::class, [
                'label' => 'Créneau',
                'class' => Slot::class,
                'property_path' => 'slot',
                'query_builder' => function (SlotRepository $repo) use ($selectedDate, $currentSlot): QueryBuilder {
                    return $repo->getFutureAvailableSlotsQueryBuilder($selectedDate, $currentSlot);
                },
                'multiple' => false,
                'expanded' => true,
                'choice_label' => function (Slot $slot) {
                    return $slot->getTimeRange() . ' - ' . $slot->getCourt();
                },
                // ChoiceExtraExtension
                'choice_extra' => function (Slot $slot) use ($currentSlot): array {
                    $isCurrent = $slot === $currentSlot;
                    $sameTime = !$isCurrent
                        && $currentSlot !== null
                        && $slot->getDate() === $currentSlot->getDate()
                        && $slot->getStartsAt() == $currentSlot->getStartsAt();

                    return [
                        'current' => $isCurrent,
                        'sameTime' => $sameTime,
                    ];
                },
            ]);
        });

        $builder->add('save', SubmitType::class, [
            'label' => $currentSlot === null ? 'Programmer' : 'Déplacer',
        ]);
    }

    /**
     * Slots of the selected date as a time x court grid (see form theme)
     *
     * rows: [['label' => '10:00-10:30', 'availability' => [['name', 'status'] per player], 'cells' => [courtId => ['slot' => Slot, 'choice' => ?FormView]]]]
     * A cell without choice is a booked slot (not selectable).
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!isset($view['slot'])) {
            return;
        }

        $date = $form->get('date')->getData();

        if (!$date instanceof Date) {
            return;
        }

        $choicesBySlotId = [];
        foreach ($view['slot']->children as $child) {
            $choicesBySlotId[(string) $child->vars['value']] = $child;
        }

        // availability of the match players for each time of the date (same for every court)
        $match = $form->getData()?->getMatch();
        $players = $match ? $match->getPlayers()->toArray() : [];
        $season = $match?->getSeason();

        $courts = [];
        $rows = [];

        foreach ($this->slotRepository->findByDateWithBookings($date) as $slot) {
            $court = $slot->getCourt();
            $courts[$court->getId()] = $court;

            $key = $slot->getTimeRange();
            $rows[$key]['label'] = $key;
            $rows[$key]['availability'] ??= array_map(fn (Player $player) => [
                'name' => $player->getName(),
                'status' => $this->availabilityGrid->status($player->getRegistration($season), $date, $slot->getStartsAt()),
            ], $players);
            $rows[$key]['cells'][$court->getId()] = [
                'slot' => $slot,
                'choice' => $choicesBySlotId[(string) $slot->getId()] ?? null,
            ];
        }

        $view['slot']->vars['grid'] = [
            'courts' => array_values($courts),
            'rows' => array_values($rows),
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
            'current_slot' => null,
        ]);

        $resolver->setAllowedTypes('current_slot', ['null', Slot::class]);
    }
}
