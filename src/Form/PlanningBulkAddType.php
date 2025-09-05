<?php

namespace App\Form;

use App\Entity\Court;
use App\Enum\SlotDuration;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanningBulkAddType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('courts', EntityType::class, [
                'label' => 'Terrains',
                'class' => Court::class,
                'multiple' => true,
                'expanded' => false,
                'autocomplete' => true,
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin (inclue)',
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
            ])
            ->add('duration', EnumType::class, [
                'label' => 'Durée de chaque créneau',
                'class' => SlotDuration::class,
                'multiple' => false,
                'expanded' => false,
                'autocomplete' => true,
                'data' => SlotDuration::ONE_HOUR
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
