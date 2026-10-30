<?php

namespace App\Form;

use App\Entity\Court;
use App\Entity\Date;
use App\Repository\DateRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class SlotBulkRemoveType extends AbstractType
{
    /** ISO-8601 day numbers (DateTime format 'N') */
    private const WEEKDAYS = [
        'Lundi' => 1,
        'Mardi' => 2,
        'Mercredi' => 3,
        'Jeudi' => 4,
        'Vendredi' => 5,
        'Samedi' => 6,
        'Dimanche' => 7,
    ];

    public function __construct(
        private DateRepository $dateRepository,
    )
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $date = $options['date'];

        $builder
            ->add('courts', EntityType::class, [
                'label' => 'Terrains',
                'class' => Court::class,
                'multiple' => true,
                'expanded' => false,
                'autocomplete' => true,
            ]);

        if ($date == null) {
            $builder->add('startDate', DateType::class, [
                'label' => 'Date de début',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin (inclue)',
            ])
            ->add('weekdays', ChoiceType::class, [
                'label' => 'Jours de la semaine',
                'help' => 'Seulement ces jours-là, entre la date de début et la date de fin',
                'choices' => self::WEEKDAYS,
                'multiple' => true,
                'expanded' => true,
                // the weekdays of the coming dates (usually Saturday only)
                'data' => $this->dateRepository->findFutureWeekdays(),
                'constraints' => [new Count(min: 1, minMessage: 'Choisissez au moins un jour')],
            ]);
        }

        $builder
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
                'help' => 'Les créneaux compris entièrement entre ces deux heures sont supprimés',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'date' => null,
        ]);

        $resolver->addAllowedTypes('date', ['null', Date::class]);
    }
}
