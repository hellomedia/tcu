<?php

namespace Admin\Controller\Planning;

use Admin\Controller\DashboardController;
use App\Controller\BaseController;
use App\Entity\Booking;
use App\Entity\InterfacMatch;
use App\Entity\MatchResult;
use App\Entity\ParticipantConfirmationInfo;
use App\Entity\Slot;
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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

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
        if ($match->isScheduled()) {
            return $this->redirectToRoute('admin_match_move_booking', ['id' => $match->getId(), 'modal' => $request->query->get('modal')]);
        }

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
                $form->get('slot')->addError(new FormError('Veuillez sélectionner un créneau'));

                return $this->renderBookingForm($request, $match, $form, mode: 'add');
            }

            $slot = $booking->getSlot();
            $slot->setBooking($booking); // sync inverse side (for the turbo stream)

            $this->entityManager->persist($booking);
            $this->entityManager->flush();

            $feedback = 'Match programmé';

            if ($request->query->get('modal')) {
                return $this->render('@admin/match/modal/booking_success.html.twig', [
                    'feedback' => $feedback,
                    'match' => $match,
                    'slots' => [$slot],
                ]);
            }

            $this->addFlash('success', $feedback);

            return $this->redirectToRoute('admin_planning_groups');
        }

        return $this->renderBookingForm($request, $match, $form, mode: 'add');
    }

    /**
     * Move booking of a scheduled match = change its slot (date, time and/or court)
     *
     * Player confirmations are reset when the date or the time changes
     * (they are kept when only the court changes)
     */
    #[Route('/match/{id:match}/move-booking', name: 'admin_match_move_booking', defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function moveBooking(InterfacMatch $match, Request $request): Response
    {
        $booking = $match->getBooking();

        if ($booking === null) {
            return $this->redirectToRoute('admin_match_add_booking', ['id' => $match->getId(), 'modal' => $request->query->get('modal')]);
        }

        $oldSlot = $booking->getSlot();

        $form = $this->createForm(BookingForMatchForm::class, $booking, [
            'current_slot' => $oldSlot,
        ]);

        $form->handleRequest($request);

        $submitBtn = $form->get('save');
        assert($submitBtn instanceof ClickableInterface);

        /* isClicked() avoids submitting when updating dependent field */
        if ($submitBtn->isClicked() && $form->isSubmitted() && $form->isValid()) {

            $newSlot = $booking->getSlot();

            if ($newSlot === null) {
                $booking->setSlot($oldSlot);
                $form->get('slot')->addError(new FormError('Veuillez sélectionner un créneau'));

                return $this->renderBookingForm($request, $match, $form, mode: 'move');
            }

            $this->applyMove($match, $oldSlot, $newSlot);

            $feedback = 'Match déplacé';

            if ($request->query->get('modal')) {
                return $this->render('@admin/match/modal/booking_success.html.twig', [
                    'feedback' => $feedback,
                    'match' => $match,
                    'slots' => [$oldSlot, $newSlot],
                ]);
            }

            $this->addFlash('success', $feedback);

            return $this->redirectToRoute('admin_planning_groups');
        }

        return $this->renderBookingForm($request, $match, $form, mode: 'move');
    }

    /**
     * Move a scheduled match to a given free slot, without form : drag & drop on the planning
     *
     * - with ?modal=1 (confirmation modal) : success modal + turbo streams
     * - otherwise (direct fetch) : turbo streams only
     */
    #[IsCsrfTokenValid('move-booking', tokenKey: 'token')]
    #[Route('/match/{id:match}/move-booking-to/{slot}', name: 'admin_match_move_booking_to', methods: ['POST'], defaults: [EA::DASHBOARD_CONTROLLER_FQCN => DashboardController::class])]
    public function moveBookingTo(
        InterfacMatch $match,
        #[MapEntity(mapping: ['slot' => 'id'])]
        Slot $slot,
        Request $request,
    ): Response {
        $booking = $match->getBooking();

        if ($booking === null) {
            return new Response('Ce match n\'est pas programmé', Response::HTTP_CONFLICT);
        }

        $oldSlot = $booking->getSlot();

        if ($slot !== $oldSlot && $slot->getBooking() !== null) {
            return new Response('Ce créneau vient d\'être réservé', Response::HTTP_CONFLICT);
        }

        if ($slot->getDate()->isPast()) {
            return new Response('Ce créneau est passé', Response::HTTP_CONFLICT);
        }

        $this->applyMove($match, $oldSlot, $slot);

        $slots = [$oldSlot, $slot];

        if ($request->query->get('modal')) {
            return $this->render('@admin/match/modal/booking_success.html.twig', [
                'feedback' => 'Match déplacé',
                'match' => $match,
                'slots' => $slots,
            ]);
        }

        $response = $this->render('@admin/match/_booking_streams.html.twig', [
            'match' => $match,
            'slots' => $slots,
        ]);
        $response->headers->set('Content-Type', 'text/vnd.turbo-stream.html; charset=utf-8');

        return $response;
    }

    /**
     * Change the slot of a scheduled match.
     * Player confirmations are reset when the date or the time changes (kept when only the court changes).
     */
    private function applyMove(InterfacMatch $match, Slot $oldSlot, Slot $newSlot): void
    {
        if ($newSlot === $oldSlot) {
            return;
        }

        $booking = $match->getBooking();

        // The slots may be lazy proxies. Initializing one AFTER the booking has been changed would undo
        // the change: when Doctrine loads a slot it completes the bidirectional one-to-one by setting
        // the owning side (booking.slot) back to that slot.
        $this->entityManager->initializeObject($oldSlot);
        $this->entityManager->initializeObject($newSlot);

        $booking->setSlot($newSlot);

        // sync inverse sides (for the turbo streams)
        $oldSlot->setBooking(null);
        $newSlot->setBooking($booking);

        $sameMoment = $newSlot->getDate() === $oldSlot->getDate()
            && $newSlot->getStartsAt() == $oldSlot->getStartsAt();

        if (!$sameMoment) {
            foreach ($match->getConfirmationInfos() as $info) {
                $this->entityManager->remove($info);
                $info->getParticipant()->setConfirmationInfo(null);
            }
        }

        $this->entityManager->flush();
    }

    private function renderBookingForm(Request $request, InterfacMatch $match, FormInterface $form, string $mode): Response
    {
        $template = $request->query->get('modal')
            ? '@admin/match/modal/_booking.html.twig'
            : '@admin/match/booking.html.twig';

        return $this->render($template, [
            'match' => $match,
            'form' => $form,
            'mode' => $mode,
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
        
            return $this->redirectToRoute('admin_planning');
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
        
            return $this->redirectToRoute('admin_planning');
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