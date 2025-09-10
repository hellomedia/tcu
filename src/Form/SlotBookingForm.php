<?php

namespace App\Form;

use App\Entity\InterfacMatch;
use App\Entity\Booking;
use App\Entity\Group;
use App\Form\Type\AjaxSubmitType;
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
            'multiple' => false,
            'autocomplete' => true,
            'mapped' => false,
            'placeholder' => 'choisir une poule',
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
                    $qb = $repo->createQueryBuilder('m')
                        ->leftJoin('m.booking', 'b')->addSelect('b')
                        ->andWhere('b.id IS NULL')
                        ->andWhere('m.group = :group')
                        ->setParameter('group', $selectedGroup);
                    
                    return $qb;
                },
                'multiple' => false,
                'expanded' => false,
                'autocomplete' => true,
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
