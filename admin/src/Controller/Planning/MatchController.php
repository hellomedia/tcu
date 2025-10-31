<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use App\Entity\Booking;
use App\Entity\InterfacMatch;
use App\Entity\MatchResult;
use App\Entity\ParticipantConfirmationInfo;
use App\Enum\BookingType;
use App\Enum\Side;
use App\Form\BookingForMatchForm;
use App\Form\MatchAdminConfirmationForm;
use App\Form\MatchResultForm;
use App\Form\Model\MatchConfirmationInfo;
use App\Repository\ParticipantConfirmationInfoRepository;
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

            $feedback = 'Résultat encodé';

            if ($request->query->get('modal')) {
                // sync reverse side
                $match->setResult($result);
                return $this->render('@admin/match/modal/add_result_success.html.twig', [
                    'feedback' => $feedback,
                    'slot' => $match->getSlot(),
                ]);
            }
            
            $this->addFlash('success', $feedback);
        
            return $this->redirectToRoute('admin_planning_planning');
        }

        if ($request->query->get('modal')) {
            return $this->render('@admin/match/modal/_add_result.html.twig', [
                'match' => $match,
                'form' => $form,
            ]);
        }

        return $this->render('@admin/match/add_result.html.twig', [
            'match' => $match,
            'form' => $form,
        ]);
    }

    #[Route('/match/{id:match}/confirmation-info', name: 'admin_match_confirmation_info', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function confirmationInfo(InterfacMatch $match, Request $request, EntityManager $entityManager, ParticipantConfirmationInfoRepository $repository): Response
    {
        // Ensure an info row exists for each participant
        $confirmationInfos = [];
        $wasConfirmedByAdmin = [];

        foreach ($match->getParticipantsForSide(Side::A) as $participant) {
            $info = $repository->findOneBy(['participant' => $participant]);
            if (!$info) {
                $info = (new ParticipantConfirmationInfo())->setParticipant($participant);
                $entityManager->persist($info);
                // update inverse for turbo stream
                $participant->setConfirmationInfo($info);
            }
            $confirmationInfos[] = $info;

            // keep track of initial value to check if it changed
            // Key by php object id (works for new + existing entities)
            // (new entities do not have a DB id yet bc not flushed yet)
            $oid = spl_object_id($info);
            $wasConfirmedByAdmin[$oid] = $info->isConfirmedByAdmin();
        }

        foreach ($match->getParticipantsForSide(Side::B) as $participant) {
            $info = $repository->findOneBy(['participant' => $participant]);
            if (!$info) {
                $info = (new ParticipantConfirmationInfo())->setParticipant($participant);
                $entityManager->persist($info);
                // update inverse for turbo stream
                $participant->setConfirmationInfo($info);
            }
            $confirmationInfos[] = $info;

            // keep track of initial value to check if it changed
            // Key by php object id (works for new + existing entities)
            // (new entities do not have a DB id yet bc not flushed yet)
            $oid = spl_object_id($info);
            $wasConfirmedByAdmin[$oid] = $info->isConfirmedByAdmin();
        }

        $dto = new MatchConfirmationInfo($confirmationInfos);

        $form = $this->createForm(MatchAdminConfirmationForm::class, $dto);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $admin = $this->getUser();
            $now  = new \DateTimeImmutable();

            foreach ($dto->getInfos() as $index => $info) {

                \assert($info instanceof ParticipantConfirmationInfo);

                // form value
                $row = $form->get('infos')->get((string) $index);
                $new = (bool) $row->get('isConfirmedByAdmin')->getData();

                // initial value
                $oid = spl_object_id($info);
                $initial = $wasConfirmedByAdmin[$oid] ?? false;
                    
                if (!$initial && $new) { // changed from false to true
                    $info->setIsConfirmedByAdmin(true);
                    $info->setAdmin($admin);
                    $info->setConfirmedByAdminAt($now);
                } elseif ($initial && !$new) { // changed from true to false
                    $info->setIsConfirmedByAdmin(false);
                    $info->setAdmin(null);
                    $info->setConfirmedByAdminAt(null);
                }

    
            }

            $this->entityManager->flush();

            $feedback = '';

            if ($request->query->get('modal')) {

                return $this->render('@admin/match/modal/confirmation_info_success.html.twig', [
                    'feedback' => $feedback,
                    'slot' => $match->getSlot(),
                ]);
            }
            
            $this->addFlash('success', $feedback);
        
            return $this->redirectToRoute('admin_planning_planning');
        }

        if ($request->query->get('modal')) {
            return $this->render('@admin/match/modal/_confirmation_info.html.twig', [
                'match' => $match,
                'form' => $form,
            ]);
        }

        return $this->render('@admin/match/confirmation_info.html.twig', [
            'match' => $match,
            'form' => $form,
        ]);
    }
}