<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Date;
use App\Entity\InterfacMatch;
use App\Enum\BookingType;
use App\Form\MatchBookingForm;
use App\Form\MatchType;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MatchController extends BaseController
{
    public function __construct(
        private EntityManager $entityManager,
    )
    { 
    }

    /**
     * Schedule existing match
     */
    #[Route('/planning/match/{id:match}/schedule', name: 'admin_planning_match_schedule', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function schedule(InterfacMatch $match, Request $request): Response
    {
        $booking = new Booking();
        $booking->setType(BookingType::MATCH);
        $booking->setMatch($match);
        $match->setBooking($booking);

        $form = $this->createForm(MatchBookingForm::class, $match);

        $form->handleRequest($request);

        if ($form->has('slot') && $form['slot']->getData() != null && $form->isSubmitted() && $form->isValid()) {

            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            $this->addFlash('success', 'Match programmé');

            return $this->redirectToRoute('admin_planning_index');
        }

        return $this->render('@admin/planning/match/schedule.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Make a booking for a match (to be selected) at given date/court
     */
    #[Route('/planning/match/add/{date}/{court}', name: 'admin_planning_match_add', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function add(
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

        return $this->render('@admin/planning/match/add.html.twig', [
            'form' => $form,
        ]);
    }
}