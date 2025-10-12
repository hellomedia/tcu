<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use App\Entity\Booking;
use App\Entity\InterfacMatch;
use App\Entity\MatchResult;
use App\Enum\BookingType;
use App\Form\BookingForMatchForm;
use App\Form\MatchResultForm;
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
     * Add booking for existing match = schedule a match
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
                    'match', $match,
                    'form' => $form,
                ]);
            }

            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            $this->addFlash('success', 'Match programmé');

            return $this->redirectToRoute('admin_planning_groups');
        }

        return $this->render('@admin/match/add_booking.html.twig', [
            'match' => $match,
            'form' => $form,
        ]);
    }


    #[Route('/match/{id:match}/add-result', name: 'admin_match_add_result', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function addResult(InterfacMatch $match, Request $request): Response
    {
        $result = $match->getResult() ?? new MatchResult();

        $result->setMatch($match);

        $form = $this->createForm(MatchResultForm::class, $result);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->entityManager->persist($result);
            $this->entityManager->flush();

            $this->addFlash('success', 'Résultat encodé');

            return $this->redirectToRoute('admin_planning_groups');
        }

        return $this->render('@admin/match/add_result.html.twig', [
            'match' => $match,
            'form' => $form,
        ]);
    }
}