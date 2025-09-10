<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use App\Entity\Court;
use App\Entity\Date;
use App\Entity\InterfacMatch;
use App\Form\MatchType;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends BaseController
{
    public function __construct(
        private EntityManager $entityManager,
    )
    { 
    }

    /**
     * Add a match booking from scratch
     * -- Creates a match too --
     * For adding a booking for an existing match, see MatchController::addBooking()
     * 
     * STATUS: buggy, unused
     */
    #[Route('/booking/add-match-booking/{date}/{court}', name: 'admin_match_booking_add', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function addMatchBooking(
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

            return $this->redirectToRoute('admin_planning_matchs');
        }

        return $this->render('@admin/match/add.html.twig', [
            'form' => $form,
        ]);
    }
}