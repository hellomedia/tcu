<?php

namespace App\Form;

use App\Entity\InterfacMatch;
use App\Entity\Booking;
use App\Entity\Group;
use App\Enum\Side;
use App\Repository\GroupRepository;
use App\Repository\InterfacMatchRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

/**
 * Make a booking for a slot
 */
final class SlotBookingForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder = new DynamicFormBuilder($builder);

        $builder->add('group', EntityType::class, [
            'label' => 'Poule',
            'class' => Group::class,
            'query_builder' => function (GroupRepository $repo): QueryBuilder {
                return $repo->getGroupsWithNonProgrammedMatchesQueryBuilder();
            },
            'multiple' => false,
            'expanded' => true,
            'mapped' => false,
            'placeholder' => 'Choisir une poule',
            'attr' => [
                'data-action' => 'change->ajax#submitOnChange',
            ]
        ]);

        $builder->addDependent('match', 'group', function(DependentField $field, ?Group $selectedGroup) {
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
                'choice_extra' => function (InterfacMatch $match) {
                    $playerA = $match->getPlayersForSide(Side::A)[0];
                    $playerB = $match->getPlayersForSide(Side::B)[0];

                    return [
                        'sideA' => [
                            'name' => $playerA->getName(),
                            'dispos' => $playerA->getAvailabilities() ?? '',
                            'dates' => $playerA->getScheduledMatchsDates(),
                        ],
                        'sideB' => [
                            'name' => $playerB->getName(),
                            'dispos' => $playerB->getAvailabilities() ?? '',
                            'dates' => $playerB->getScheduledMatchsDates(),
                        ],
                    ];
                },
            ]);
        });

        $builder->add('save', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Booking::class,
        ]);
    }
}
