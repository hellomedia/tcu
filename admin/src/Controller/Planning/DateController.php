<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use Admin\Exception\InvalidWindowException;
use App\Controller\BaseController;
use App\Entity\Date;
use App\Form\Handler\SlotBulkAddFormHandler;
use App\Form\SlotBulkAddType;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class DateController extends BaseController
{
    public function __construct(
        private EntityManager $entityManager,
    )
    {
    }

    #[IsCsrfTokenValid('delete-date', tokenKey: 'token')]
    #[Route('/planning/date/{id:date}/delete', name: 'admin_planning_date_delete', methods: ['POST'])]
    public function delete(Date $date, EntityManager $entityManager): Response
    {
        $dateId = $date->getId();

        $entityManager->remove($date);
        $entityManager->flush();

        $feedback = 'Supression réussie';

        return $this->render('@admin/date/delete_success.html.twig', [
            'feedback' => $feedback,
            'deletedDateId' => $dateId,
        ]);
    }

    #[Route('/planning/date/{id:date}/add-slots', name: 'admin_planning_date_add_slots', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function addSlots(Date $date, Request $request, SlotBulkAddFormHandler $handler): Response
    {
        $form = $this->createForm(SlotBulkAddType::class, options: [
            'date' => $date
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            try {
                $slots = $handler->processForm($form);

                $this->entityManager->flush();
            } catch (InvalidWindowException $exception) {

                $this->addFlash('danger', $exception->getMessage());

                return $this->redirectToRoute('@admin/date/add_slots.html.twig', [
                    'form' => $form,
                    'date' => $date,
                ]);
            }

            if (\sizeof($slots) > 0) {
                $this->addFlash('success', 'Crénaux ajoutés');
            }

            return $this->redirectToRoute('admin_planning_slots');
        }

        return $this->render('@admin/date/add_slots.html.twig', [
            'form' => $form,
            'date' => $date,
        ]);
    }
}