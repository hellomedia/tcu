<?php

namespace Admin\Controller\Planning;

use App\Controller\BaseController;
use App\Entity\Court;
use App\Entity\Date;
use App\Entity\InterfacMatch;
use App\Form\MatchType;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    #[AdminRoute('/booking/add-match-booking/{date}/{court}', name: 'match_booking_add')]
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

            return $this->redirectToRoute('admin_planning_slots');
        }

        return $this->render('@admin/match/add.html.twig', [
            'form' => $form,
        ]);
    }
}