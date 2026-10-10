<?php

namespace App\Form;

use App\Entity\InterfacMatch;
use App\Entity\Booking;
use App\Entity\Date;
use App\Entity\Group;
use App\Entity\Player;
use App\Entity\Slot;
use App\Enum\Side;
use App\Repository\GroupRepository;
use App\Repository\InterfacMatchRepository;
use App\Service\AvailabilityGrid;
use App\Service\GroupColors;
use App\Service\SeasonContext;
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
 * Make a booking for a slot : choose a group, then a match of that group
 *
 * Options:
 *  - slot: the slot being booked (used to flag players who already play that day)
 *  - last_group: group pre-selected when the form opens (last group used by the admin)
 */
final class SlotBookingForm extends AbstractType
{
    public function __construct(
        private SeasonContext $seasonContext,
        private GroupColors $groupColors,
        private AvailabilityGrid $availabilityGrid,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $slot = $options['slot'];
        \assert($slot instanceof Slot);

        // availabilities of every registered player, in one query (see playerInfo)
        if ($season = $this->seasonContext->getSelected()) {
            $this->availabilityGrid->preloadSeason($season);
        }

        $builder->add('group', EntityType::class, [
            'label' => 'Poule',
            'class' => Group::class,
            'query_builder' => function (GroupRepository $repo): QueryBuilder {
                return $repo->getGroupsWithNonProgrammedMatchesQueryBuilder($this->seasonContext->getSelected());
            },
            'multiple' => false,
            'expanded' => true,
            'mapped' => false,
            'data' => $options['last_group'],
            'placeholder' => false,
            'attr' => [
                'data-action' => 'change->ajax#submitOnChange',
            ],
            // ChoiceExtraExtension
            'choice_extra' => function (Group $group): array {
                $toPlan = $group->getMatchs()->filter(fn (InterfacMatch $match) => !$match->isScheduled())->count();

                return [
                    'toPlan' => $toPlan,
                    'color' => $this->groupColors->colorOf($group),
                ];
            },
        ]);

        $builder->addDependent('match', 'group', function(DependentField $field, ?Group $selectedGroup) use ($slot) {
            if ($selectedGroup === null) {
                return;
            }
            $field->add(EntityType::class, [
                'label' => 'Match',
                'class' => InterfacMatch::class,
                'query_builder' => function (InterfacMatchRepository $repo) use ($selectedGroup): QueryBuilder {
                    return $repo->getNonScheduledMatchsQueryBuilder($selectedGroup);
                },
                'multiple' => false,
                'expanded' => true,
                // ChoiceExtraExtension
                'choice_extra' => function (InterfacMatch $match) use ($slot): array {
                    $sides = [];
                    $conflict = false;
                    $statuses = [];

                    foreach ([Side::A, Side::B] as $side) {
                        $players = [];

                        foreach ($match->getPlayersForSide($side) as $player) {
                            $info = $this->playerInfo($player, $match, $slot);
                            $conflict = $conflict || $info['overlap'];
                            $statuses[] = $info['availability'];
                            $players[] = $info;
                        }

                        $sides[] = $players;
                    }

                    return [
                        'sides' => $sides,
                        'conflict' => $conflict,
                        // for the slot : 'yes' = every player available, 'no' = at least one not available, else 'unknown'
                        'availability' => match (true) {
                            \in_array(AvailabilityGrid::NO, $statuses, true) => AvailabilityGrid::NO,
                            $statuses !== [] && !\in_array(AvailabilityGrid::UNKNOWN, $statuses, true) => AvailabilityGrid::YES,
                            default => AvailabilityGrid::UNKNOWN,
                        },
                    ];
                },
            ]);
        });

        $builder->add('save', SubmitType::class, [
            'label' => 'Programmer',
        ]);
    }

    /**
     * Infos displayed under a match for one of its players.
     * Only the matches of the match's season (= the working season) are taken into account.
     *
     * @return array{name: string, availability: string, dispos: string, dates: list<array{date: ?Date, slot: Slot, sameDay: bool, overlap: bool}>, scheduledCount: int, totalCount: int, sameDay: bool, overlap: bool}
     */
    private function playerInfo(Player $player, InterfacMatch $match, Slot $slot): array
    {
        $season = $match->getSeason();
        $scheduled = $player->getScheduledMatchs($season);
        $slotDate = $slot->getDate();

        $dates = [];
        $sameDay = false;
        $overlap = false;

        foreach ($scheduled as $scheduledMatch) {
            $other = $scheduledMatch->getSlot();
            $isSameDay = $other->getDate() === $slotDate;
            // same day and time ranges overlap : the player cannot play both
            $isOverlap = $isSameDay
                && $slot->getStartsAt() < $other->getEndsAt()
                && $other->getStartsAt() < $slot->getEndsAt();
            $sameDay = $sameDay || $isSameDay;
            $overlap = $overlap || $isOverlap;
            $dates[] = [
                'date' => $other->getDate(),
                'slot' => $other,
                'sameDay' => $isSameDay,
                'overlap' => $isOverlap,
            ];
        }

        return [
            'name' => $player->getName(),
            'availability' => $this->availabilityGrid->status($player->getRegistration($season), $slotDate, $slot->getStartsAt()),
            'dispos' => $player->getAvailabilities($season) ?? '',
            'dates' => $dates,
            'scheduledCount' => \count($dates),
            'totalCount' => $player->getMatchs($season)->count(),
            'sameDay' => $sameDay,
            'overlap' => $overlap,
        ];
    }

    /**
     * Matchs ordered by availability for the slot : both players available first, unknown, then not available.
     * Stable : the group order is kept inside each category.
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        if (!isset($view['match'])) {
            return;
        }

        $rank = [AvailabilityGrid::YES => 0, AvailabilityGrid::UNKNOWN => 1, AvailabilityGrid::NO => 2];
        $children = $view['match']->children;

        uasort($children, fn (FormView $a, FormView $b) =>
            ($rank[$a->vars['extra']['availability'] ?? 'unknown'] ?? 1) <=> ($rank[$b->vars['extra']['availability'] ?? 'unknown'] ?? 1)
        );

        $view['match']->children = $children;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
            'last_group' => null,
        ]);

        $resolver->setRequired('slot');
        $resolver->setAllowedTypes('slot', Slot::class);
        $resolver->setAllowedTypes('last_group', ['null', Group::class]);
    }
}
