<?php

namespace Admin\Controller\Planning;

use Admin\Exception\InvalidWindowException;
use App\Controller\BaseController;
use App\Entity\Date;
use App\Form\Handler\SlotBulkAddFormHandler;
use App\Form\Handler\SlotBulkRemoveFormHandler;
use App\Form\SlotBulkAddType;
use App\Form\SlotBulkRemoveType;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class DateController extends BaseController
{
    public function __construct(
        private EntityManager $entityManager,
    ) {
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

    #[AdminRoute('/planning/date/{id:date}/add-slots', name: 'planning_date_add_slots')]
    public function addSlots(Date $date, Request $request, SlotBulkAddFormHandler $handler): Response
    {
        $form = $this->createForm(SlotBulkAddType::class, options: [
            'date' => $date,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $slots = $handler->processForm($form);

                $this->entityManager->flush();
            } catch (InvalidWindowException $exception) {
                $this->addFlash('danger', $exception->getMessage());

                // the form again, with the message (the slots and dates it prepared are not flushed)
                return $this->render('@admin/date/add_slots.html.twig', [
                    'form' => $form,
                    'date' => $date,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
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

    #[AdminRoute('/planning/date/{id:date}/remove-slots', name: 'planning_date_remove_slots')]
    public function removeSlots(Date $date, Request $request, SlotBulkRemoveFormHandler $handler): Response
    {
        $form = $this->createForm(SlotBulkRemoveType::class, options: [
            'date' => $date,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $handler->processForm($form);

            $this->entityManager->flush();

            return $this->redirectToRoute('admin_planning_slots');
        }

        return $this->render('@admin/date/remove_slots.html.twig', [
            'form' => $form,
            'date' => $date,
        ]);
    }
}
