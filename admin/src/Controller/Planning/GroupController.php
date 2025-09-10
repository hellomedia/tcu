<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use Admin\Factory\MatchFactory;
use App\Controller\BaseController;
use App\Entity\Group;
use App\Repository\GroupRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupController extends BaseController
{
    #[Route('/planning/groups', name: 'admin_planning_groups', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function groups(GroupRepository $repository): Response
    {
        $groups = $repository->findAll();

        return $this->render('@admin/group/index.html.twig', [
            'groups' => $groups,
        ]);
    }

    #[Route('/planning/group/{id:group}/generate-matchs', name: 'admin_planning_group_generate_matchs', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function generateMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $matchFactory->generateGroupMatchs($group);

        return $this->redirectToRoute('admin_planning_groups');
    }

    /**
     * Add a series of matchs to a group with existing matchs
     */
    #[Route('/planning/group/{id:group}/add-matchs', name: 'admin_planning_group_add_matchs', methods: ['POST'])]
    public function addMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $matchFactory->addGroupMatchs($group);

        $feedback = 'Ajout des matchs réussi';

        return $this->render('@admin/group/add_matchs_success.html.twig', [
            'group' => $group,
            'feedback' => $feedback,
        ]);
    }

    #[Route('/planning/group/{id:group}/regenerate-matchs', name: 'admin_planning_group_regenerate_matchs', methods: ['POST'])]
    public function regenerateMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $matchFactory->regenerateGroupMatchs($group);

        $feedback = 'Regénération des matchs réussie';

        return $this->render('@admin/group/regenerate_matchs_success.html.twig', [
            'group' => $group,  
            'feedback' => $feedback,
        ]);
    }

    #[Route('/planning/group/{id:group}/delete-matchs', name: 'admin_planning_group_delete_matchs', methods: ['POST'])]
    public function deleteMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $matchFactory->deleteGroupMatchs($group);

        $feedback = 'Supression réussie';

        return $this->render('@admin/group/delete_matchs_success.html.twig', [
            'group' => $group,  
            'feedback' => $feedback,
        ]);
    }

}