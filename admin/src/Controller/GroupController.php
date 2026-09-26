<?php

namespace Admin\Controller;

use Admin\Factory\MatchFactory;
use App\Controller\BaseController;
use App\Entity\Group;
use App\Repository\GroupRepository;
use App\Service\SeasonContext;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Liste des poules de la saison sélectionnée (cartes avec les joueurs)
 * Création / modification d'une poule : GroupCrudController
 * Matchs des poules : Planning\GroupController
 */
class GroupController extends BaseController
{
    #[IsGranted('ROLE_EDITOR')]
    #[Route('/groups', name: 'admin_groups', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function index(GroupRepository $repository, SeasonContext $seasonContext): Response
    {
        $season = $seasonContext->getSelected();

        return $this->render('@admin/group/index.html.twig', [
            'groups' => $repository->findBySeason($season),
            'season' => $season,
        ]);
    }

    /**
     * Supprime la poule ainsi que ses matchs et leurs réservations
     */
    #[IsGranted('ROLE_EDITOR')]
    #[IsCsrfTokenValid('delete-group', tokenKey: 'token')]
    #[Route('/groups/{id:group}/delete', name: 'admin_groups_delete', methods: ['POST'])]
    public function delete(Group $group, MatchFactory $matchFactory, EntityManagerInterface $entityManager): Response
    {
        $groupId = $group->getId();

        $matchFactory->deleteGroupMatchs($group);

        $entityManager->remove($group);
        $entityManager->flush();

        return $this->render('@admin/group/delete_success.html.twig', [
            'feedback' => 'Suppression réussie',
            'deletedGroupId' => $groupId,
        ]);
    }
}
