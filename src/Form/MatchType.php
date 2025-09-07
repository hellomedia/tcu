<?php

namespace App\Form;

use App\Entity\InterfacMatch;
use App\Entity\Player;
use App\Entity\Slot;
use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Date;
use App\Enum\BookingType;
use App\Repository\SlotRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MatchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $court = $options['court'];
        $date = $options['date'];

        $builder->add('date', EntityType::class, [
            'label' => 'Date',
            'class' => Date::class,
            'multiple' => false,
            'autocomplete' => true,
            'data' => $date,
            'mapped' => false,
        ]);

        // Slot (match -> booking -> slot)
        $builder->add('slot', EntityType::class, [
            'label' => 'Créneau',
            'class' => Slot::class,
            'property_path' => 'booking.slot', // <- key part
            'placeholder' => '— Liste des créneaux disponibles —',
            'query_builder' => function (SlotRepository $repo) use ($date, $court): QueryBuilder {
                $qb = $repo->createQueryBuilder('s')
                    ->leftJoin('s.booking', 'b')->addSelect('b')
                    ->andWhere('b.id IS NULL')
                    ->innerJoin('s.date', 'd')->addSelect('d')
                    ->andWhere('d.date >= CURRENT_DATE()')
                    ->addOrderBy('d.date', 'ASC')
                    ->addOrderBy('s.startsAt', 'ASC')
                ;

                if ($date instanceof Date) {
                    $qb->andWhere('s.date = :date')
                        ->setParameter('date', $date);
                }
                if ($court instanceof Court) {
                    $qb->andWhere('s.court = :court')
                        ->setParameter('court', $court);
                }
                return $qb;
            },
            'multiple' => false,
            'expanded' => $date ? true: false,
            'group_by' => function ($slot, $key, $value) {
                return $slot->getDate();
            },
            'choice_label' => function (Slot $slot) {
                return $slot->getTimeRange();
            },
            'autocomplete' => true,
        ]);

        $builder->add('court', EntityType::class, [
            'label' => 'Terrain',
            'class' => Court::class,
            'multiple' => false,
            'autocomplete' => true,
            'data' => $court,
            'mapped' => false,
        ]);

        // Players
        $builder->add('players', EntityType::class, [
            'label' => 'Joueurs',
            'class' => Player::class,
            'multiple' => true,
            'by_reference' => false, // ensure add/remove are called on the collection
            'autocomplete' => true,
        ]);

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
