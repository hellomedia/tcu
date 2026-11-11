<?php

namespace Admin\Controller\Planning;

use App\Controller\BaseController;
use App\Entity\Practice;
use App\Entity\Slot;
use App\Form\PracticeForm;
use App\Repository\PracticeRepository;
use App\Repository\SlotRepository;
use App\Service\SeasonContext;
use Doctrine\ORM\EntityManager;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

/**
 * Entraînements : réservation de créneaux pour que les joueurs de poules de niveau proche jouent librement.
 *
 * - page Entraînements (liste de la saison, formulaire pleine page)
 * - depuis le planning : bouton "Entraînement" d'un créneau libre (modale), menu d'un créneau réservé
 * - annuler (reste affiché "annulé") / supprimer (disparaît sans trace)
 */
class PracticeController extends BaseController
{
    public function __construct(
        private EntityManager $entityManager,
        private SeasonContext $seasonContext,
    ) {
    }

    #[AdminRoute('/planning/practices', name: 'practices')]
    public function index(PracticeRepository $repository): Response
    {
        $season = $this->seasonContext->getSelected();
        $practices = $season ? $repository->findBySeason($season) : [];

        $upcoming = [];
        $past = [];
        foreach ($practices as $practice) {
            if ($practice->isPast()) {
                $past[] = $practice;
            } else {
                $upcoming[] = $practice;
            }
        }

        return $this->render('@admin/practice/index.html.twig', [
            'season' => $season,
            'upcoming' => $upcoming,
            'past' => array_reverse($past),
        ]);
    }

    /**
     * Nouvel entraînement, depuis la page Entraînements ou depuis un créneau libre du planning (?slot=, ?modal=1)
     */
    #[AdminRoute('/planning/practice/new', name: 'practice_new')]
    public function new(Request $request, SlotRepository $slotRepository): Response
    {
        $practice = (new Practice())->setSeason($this->seasonContext->getSelected());
        $slot = $request->query->has('slot') ? $slotRepository->find($request->query->getInt('slot')) : null;

        return $this->handleForm($request, $practice, $slot);
    }

    #[AdminRoute('/planning/practice/{id:practice}/edit', name: 'practice_edit')]
    public function edit(Practice $practice, Request $request): Response
    {
        return $this->handleForm($request, $practice, null);
    }

    private function handleForm(Request $request, Practice $practice, ?Slot $slot): Response
    {
        $isNew = $practice->getId() === null;
        $oldSlots = $practice->getSlots();

        $form = $this->createForm(PracticeForm::class, $practice, [
            'slot' => $slot,
        ]);

        $form->handleRequest($request);

        $submitBtn = $form->get('save');
        \assert($submitBtn instanceof ClickableInterface);

        /* isClicked() : le changement de date ne soumet pas le formulaire */
        if ($submitBtn->isClicked() && $form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($practice);
            $this->entityManager->flush();

            $feedback = $isNew ? 'Entraînement programmé' : 'Entraînement modifié';

            if ($request->query->get('modal')) {
                return $this->render('@admin/practice/modal/save_success.html.twig', [
                    'feedback' => $feedback,
                    'practice' => $practice,
                    'slots' => $this->uniqueSlots([...$oldSlots, ...$practice->getSlots()]),
                ]);
            }

            $this->addFlash('success', $feedback);

            return $this->redirectToRoute('admin_practices');
        }

        return $this->renderForm($request, $practice, $form, $slot);
    }

    /**
     * @param Slot|null $slot créneau fixé (nouvel entraînement depuis le planning)
     */
    private function renderForm(Request $request, Practice $practice, FormInterface $form, ?Slot $slot): Response
    {
        $template = $request->query->get('modal')
            ? '@admin/practice/modal/_form.html.twig'
            : '@admin/practice/form.html.twig';

        return $this->render($template, [
            'practice' => $practice,
            'form' => $form,
            'slot' => $slot,
        ]);
    }

    /**
     * Annuler : reste affiché "annulé" aux joueurs (et en attente de notification pour ceux déjà prévenus),
     * les créneaux sont libérés. Définitif : pour revenir en arrière, programmer un nouvel entraînement.
     */
    #[IsCsrfTokenValid('practice-cancel', tokenKey: 'token')]
    #[AdminRoute('/planning/practice/{id:practice}/cancel', name: 'practice_cancel', options: ['methods' => ['POST']])]
    public function cancel(Practice $practice): Response
    {
        $slots = $practice->getSlots(); // libérés par l'annulation : lignes du planning à rafraîchir
        $practice->cancel();

        $this->entityManager->flush();

        return $this->render('@admin/practice/modal/save_success.html.twig', [
            'feedback' => 'Entraînement annulé',
            'practice' => $practice,
            'slots' => $slots,
        ]);
    }

    /**
     * Supprimer : disparaît sans trace, les créneaux sont libérés
     */
    #[IsCsrfTokenValid('practice-delete', tokenKey: 'token')]
    #[AdminRoute('/planning/practice/{id:practice}/delete', name: 'practice_delete', options: ['methods' => ['POST']])]
    public function delete(Practice $practice): Response
    {
        $slots = $practice->getSlots();
        $id = $practice->getId();

        $this->entityManager->remove($practice); // cascade : réservations et réponses
        $this->entityManager->flush();

        // les créneaux sont libres (côté inverse non mis à jour par la suppression)
        foreach ($slots as $slot) {
            $slot->setBooking(null);
        }

        return $this->render('@admin/practice/modal/delete_success.html.twig', [
            'feedback' => 'Entraînement supprimé',
            'practice_id' => $id,
            'slots' => $slots,
        ]);
    }

    /**
     * @param Slot[] $slots
     *
     * @return Slot[]
     */
    private function uniqueSlots(array $slots): array
    {
        $unique = [];
        foreach ($slots as $slot) {
            $unique[spl_object_id($slot)] = $slot;
        }

        return array_values($unique);
    }
}
