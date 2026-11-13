<?php

namespace App\Service;

use App\Entity\Availability;
use App\Entity\Slot;
use App\Repository\AvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Slots added to a date players already answered: assume "pas dispo" when the player answered "no"
 * to the neighbouring times of that date (the closest existing time before and after, or the only
 * one there is). Stored as real answers the player sees and can change, never inferred at read time.
 */
class NewSlotAvailability
{
    public function __construct(
        private AvailabilityRepository $availabilityRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<Slot> $newSlots slots just created (persisted, not flushed)
     *
     * @return int number of "no" answers created
     */
    public function inferUnavailability(array $newSlots): int
    {
        $newIds = array_map(spl_object_id(...), $newSlots);

        // new times per date, and the times the date had before
        $dates = [];
        foreach ($newSlots as $slot) {
            $date = $slot->getDate();
            if ($date->getId() === null) {
                continue; // date created now: nobody answered it
            }
            $dates[$date->getId()] ??= ['date' => $date, 'new' => [], 'old' => []];
            $dates[$date->getId()]['new'][$slot->getStartsAt()->format('H:i')] = $slot->getStartsAt();
        }
        foreach ($dates as &$item) {
            foreach ($item['date']->getSlots() as $slot) {
                if (!\in_array(spl_object_id($slot), $newIds, true)) {
                    $item['old'][$slot->getStartsAt()->format('H:i')] = true;
                }
            }
            // a time that already existed (slot added on another court) is not new
            $item['new'] = array_diff_key($item['new'], $item['old']);
            $item['old'] = array_keys($item['old']);
            sort($item['old']);
        }
        unset($item);

        $dates = array_filter($dates, fn (array $item) => $item['new'] !== [] && $item['old'] !== []);
        if ($dates === []) {
            return 0;
        }

        // answers[dateId][registrationId][H:i] = available
        $answers = [];
        $registrations = [];
        foreach ($this->availabilityRepository->findBy(['date' => array_column($dates, 'date')]) as $availability) {
            $registration = $availability->getRegistration();
            $registrations[$registration->getId()] = $registration;
            $answers[$availability->getDate()->getId()][$registration->getId()][$availability->getStartsAt()->format('H:i')] = $availability->isAvailable();
        }

        $created = 0;
        foreach ($dates as $dateId => $item) {
            foreach ($answers[$dateId] ?? [] as $registrationId => $byTime) {
                foreach ($item['new'] as $hm => $startsAt) {
                    if (\array_key_exists($hm, $byTime) || !$this->neighboursAreNo($item['old'], $hm, $byTime)) {
                        continue;
                    }
                    $this->entityManager->persist((new Availability())
                        ->setRegistration($registrations[$registrationId])
                        ->setDate($item['date'])
                        ->setStartsAt($startsAt)
                        ->setAvailable(false));
                    ++$created;
                }
            }
        }

        return $created;
    }

    /**
     * @param list<string>        $oldTimes sorted H:i of the date's existing slots
     * @param array<string, bool> $byTime   the player's answers for that date
     */
    private function neighboursAreNo(array $oldTimes, string $hm, array $byTime): bool
    {
        $before = array_filter($oldTimes, fn (string $time) => $time < $hm);
        $after = array_filter($oldTimes, fn (string $time) => $time > $hm);
        $neighbours = array_filter([$before ? max($before) : null, $after ? min($after) : null]);

        foreach ($neighbours as $time) {
            // unanswered or available: no guess
            if (($byTime[$time] ?? null) !== false) {
                return false;
            }
        }

        return $neighbours !== [];
    }
}
