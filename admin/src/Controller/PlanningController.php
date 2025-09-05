<?php

namespace Admin\Controller;

use Admin\Exception\InvalidWindowException;
use App\Form\Handler\BulkPlanningFormHandler;
use App\Controller\BaseController;
use App\Form\PlanningBulkAddType;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlanningController extends BaseController
{
    public function __construct(
        private BulkPlanningFormHandler $planningFormHandler,
        private EntityManager $entityManager,
    )
    {
        
    }
    #[Route('/planning', name: 'admin_planning', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function index(DateRepository $dateRepository, CourtRepository $courtRepository): Response
    {
        $dates = $dateRepository->findFutureDates();
        $courts = $courtRepository->findAll();

        return $this->render('@admin/planning/index.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
        ]);
    }

    #[Route('/planning/bulk-add-slots', name: 'admin_planning_bulk_add_slots', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function bulkAdd(Request $request): Response
    {
        $form = $this->createForm(PlanningBulkAddType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            try {
                $slots = $this->planningFormHandler->processBulkAddSlots($form);

            } catch (InvalidWindowException $exception) {

                $this->addFlash('danger', $exception->getMessage());

                return $this->redirectToRoute('admin_planning_bulk_add_slots', [
                    'form' => $form,
                ]);
            }

            $this->entityManager->flush();

            if (\sizeof($slots) > 0) {
                $this->addFlash('success', 'Crénaux ajoutés');
            }

            return $this->redirectToRoute('admin_planning');
        }

        return $this->render('@admin/planning/bulk_add_slots.html.twig', [
            'form' => $form,
        ]);
    }
}