<?php

namespace Admin\Controller;

use Admin\Factory\MatchFactory;
use App\Controller\BaseController;
use App\Entity\Court;
use App\Entity\Date;
use App\Entity\Group;
use App\Entity\InterfacMatch;
use App\Form\Handler\SlotBulkAddFormHandler;
use App\Form\MatchType;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlanningController extends BaseController
{
    public function __construct(
        private SlotBulkAddFormHandler $planningFormHandler,
        private EntityManager $entityManager,
    )
    {
        
    }

    #[Route('/planning/by-date', name: 'admin_planning_by_date', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function planningByDate(DateRepository $dateRepository, CourtRepository $courtRepository): Response
    {
        $dates = $dateRepository->findFutureDates();
        $courts = $courtRepository->findAll();

        return $this->render('@admin/planning/planning_by_date.html.twig', [
            'dates' => $dates,
            'courts' => $courts,
        ]);
    }

    #[Route('/planning/by-group', name: 'admin_planning_by_group', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function planningByGroup(GroupRepository $repository): Response
    {
        $groups = $repository->findAll();

        return $this->render('@admin/planning/planning_by_group.html.twig', [
            'groups' => $groups,
        ]);
    }

    #[Route('/planning/group/{id:group}/generate-matchs', name: 'admin_planning_generate_group_matchs', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function generateGroupMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $matchFactory->generateGroupMatchs($group);

        return $this->redirectToRoute('admin_planning_by_group');
    }

    #[Route('/planning/group/{id:group}/regenerate', name: 'admin_planning_regenerate_group_matchs', methods: ['POST'])]
    public function regenerateGroupMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $matchFactory->regenerateGroupMatchs($group);

        $feedback = 'Regénération des matchs réussie';

        return $this->render('@admin/planning/regenerate_group_matchs_success.html.twig', [
            'group' => $group,  
            'feedback' => $feedback,
        ]);
    }

    #[Route('/planning/group/{id:group}/delete', name: 'admin_planning_delete_group_matchs', methods: ['POST'])]
    public function deleteGroupMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $matchFactory->deleteGroupMatchs($group);

        $feedback = 'Supression réussie';

        return $this->render('@admin/planning/delete_group_matchs_success.html.twig', [
            'group' => $group,  
            'feedback' => $feedback,
        ]);
    }


    #[Route('/planning/match/{id:match}/schedule', name: 'admin_planning_schedule_match', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function scheduleMatch(InterfacMatch $match): Response
    {

        return $this->redirectToRoute('admin_planning_by_group');
    }

    #[Route('/planning/add-match/{court}/{date}', name: 'admin_planning_add_match', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function addMatch(
        Request $request,
        #[MapEntity(mapping: ['court' => 'id'])]
        ?Court $court = null,
        #[MapEntity(mapping: ['date' => 'id'])]
        ?Date $date = null,
    ): Response
    {
        $match = new InterfacMatch();        

        $form = $this->createForm(MatchType::class, $match, [
            'court' => $court,
            'date' => $date,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $this->entityManager->persist($match->getBooking()); // persist the new booking too
            $this->entityManager->persist($match);

            $this->entityManager->flush();

            $this->addFlash('success', 'Match ajouté');

            return $this->redirectToRoute('admin_planning_by_date');
        }

        return $this->render('@admin/planning/add_match.html.twig', [
            'form' => $form,
        ]);
    }
}