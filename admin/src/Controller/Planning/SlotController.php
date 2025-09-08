<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
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
}