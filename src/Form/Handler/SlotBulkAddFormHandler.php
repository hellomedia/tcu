<?php

namespace App\Form\Handler;

use Admin\Exception\InvalidWindowException;
use App\Entity\Date;
use App\Entity\Slot;
use App\Repository\DateRepository;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SlotBulkAddFormHandler
{
    public function __construct(
        private DateRepository $dateRepository,
        private EntityManager $entityManager,
        private ValidatorInterface $validator,
        private RequestStack $requestStack,
    )
    {
        
    }

    public function processForm(FormInterface $form): array
    {
        $courts       = $form->get('courts')->getData();
        $startDate    = $form->get('startDate')->getData();   // DateTimeInterface (date-only)
        $endDate      = $form->get('endDate')->getData();     // DateTimeInterface (date-only)
        $startTime    = $form->get('startTime')->getData();   // DateTimeInterface (time-only)
        $endTime      = $form->get('endTime')->getData();     // DateTimeInterface (time-only)
        $slotDuration = $form->get('duration')->getData();    // SlotDuration enum

        $week = new \DateInterval('P1W');
        $cursor   = \DateTimeImmutable::createFromInterface($startDate)->setTime(0, 0);
        $lastDate = \DateTimeImmutable::createFromInterface($endDate)->setTime(0, 0);

        // 1) Collect all target days (Y-m-d)
        $ymds = [];
        for ($d = $cursor; $d <= $lastDate; $d = $d->add($week)) {
            $ymds[] = $d->format('Y-m-d');
        }

        // 2) Preload existing Date rows in one query, keyed by Y-m-d
        $existing =  $this->dateRepository->findByYmd($ymds); // see repo method below

        // 3) Create missing Date rows
        foreach ($ymds as $ymd) {
            if (!isset($existing[$ymd])) {
                $date = (new Date())->setDate(new \DateTimeImmutable($ymd));
                $this->entityManager->persist($date);
                $existing[$ymd] = $date;
            }
        }

        // 4) Build slots per day and link to Date
        $slots = [];
        $duration = $slotDuration->toInterval();

        for ($day = $cursor; $day <= $lastDate; $day = $day->add($week)) {
            $ymd = $day->format('Y-m-d');
            $dateEntity = $existing[$ymd];

            $timeStart = $this->combineDateAndTime($day, $startTime, $startDate->getTimezone());
            $timeEnd   = $this->combineDateAndTime($day, $endTime,   $startDate->getTimezone());

            // (same strict divisibility check as before)
            $rangeSeconds = $timeEnd->getTimestamp() - $timeStart->getTimestamp();
            $durSeconds   = $this->intervalInSeconds($duration);
            if ($rangeSeconds % $durSeconds !== 0) {
                throw new InvalidWindowException(sprintf(
                    'La fenêtre %s–%s n\'est pas un multiple de %d minutes.',
                    $timeStart->format('H:i'),
                    $timeEnd->format('H:i'),
                    $slotDuration->minutes()
                ));
            }

            for ($slotStart = $timeStart; $slotStart < $timeEnd; $slotStart = $slotStart->add($duration)) {
                $slotEnd = $slotStart->add($duration);

                foreach ($courts as $court) {
                    $slot = (new Slot())
                        ->setStartsAt($slotStart)
                        ->setEndsAt($slotEnd)
                        ->setDate($dateEntity)
                        ->setCourt($court);
    
                    $slots[] = $slot;
                }
            }
        }

        $slots = $this->checkSlotUniqueness($slots);

        return $slots; // caller can flush()
    }

    /**
     * Symfony uniqueEntity constraint is checked when form is validated
     * But only if the entity is the data of the form being validated.
     * Here, the slot entity is not the form data.
     * So we must validate by hand
     */
    public function checkSlotUniqueness($slots)
    {
        $session = $this->requestStack->getSession();
        assert($session instanceof FlashBagAwareSessionInterface);
        $flashBag = $session->getFlashBag();

        $validSlots = [];

        foreach ($slots as $slot) {
            $violations = $this->validator->validate($slot); // Checks UniqueEntity
            if (\count($violations) > 0) {
                $flashBag->add('info', sprintf(
                    '%s à %s : il y a déjà une plage à cette heure.',
                    $slot->getDate()->getDate()->format('Y-m-d'),
                    $slot->getStartsAt()->format('H:i')
                ));
                continue;
            }
            $validSlots[] = $slot;
            $this->entityManager->persist($slot);
        }

        return $validSlots;
    }
    
    /**
     * Combine a date (Y-m-d) and a time (H:i) as wall clock, in $tz.
     */
    private function combineDateAndTime(DateTimeInterface $date, DateTimeInterface $time, DateTimeZone $tz): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $date->format('Y-m-d') . ' ' . $time->format('H:i'),
            $tz
        );
    }
    
    /**
     * Convert a DateInterval to seconds (safe for H/M/S ranges).
     */
    private function intervalInSeconds(DateInterval $i): int
    {
        $start = new DateTimeImmutable('@0');
        $end   = $start->add($i);
        return $end->getTimestamp() - $start->getTimestamp();
    }
}