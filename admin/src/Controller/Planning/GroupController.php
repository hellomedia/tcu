<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use Admin\Factory\MatchFactory;
use App\Controller\BaseController;
use App\Entity\Group;
use App\Repository\GroupRepository;
use App\Service\SeasonContext;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class GroupController extends BaseController
{
    #[Route('/planning/groups', name: 'admin_planning_groups', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function groups(GroupRepository $repository, SeasonContext $seasonContext): Response
    {
        $season = $seasonContext->getSelected();
        $groups = $repository->findBySeason($season);
        $repository->loadMatchs($groups);

        return $this->render('@admin/group/groups.html.twig', [
            'groups' => $groups,
            'season' => $season,
        ]);
    }

    #[IsCsrfTokenValid('generate-matchs', tokenKey: 'token')]
    #[Route('/planning/group/{id:group}/generate-matchs', name: 'admin_planning_group_generate_matchs', methods: ['POST'])]
    public function generateMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $matchFactory->generateGroupMatchs($group);

        return $this->redirectToRoute('admin_planning_groups');
    }

    /**
     * Add a series of matchs to a group with existing matchs
     */
    #[IsCsrfTokenValid('add-matchs', tokenKey: 'token')]
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

    /**
     * Create the missing matchs (player added after the generation), without touching the existing ones
     */
    #[IsCsrfTokenValid('complete-matchs', tokenKey: 'token')]
    #[Route('/planning/group/{id:group}/complete-matchs', name: 'admin_planning_group_complete_matchs', methods: ['POST'])]
    public function completeMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        $created = $matchFactory->completeGroupMatchs($group);

        return $this->render('@admin/group/updated_success.html.twig', [
            'group' => $group,
            'feedback' => $created > 0 ? sprintf('%d match%s ajouté%s', $created, $created > 1 ? 's' : '', $created > 1 ? 's' : '') : 'Aucun match manquant',
        ]);
    }

    /**
     * Delete the matchs of players who left the group (played matchs are kept)
     */
    #[IsCsrfTokenValid('delete-orphan-matchs', tokenKey: 'token')]
    #[Route('/planning/group/{id:group}/delete-orphan-matchs', name: 'admin_planning_group_delete_orphan_matchs', methods: ['POST'])]
    public function deleteOrphanMatchs(Group $group, MatchFactory $matchFactory): Response
    {
        ['deleted' => $deleted, 'kept' => $kept] = $matchFactory->deleteOrphanMatchs($group);

        $feedback = sprintf('%d match%s supprimé%s', $deleted, $deleted > 1 ? 's' : '', $deleted > 1 ? 's' : '');

        if ($kept > 0) {
            $feedback .= sprintf(', %d match%s joué%s conservé%s', $kept, $kept > 1 ? 's' : '', $kept > 1 ? 's' : '', $kept > 1 ? 's' : '');
        }

        return $this->render('@admin/group/updated_success.html.twig', [
            'group' => $group,
            'feedback' => $feedback,
        ]);
    }

    #[IsCsrfTokenValid('regenerate-matchs', tokenKey: 'token')]
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

    #[IsCsrfTokenValid('delete-matchs', tokenKey: 'token')]
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