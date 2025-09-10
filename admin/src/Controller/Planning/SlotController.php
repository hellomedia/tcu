<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use Admin\Exception\InvalidWindowException;
use App\Form\Handler\SlotBulkAddFormHandler;
use App\Controller\BaseController;
use App\Entity\Booking;
use App\Entity\Slot;
use App\Enum\BookingType;
use App\Form\SlotBookingForm;
use App\Form\SlotBulkAddType;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SlotController extends BaseController
{
    public function __construct(
        private SlotBulkAddFormHandler $formHandler,
        private EntityManager $entityManager,
    )
    {
        
    }

    #[Route('/planning/slots', name: 'admin_planning_slots', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function slots(DateRepository $dateRepository, CourtRepository $courtRepository): Response
    {
        $dates = $dateRepository->findFutureDates();
        $courts = $courtRepository->findAll();

        return $this->render('@admin/slot/slots.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
        ]);
    }

    #[Route('/planning/slot/bulk-add', name: 'admin_planning_slot_bulk_add', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function bulkAdd(Request $request): Response
    {
        $form = $this->createForm(SlotBulkAddType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            try {
                $slots = $this->formHandler->processForm($form);

                $this->entityManager->flush();

            } catch (InvalidWindowException $exception) {

                $this->addFlash('danger', $exception->getMessage());

                return $this->redirectToRoute('admin_planning_slot_bulk_add', [
                    'form' => $form,
                ]);
            }

            if (\sizeof($slots) > 0) {
                $this->addFlash('success', 'Crénaux ajoutés');
            }

            return $this->redirectToRoute('admin_planning_slots');
        }

        return $this->render('@admin/slot/bulk_add.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/planning/slot/{id:slot}/add-booking', name: 'admin_planning_slot_add_booking', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function addBooking(Slot $slot, Request $request): Response
    {
        $booking = new Booking();
        $booking->setType(BookingType::MATCH);
        $booking->setSlot($slot);
        $slot->setBooking($booking);

        $form = $this->createForm(SlotBookingForm::class, $booking);

        $form->handleRequest($request);

        $submitBtn = $form->get('save');
        assert($submitBtn instanceof ClickableInterface);

        /* isClicked() avoids submitting when updating dependent field */
        if ($submitBtn->isClicked() && $form->isSubmitted() && $form->isValid()) {

            $this->entityManager->persist($booking);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Match programmé');

            return $this->redirectToRoute('admin_planning');
        }

        // if ($request->isXmlHttpRequest()) {
        //     return $this->render('@admin/slot/_add_booking_form_container.html.twig', [
        //         'form' => $form,
        //     ]);
        // }

        return $this->render('@admin/slot/add_booking.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/planning/slot/{id:slot}/remove-booking', name: 'admin_planning_slot_remove_booking')]
    public function removeBooking(Slot $slot, Request $request): Response
    {
        $booking = $slot->getBooking();

        $this->entityManager->remove($booking);

        $this->entityManager->flush();

        // the DB is updated but the other doctrine objects did not see the change
        // (Unless we tell them like $slot->setBooking(null) or orphan removal)
        // This refreshes the doctrine object
        $this->entityManager->refresh($slot);

        return $this->render('@admin/planning/booking_removed_success.html.twig', [
            'slot' => $slot,
        ]);
    }
}