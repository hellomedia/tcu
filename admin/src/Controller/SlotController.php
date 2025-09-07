<?php

namespace Admin\Controller;

use Admin\Exception\InvalidWindowException;
use App\Form\Handler\SlotBulkAddFormHandler;
use App\Controller\BaseController;
use App\Form\SlotBulkAddType;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
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

        return $this->render('@admin/planning/slots.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
        ]);
    }

    #[Route('/planning/bulk-add-slots', name: 'admin_planning_bulk_add_slots', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function bulkAddSlots(Request $request): Response
    {
        $form = $this->createForm(SlotBulkAddType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            try {
                $slots = $this->formHandler->processForm($form);

                $this->entityManager->flush();

            } catch (InvalidWindowException $exception) {

                $this->addFlash('danger', $exception->getMessage());

                return $this->redirectToRoute('admin_planning_bulk_add_slots', [
                    'form' => $form,
                ]);
            }

            if (\sizeof($slots) > 0) {
                $this->addFlash('success', 'Crénaux ajoutés');
            }

            return $this->redirectToRoute('admin_planning_slots');
        }

        return $this->render('@admin/planning/bulk_add_slots.html.twig', [
            'form' => $form,
        ]);
    }
}