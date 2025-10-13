<?php

namespace App\Form;

use App\Entity\InterfacMatch;
use App\Entity\Booking;
use App\Entity\Group;
use App\Enum\Side;
use App\Form\Type\AjaxSubmitType;
use App\Repository\GroupRepository;
use App\Repository\InterfacMatchRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
            'autocomplete' => true,
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
                    return $repo->getNonProgammedMatchsQueryBuilder($selectedGroup);
                },
                'multiple' => false,
                'expanded' => true,
                'choice_attr' => function (InterfacMatch $match) {
                    $playerA = $match->getPlayersForSide(Side::A)[0];
                    $disposA = $playerA->getAvailabilities();
                    $playerB = $match->getPlayersForSide(Side::B)[0];
                    $disposB = $playerB->getAvailabilities();

                    return [
                        'data-side-a-name' => $playerA,
                        'data-side-a-dispos' => $disposA ?? '',
                        'data-side-b-name' => $playerB,
                        'data-side-b-dispos' => $disposB ?? '',
                    ];
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
