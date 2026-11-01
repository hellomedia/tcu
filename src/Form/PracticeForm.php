<?php

namespace App\Form;

use App\Entity\Booking;
use App\Entity\Date;
use App\Entity\Group;
use App\Entity\Practice;
use App\Entity\Slot;
use App\Enum\BookingType;
use App\Repository\DateRepository;
use App\Repository\GroupRepository;
use App\Repository\SlotRepository;
use App\Service\GroupColors;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

/**
 * Entraînement : choisir une date, puis un ou plusieurs créneaux libres de cette date, les poules, un intitulé, une note.
 *
 * Les créneaux ne sont pas mappés : le contrôleur les convertit en réservations (Practice::$bookings).
 *
 * Options:
 *  - slot: créneau cliqué dans le planning (nouvel entraînement) : la date est fixée, les créneaux de cette date
 *    sont proposés avec celui-là coché
 */
final class PracticeForm extends AbstractType
{
    public function __construct(
        private SlotRepository $slotRepository,
        private DateRepository $dateRepository,
        private GroupColors $groupColors,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        /** @var Practice $practice */
        $practice = $options['data'];
        $currentSlots = $practice->getSlots();
        $currentDate = $practice->getDate();
        $preselectedSlot = $options['slot'];
        \assert($preselectedSlot === null || $preselectedSlot instanceof Slot);

        $fixedSlot = $practice->getId() === null ? $preselectedSlot : null;

        if ($fixedSlot === null) {
            $this->addScheduleFields($builder, $practice, $currentSlots, $currentDate);
        } else {
            $this->addSlotsField($builder, $practice, $fixedSlot->getDate(), [$fixedSlot], []);
        }

        $this->addDetailFields($builder, $practice);

        // les créneaux cochés deviennent les réservations de l'entraînement, avant la validation (priorité > 0)
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $submit = $form->get('save');
            \assert($submit instanceof ClickableInterface);
            if (!$submit->isClicked()) {
                return; // changement de date : le formulaire est seulement réaffiché
            }
            $selected = $form->get('slots')->getData();
            $this->syncBookings($event->getData(), array_values(array_filter(\is_iterable($selected) ? [...$selected] : [])));
        }, 10);
    }

    /**
     * Date, puis créneaux de cette date (champ dépendant)
     *
     * @param Slot[] $currentSlots
     */
    private function addScheduleFields(DynamicFormBuilder $builder, Practice $practice, array $currentSlots, ?Date $currentDate): void
    {
        // date pré-sélectionnée : celle de l'entraînement, sinon la première date avec un créneau libre
        // (la grille des créneaux dépend de la date : elle s'affiche dès l'ouverture)
        $defaultDate = $currentDate
            ?? $this->dateRepository->getFutureDatesWithAvailableSlotsQueryBuilder()->setMaxResults(1)->getQuery()->getOneOrNullResult();

        $builder->add('date', EntityType::class, [
            'label' => 'Date',
            'class' => Date::class,
            'query_builder' => function (DateRepository $repo) use ($currentDate): QueryBuilder {
                return $repo->getFutureDatesWithAvailableSlotsQueryBuilder($currentDate);
            },
            'multiple' => false,
            'expanded' => true,
            'mapped' => false,
            'data' => $defaultDate,
            'placeholder' => false,
            'attr' => [
                'data-action' => 'change->ajax#submitOnChange',
            ],
            // ChoiceExtraExtension
            'choice_extra' => function (Date $date) use ($currentDate): array {
                return ['current' => $date === $currentDate];
            },
        ]);

        $builder->addDependent('slots', 'date', function (DependentField $field, ?Date $selectedDate) use ($practice, $currentSlots) {
            if ($selectedDate === null) {
                return;
            }
            $this->addSlotsField($field, $practice, $selectedDate, $currentSlots !== [] && $currentSlots[0]->getDate() === $selectedDate ? $currentSlots : [], $currentSlots);
        });

        // changement de date : les créneaux cochés appartiennent à l'ancienne date, ils ne sont plus des choix valides
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (\is_array($data) && !isset($data['save'])) {
                unset($data['slots']);
                $event->setData($data);
            }
        });
    }

    /**
     * Créneaux d'une date, libres ou déjà réservés par cet entraînement, plusieurs cochables
     *
     * @param Slot[] $checked créneaux cochés à l'ouverture
     * @param Slot[] $currentSlots créneaux actuels de l'entraînement ("Réservé")
     */
    private function addSlotsField(DynamicFormBuilder|DependentField $builder, Practice $practice, Date $date, array $checked, array $currentSlots): void
    {
        $options = [
            'label' => 'Créneaux',
            'class' => Slot::class,
            'mapped' => false,
            'query_builder' => function (SlotRepository $repo) use ($date, $practice): QueryBuilder {
                return $repo->getAvailableSlotsForPracticeQueryBuilder($date, $practice);
            },
            'multiple' => true,
            'expanded' => true,
            'data' => $checked,
            'choice_label' => function (Slot $slot) {
                return $slot->getTimeRange() . ' - ' . $slot->getCourt();
            },
            // ChoiceExtraExtension
            'choice_extra' => function (Slot $slot) use ($currentSlots): array {
                return ['current' => \in_array($slot, $currentSlots, true)];
            },
        ];

        // un champ dépendant est déjà nommé
        $builder instanceof DependentField
            ? $builder->add(EntityType::class, $options)
            : $builder->add('slots', EntityType::class, $options);
    }

    /**
     * Poules, intitulé, note, bouton
     */
    private function addDetailFields(DynamicFormBuilder $builder, Practice $practice): void
    {
        $builder->add('groups', EntityType::class, [
            'label' => 'Poules',
            'class' => Group::class,
            'query_builder' => function (GroupRepository $repo) use ($practice): QueryBuilder {
                return $repo->getSeasonQueryBuilder($practice->getSeason());
            },
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'by_reference' => false,
            // ChoiceExtraExtension
            'choice_extra' => function (Group $group): array {
                return [
                    'color' => $this->groupColors->colorOf($group),
                    'players' => $group->getPlayers()->count(),
                ];
            },
        ]);

        $builder->add('title', TextType::class, [
            'label' => 'Intitulé',
            'required' => false,
            'help' => 'À la place des noms des poules. Obligatoire quand aucune poule n\'est liée.',
            'attr' => ['maxlength' => 100, 'placeholder' => 'ex. Entraînement libre'],
        ]);

        $builder->add('note', TextareaType::class, [
            'label' => 'Note pour les joueurs',
            'required' => false,
            'attr' => ['rows' => 2, 'placeholder' => 'ex. Apportez des balles'],
        ]);

        $builder->add('save', SubmitType::class, [
            'label' => $practice->getId() === null ? 'Programmer' : 'Enregistrer',
        ]);
    }

    /**
     * Réservations = créneaux cochés : les réservations des créneaux décochés sont supprimées (créneau libéré),
     * les créneaux nouvellement cochés sont réservés
     *
     * @param Slot[] $selected
     */
    private function syncBookings(Practice $practice, array $selected): void
    {
        foreach ($practice->getBookings()->toArray() as $booking) {
            if (!\in_array($booking->getSlot(), $selected, true)) {
                $practice->removeBooking($booking);
            }
        }

        foreach ($selected as $slot) {
            if ($slot->getBooking() !== null) {
                continue; // déjà réservé par cet entraînement (un créneau pris entre-temps est exclu par la requête du champ)
            }
            $booking = (new Booking())->setType(BookingType::PRACTICE)->setSlot($slot);
            $slot->setBooking($booking);
            $practice->addBooking($booking);
        }
    }

    /**
     * Créneaux de la date choisie en grille heure x terrain (voir le thème de formulaire)
     *
     * rows: [['label' => '18:00-19:00', 'cells' => [courtId => ['slot' => Slot, 'choice' => ?FormView]]]]
     * Une cellule sans choix est un créneau occupé (match ou autre entraînement).
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!isset($view['slots'])) {
            return;
        }

        $date = $form->has('date') ? $form->get('date')->getData() : $options['slot']?->getDate();

        if (!$date instanceof Date) {
            return;
        }

        $choicesBySlotId = [];
        foreach ($view['slots']->children as $child) {
            $choicesBySlotId[(string) $child->vars['value']] = $child;
        }

        $courts = [];
        $rows = [];

        foreach ($this->slotRepository->findByDateWithBookings($date) as $slot) {
            $court = $slot->getCourt();
            $courts[$court->getId()] = $court;

            $key = $slot->getTimeRange();
            $rows[$key]['label'] = $key;
            $rows[$key]['cells'][$court->getId()] = [
                'slot' => $slot,
                'choice' => $choicesBySlotId[(string) $slot->getId()] ?? null,
            ];
        }

        $view['slots']->vars['grid'] = [
            'courts' => array_values($courts),
            'rows' => array_values($rows),
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Practice::class,
            'slot' => null,
            // un changement de date réaffiche le formulaire : pas de validation avant le bouton Programmer
            'validation_groups' => function (FormInterface $form): array|false {
                $submit = $form->get('save');
                \assert($submit instanceof ClickableInterface);

                return $submit->isClicked() ? ['Default'] : false;
            },
        ]);

        $resolver->setAllowedTypes('slot', ['null', Slot::class]);
    }
}
