// remote_form_modal_controller.js
import { Controller } from "@hotwired/stimulus";

/**
 * 
 * 
    ==============================================
            REMOTE FORM MODAL ---- USAGE
    ==============================================
     
    In-page link to open modal:

        <a class="hk-dropdown-link"
            href="{{ path('admin_match_add_result', {'id': slot.match.id}) }}"
            data-action="modal-remote-form#open"
        >
            <twig:ux:icon name="add"/>
            Ajouter résultat
        </a>


    2 use cases:

      A) Regular forms: no need for ajax controller. Turbo frame works great.

      B) Forms with dependent fields ==> use 'ajax' controller
 

 
     Controller with remote form and Modal success message
    =======================================================
    
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


     Form partial
    ==============

    {# _add_results.html.twig #}

    {% form_theme form with ['@admin/form/form_theme.html.twig'] only %}

    <turbo-frame id="modal-frame" data-turbo="true">{# data-turbo="true" for easy-admin #}

        {{ form_start(form, {
            'attr': {
                'action': path('admin_match_add_result', {'id': match.id}),
            }
        }) }}

            {{ form_rest(form) }}

            {% include 'component/modal/_actions.html.twig' %}
        
        </form>

    </turbo-frame>

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
    // Works out of the box if:
    //  - form lives inside an active turbo-frame (data-turbo="true" necessary in easy-admin)
    //  - reply includes same turbo-frame


    // =======================================
    // OPTION 2: REGULAR NON-TURBO FORM SUBMIT
    // =======================================
    //
    // Might be useful for polished UX in forms with dependent fields.
    //
    // Use ajax_controller.js
}
