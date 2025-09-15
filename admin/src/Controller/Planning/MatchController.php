<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use App\Entity\Booking;
use App\Entity\InterfacMatch;
use App\Enum\BookingType;
use App\Form\BookingForMatchForm;
use App\Repository\CourtRepository;
use App\Repository\DateRepository;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormError;
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
     * Add booking for existing match
     */
    #[Route('/match/{id:match}/add-booking', name: 'admin_match_add_booking', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function addBooking(InterfacMatch $match, Request $request): Response
    {        
        $booking = new Booking();
        $booking->setType(BookingType::MATCH);
        $booking->setMatch($match);
        $match->setBooking($booking);

        $form = $this->createForm(BookingForMatchForm::class, $booking);

        $form->handleRequest($request);

        $submitBtn = $form->get('save');
        assert($submitBtn instanceof ClickableInterface);

        /* isClicked() avoids submitting when updating dependent field */
        if ($submitBtn->isClicked() && $form->isSubmitted() && $form->isValid()) {

            if ($form->get('slot')->getData() == null) {
                $form->get('slot')->addError(new FormError('Veuillez sélectionner une plage horaire'));

                return $this->render('@admin/match/add_booking.html.twig', [
                    'form' => $form,
                ]);
            }

            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            $this->addFlash('success', 'Match programmé');

            return $this->redirectToRoute('admin_planning_groups');
        }

        return $this->render('@admin/match/add_booking.html.twig', [
            'form' => $form,
        ]);
    }
}