<?php

namespace App\Form;

use App\Entity\InterfacMatch;
use App\Entity\Slot;
use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Date;
use App\Enum\BookingType;
use App\Repository\DateRepository;
use App\Repository\SlotRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfonycasts\DynamicForms\DependentField;
use Symfonycasts\DynamicForms\DynamicFormBuilder;

/**
 * Make a booking for a given match
 */
final class MatchBookingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $court = $options['court'];
        $date = $options['date'];

        $builder = new DynamicFormBuilder($builder);

        $builder->add('date', EntityType::class, [
            'label' => 'Date',
            'class' => Date::class,
            'query_builder' => function (DateRepository $repo): QueryBuilder {
                return $repo->getFutureDatesWithAvailableSlotsQueryBuilder();
            },
            'multiple' => false,
            'autocomplete' => true,
            'data' => $date,
            'mapped' => false,
        ]);

        $builder->addDependent('date', 'slot', function(DependentField $field, ?Date $selectedDate) use ($court) {
            if ($selectedDate === null) {
                return;
            }
            $field->add(EntityType::class, [
                'label' => 'Créneau',
                'class' => Slot::class,
                'property_path' => 'booking.slot', // <- key part
                'query_builder' => function (SlotRepository $repo) use ($selectedDate, $court): QueryBuilder {
                    $qb = $repo->createQueryBuilder('s')
                        ->leftJoin('s.booking', 'b')->addSelect('b')
                        ->andWhere('b.id IS NULL')
                        ->innerJoin('s.date', 'd')->addSelect('d')
                        ->andWhere('s.date = :date')
                        ->setParameter('date', $selectedDate)
                        ->andWhere('s.court = :court')
                        ->setParameter('court', $court)
                        ->addOrderBy('s.startsAt', 'ASC');
                    
                    return $qb;
                },
                'multiple' => false,
                'expanded' => true,
                'choice_label' => function (Slot $slot) {
                    return $slot->getTimeRange() / ' - ' . $slot->getCourt();
                },
                'autocomplete' => true,
            ]);
        });

        // Make sure a Booking exists for new matches so property_path can write to it
        // NB: if booking is created, don't forget to persist it before flush()
        // NB: a new booking will only be persisted at flush time, which avoids unwanted bookings
        // if a booking already exists (edit match), nothing to do
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            /** @var InterfacMatch|null $match */
            $match = $event->getData();
            if (!$match) {
                return;
            }
            if (null === $match->getBooking()) {
                $booking = new Booking();
                $booking->setType(BookingType::MATCH);
                $booking->setMatch($match);
                $match->setBooking($booking);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InterfacMatch::class,
            'court' => null,
            'date' => null,
        ]);

        $resolver->setAllowedTypes('court', [Court::class, 'null']);
        $resolver->setAllowedTypes('date', [Date::class, 'null']);
    }
}
