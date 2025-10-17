// remote_form_modal_controller.js
import { Controller } from "@hotwired/stimulus";

/**
 * 
 * USAGE
 * =====
 * 
 
    <a class="hk-dropdown-link"
        href="{{ path('admin_match_add_result', {'id': slot.match.id}) }}"
        data-action="modal-remote-form#open"    <==================
    >
        <twig:ux:icon name="add"/>
        Ajouter résultat
    </a>


 * 
 * 2 use cases:
 *      - Regular forms: no need for ajax controller. Turbo frames works great.
 *      - Forms with dependent fields ==> use 'ajax' controller
 * 
 
    Controller with remote form and Modal success message
    ======================================================
    
    public function addResult(InterfacMatch $match, Request $request): Response
    {
        ...

        $form = $this->createForm(MatchResultForm::class, $result);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $this->entityManager->persist($result);
            $this->entityManager->flush();

            $feedback = 'Success!';

            // modal response
            if ($request->query->get('modal')) {
                return $this->render('@admin/foo/modal/bar_success.html.twig', [
                    'feedback' => $feedback,
                    'slot' => $match->getSlot(),
                ]);
            }
            
            // full page response
            $this->addFlash('success', $feedback);
        
            return $this->redirectToRoute('admin_planning_groups');
        }

        // modal remote form
        if ($request->query->get('modal')) {
            return $this->render('@admin/match/modal/_add_result.html.twig', [
                'match' => $match,
                'form' => $form,
            ]);
        }

        // full page form
        return $this->render('@admin/match/add_result.html.twig', [
            'match' => $match,
            'form' => $form,
        ]);
    }

 */
export default class extends Controller {

    open(event) {
        event?.preventDefault();
        
        // A controller can host multiple links
        // For this event, the one of interest is currentTarget
        const button = event.currentTarget;

        this.baseModalController = this.application.getControllerForElementAndIdentifier(this.element, 'modal');

        this.baseModalController.createFromTemplate();
        
        const frame = document.getElementById("modal-frame");

        // Initially, set frame content to empty div to avoid flash of content
        frame.innerHTML = '<div style="height:250px;"></div>';

        // Load form into turbo-frame
        const url = new URL(button.href, window.location.href);
        url.searchParams.set('modal', '1'); // adds or updates ?modal=1
        // trigger ajax call to load url content
        if (frame) frame.src = url.toString();

        this.baseModalController.open();
    }

    /**
     * =================
     *  FORM SUBMISSION
     * =================
     */

    // ===========================
    // OPTION 1: TURBO FORM SUBMIT
    // ===========================
    //
    // NOTHING TO DO. Works out of the box if:
    //  - form is inside a turbo-frame
    //  - reply includes same turboframe
    //
    // ATTN: In easyadmin (data-turbo=false on body),
    // set 'data-turbo-frame' on the form to opt-in turbo handling


    // =======================================
    // OPTION 2: REGULAR NON-TURBO FORM SUBMIT
    // =======================================
    //
    // Might be useful for polished UX in forms with dependent fields.
    //
    // Use ajax_controller.js
}
