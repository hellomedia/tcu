// confirm_modal_controller.js
import { Controller } from "@hotwired/stimulus";

/**
 * ========================================
 *       CONFIRMATION MODAL --- USAGE
 * ========================================
 * 
 
    No form in the main page.

    - Remote form modal ==> form is fetched inside modal by modal_remote_form_controller.js

    - In-page button confirmation modal ==> form lives inside modal.
           
 
    CASE 1) Remote form in modal + confirm

        See example in remote_form_controller.js


    CASE 2) In-page button with confirm modal:

        <div data-controller="modal modal-confirm"> 

            <button type="button"
                formaction="{{ path('route') }}"
                data-action="modal-confirm#open"
                data-title="Supprimer les matchs"
                data-html="<p>Attention, les matchs existants seront supprimés!</p>"
                data-button-label="Supprimer les matchs"
                data-variant="warning"
                data-csrf="{{ csrf_token('delete-match') }}"
            >
                <twig:ux:icon name="delete" />
                Supprimer les matchs
            </button>

            {% include 'component/modal/_modal.html.twig' %}
            {% include 'component/modal/_icons_templates.html.twig' %}

        </div>

    
    button click ------> open confirmation modal
    
    inside confirmation modal:
        - turbo-frame
        - form
    
    confirm button click
        ----> Turbo submit inside <turbo-frame id="modal-frame" data-turbo="true">
        ----> response must include same turbo frame (see below)
 

    Controller:
     
        if ($request->query->get('modal')) {
            return $this->render('component/modal/success.html.twig', [
                'feedback' => $feedback,
                'slot' => $match->getSlot(),
            ]);
        }

    In addition to displaying the confirmation message,
    we can also add turbo **streams** to replace other page elements
    by targetting them with their id. It fires automatically.

        if ($request->query->get('modal')) {
            return $this->render('@admin/foo/modal/bar_success.html.twig', [
                'feedback' => $feedback,
                'slot' => $match->getSlot(),
            ]);
        }

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
    static targets = ["icon", "title", "details", "token", "confirmButton", "confirmButtonLabel", "cancelButtonLabel"];

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

        if ("buttonLabel" in btn.dataset) {
            this.confirmButtonLabelTarget.textContent = btn.dataset.buttonLabel;
        }

        if ("cancelLabel" in btn.dataset) {
            this.cancelButtonLabelTarget.textContent = btn.dataset.cancelLabel;
        }

        if (variant == 'warning') {
            this.confirmButtonTarget.classList.remove('btn-primary');
            this.confirmButtonTarget.classList.add('btn-danger');
        }

        if ("csrf" in btn.dataset) {
            this.tokenTarget.value = btn.dataset.csrf;
        }

        // Add formaction to modal submit button
        const url = new URL(btn.getAttribute('formaction'), window.location.href);
        url.searchParams.set('modal', '1'); // adds or updates ?modal=1
        this.confirmButtonTarget.setAttribute('formaction', url);
    }
}
