<?php

namespace App\Form\Handler;

use App\Entity\Slot;
use App\Repository\SlotRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Removes the slots of a date range within a time window.
 * Booked slots are kept: removing them would silently unschedule the matches.
 */
class SlotBulkRemoveFormHandler
{
    public function __construct(
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

        // every date of the range, whatever its weekday (unlike the bulk add, which steps week by week from the start date)
        if ($date) {
            $startDate = $endDate = $date->getDate();
        } else {
            $startDate = $form->get('startDate')->getData();
            $endDate = $form->get('endDate')->getData();
        }

        $slots = $this->slotRepository->findInWindow(
            \DateTimeImmutable::createFromInterface($startDate),
            \DateTimeImmutable::createFromInterface($endDate),
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
