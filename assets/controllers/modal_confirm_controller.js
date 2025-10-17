// confirm_modal_controller.js
import { Controller } from "@hotwired/stimulus";

/**
 * ========================================
 *               SUMMARY
 * ========================================
 * 
 * No form in the page.
 *    - For remote form, form is fetched inside modal by modal_remote_form_controller.js
 *    - For a simple action confirmation, Form lives inside modal template.
 *          
 
    CASE 1) Remote form in modal + confirm

        See example in remote_form_controller.js


    CASE 2) Simple action with confirm modal:

        <div data-controller="modal modal-confirm"> 

            <button type="button"
                formaction="{{ path('route') }}"
                data-action="modal-confirm#open"   <============
                data-title="Supprimer les matchs"
                data-html="<p>Attention, les matchs existants seront supprimés!</p>"
                data-button-label="Supprimer les matchs"
                data-variant="warning"
            >
                <twig:ux:icon name="delete" />
                Supprimer les matchs
            </button>

            {% include 'component/modal/_modal.html.twig' %}
            {% include 'component/modal/_icons_templates.html.twig' %}

        </div>

 * 
 * button click ------> open confirmation modal
 * 
 * inside confirmation modal:
 *      - turbo-frame
 *      - form
 * 
 * confirm button click ----> 
 *      A) Turbo submit ----> response includes turbo frame, replaces existing turbo frame
 *      B) Turbo not active on page (easyadmin : data-turbo=false on body) 
 *           ----> Add 'data-turbo-frame' attribute on form to opt-in turbo handling
 * 
 

 * Controller:
 * 
 
        if ($request->query->get('modal')) {
            return $this->render('@admin/foo/modal/bar_success.html.twig', [
                'feedback' => $feedback,
                'slot' => $match->getSlot(),
            ]);
        }


 * In server reponse, we can include a turbo stream to replace page elements
 * by simply targetting them with their id. It fires automatically.
 * 
    {# bar_success.html.twig #}
    {% embed 'component/modal/success.html.twig' %}
        {% block streams %}
            <turbo-stream action="replace" target="slot-{{ slot.id }}">
                <template>
                    {% include '@admin/planning/_slot_row.html.twig' %}
                </template>
            </turbo-stream>
        {% endblock %}
    {% endembed %}

 * ==========================================
 * 
 * Modal controller must be attached to parent element, wrapping:
 *  - trigger buttons
 *  - #modal-template element
 * NB: #modal-template must be outside of trigger buttons
 * to avoid conflict with turbo-frame updates
 */

export default class extends Controller {
    static targets = ["icon", "title", "details", "confirmButton", "confirmButtonLabel"];

    open(event) {
        event.preventDefault();

        const baseModalController = this.application.getControllerForElementAndIdentifier(this.element, 'modal');

        baseModalController.createFromTemplate();

        this.setModalContent(event);

        baseModalController._generateAccessibilityTags();

        baseModalController.open();

        this.confirmButtonTarget.focus();
    }

    setModalContent(event) {

        // A controller can host multiple buttons
        // For this event, the one of interest is currentTarget
        const btn = event.currentTarget;
        const variant = btn.dataset.variant ?? "question";

        if ("title" in btn.dataset) {
            this.titleTarget.textContent = btn.dataset.title;
        }
        if ("html" in btn.dataset) {
            this.detailsTarget.innerHTML = btn.dataset.html;
            this.detailsTarget.style.display = "";
        } else {
            this.detailsTarget.style.display = "none";
        }

        // Inject icon from template based on type
        const iconTemplate = document.getElementById(`modal-icon-${variant}`);
        this.iconTarget.innerHTML = '';
        if (iconTemplate?.content) {
            this.iconTarget.appendChild(iconTemplate.content.cloneNode(true));
        }

        this.confirmButtonLabelTarget.textContent = btn.dataset.buttonLabel;

        if (variant == 'warning') {
            this.confirmButtonTarget.classList.remove('btn-primary');
            this.confirmButtonTarget.classList.add('btn-danger');
        }

        // add formaction to modal submit button
        // The form we submit must live inside a turbo-frame
        // to avoid turbo error "form submit must redirect" when submitting a form outside a turbo-frame
        const url = new URL(btn.getAttribute('formaction'), window.location.href);
        url.searchParams.set('modal', '1'); // adds or updates ?modal=1
        this.confirmButtonTarget.setAttribute('formaction', url);

        /* FROM CHATGPT. might be useful if we run into turbo issues
        // Wire the form + submit button
        const form = this.element.querySelector("dialog.modal-container form.modal-inner-layout");
        if (form) {
            const action = btn.getAttribute("formaction") ?? "";
            form.setAttribute("action", action); // helps Turbo path
            // non-Turbo path: rely on your embed to attach data-action="modal#submitForm" when needed
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) submitBtn.setAttribute("formaction", action);
        }*/
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
    // Set data-turbo-frame on the form if turbo is not active on the page (easy admin).
    // Otherwise, nothing to do. It works out of the box is the form is inside a turbo-frame.
    // And we reply with the same turbo-frame.
    //
    // Let turbo handle modal form submission and server response
    // Server responds with turboframe (optionally including turbostreams)
    // to replace modal turboframe
    // Used in:
    //  - confirmation modal
    //  - in TCU admin with remote form

    // =======================================
    // OPTION 2: REGULAR NON-TURBO FORM SUBMIT
    // =======================================
    //
    //  use 'data-action': 'submit->modal-confirm#submit' to replicate turbo submit
    //  It will add 'data-turbo-frame' attribute on form to activate turbo-frame if full turbo is not active
    //

    /**
     * Replicate turbo submit
     */
    async submit(event) {
        event.preventDefault();

        await this._submitForm(event);
    }

    async _submitForm(event) {

        console.log(event);

        // const baseModalController = this.application.getControllerForElementAndIdentifier(this.element, 'modal');
        // const form = baseModalController.dialogTarget.querySelector('form');
        // const btn = event.currentTarget;

        const form = event.target;
        const submitter = event.submitter;

        if (!form) return;
        
        form.setAttribute('data-turbo-frame', 'modal-frame'); 

        // Use requestSubmit to pass the clicked button so its name=value is sent
        form.requestSubmit(submitter);
    }
}
