<?php

namespace App\Form\Handler;

use App\Entity\Slot;
use App\Repository\DateRepository;
use App\Repository\SlotRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Removes the slots of the same weekday, week after week, within a time window (mirror of the bulk add).
 * Booked slots are kept: removing them would silently unschedule the matches.
 */
class SlotBulkRemoveFormHandler
{
    public function __construct(
        private DateRepository $dateRepository,
        private SlotRepository $slotRepository,
        private EntityManager $entityManager,
        private RequestStack $requestStack,
    )
    {
    }

    /**
     * @return array{Slot[], Slot[]} removed slots, booked slots kept
     */
    public function processForm(FormInterface $form): array
    {
        $date = $form->getConfig()->getOption('date');

        if ($date) {
            $dates = [$date];
        } else {
            $week = new \DateInterval('P1W');
            $cursor = \DateTimeImmutable::createFromInterface($form->get('startDate')->getData())->setTime(0, 0);
            $lastDate = \DateTimeImmutable::createFromInterface($form->get('endDate')->getData())->setTime(0, 0);

            $ymds = [];
            for ($d = $cursor; $d <= $lastDate; $d = $d->add($week)) {
                $ymds[] = $d->format('Y-m-d');
            }

            $dates = array_values($this->dateRepository->findByYmd($ymds));
        }

        $slots = $this->slotRepository->findInWindow(
            $dates,
            $form->get('courts')->getData()->toArray(),
            \DateTimeImmutable::createFromInterface($form->get('startTime')->getData()),
            \DateTimeImmutable::createFromInterface($form->get('endTime')->getData()),
        );

        $removed = $booked = [];

        foreach ($slots as $slot) {
            if ($slot->getBooking()) {
                $booked[] = $slot;
                continue;
            }

            $this->entityManager->remove($slot);
            $removed[] = $slot;
        }

        $this->addFlashes($removed, $booked);

        return [$removed, $booked]; // caller can flush()
    }

    /**
     * @param Slot[] $removed
     * @param Slot[] $booked
     */
    private function addFlashes(array $removed, array $booked): void
    {
        $session = $this->requestStack->getSession();
        assert($session instanceof FlashBagAwareSessionInterface);
        $flashBag = $session->getFlashBag();

        $count = \count($removed);
        $flashBag->add($count ? 'success' : 'info', match ($count) {
            0 => 'Aucun créneau supprimé',
            1 => '1 créneau supprimé',
            default => $count . ' créneaux supprimés',
        });

        foreach ($booked as $slot) {
            $flashBag->add('warning', sprintf(
                '%s : créneau conservé, un match y est programmé (déprogrammez-le d\'abord)',
                $slot
            ));
        }
    }
}
