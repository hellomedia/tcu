<?php

namespace App\Controller;

use App\Controller\BaseController;
use App\Repository\DateRepository;
use App\Repository\GroupRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GroupController extends BaseController
{
    #[Route('/groups', name: 'groups')]
    public function groups(GroupRepository $repository, DateRepository $dateRepository): Response
    {
        $groups = $repository->findAll();

        foreach ($groups as $group) {
            $dates[$group->getId()] = $dateRepository->findDatesByGroup($group);
        }

        $this->addBreadcrumb('Homepage', 'homepage');
        $this->addBreadcrumb('Poules');

        return $this->render('group/groups.html.twig', [
            'groups' => $groups,
            'dates' => $dates,
        ]);
    }

}