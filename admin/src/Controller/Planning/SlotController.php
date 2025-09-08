<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use Admin\Exception\InvalidWindowException;
use App\Form\Handler\SlotBulkAddFormHandler;
use App\Controller\BaseController;
use App\Entity\Booking;
use App\Entity\Slot;
use App\Enum\BookingType as BookingTypeEnum;
use App\Form\BookingType;
use App\Form\SlotBulkAddType;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\Form\FormInterface;
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

        return $this->render('@admin/planning/slot/bulk_add.html.twig', [
            'form' => $form,
        ]);
    }


    #[Route('/planning/slot/{id:slot}/add-booking', name: 'admin_planning_slot_add_booking', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function addBooking(Slot $slot, Request $request): Response
    {
        $booking = new Booking();
        $booking->setType(BookingTypeEnum::MATCH);
        $slot->setBooking($booking);

        $form = $this->createForm(BookingType::class, $booking);

        $form->handleRequest($request);

        if ($form->has('match') && $form['match']->getData() != null && $form->isSubmitted() && $form->isValid()) {

            $this->entityManager->persist($booking);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Match programmé');

            return $this->redirectToRoute('admin_planning_slots');
        }

        // if ($request->isXmlHttpRequest()) {
        //     return $this->render('@admin/planning/slot/_add_booking_form_container.html.twig', [
        //         'form' => $form,
        //     ]);
        // }

        return $this->render('@admin/planning/slot/add_booking.html.twig', [
            'form' => $form,
        ]);
    }
}