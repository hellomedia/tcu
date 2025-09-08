import { Controller } from '@hotwired/stimulus';

/**
 * ========================================
 *               SUMMARY
 * ========================================
 * 
 * No need for a form in the page.
 * Form lives inside modal template.
 *                 
    <button type="button"
        formaction="{{ path('route') }}"
        data-action="modal#open"
        data-title="Supprimer les matchs"
        data-html="<p>Attention, les matchs existants seront supprimés!</p>"
        data-button-label="Supprimer les matchs"
        data-variant="warning"
    >
        <twig:ux:icon name="delete" />
        Supprimer les matchs
    </button>

 * 
 * button click ------> open confirmation modal
 * 
 * inside confirmation modal:
 *      - turbo-frame
 *      - form 
 * 
 * confirm button click ----> 
 *      A) Turbo submit ----> response includes turbo frame, replaces existing turbo frame
 *      B) No turbo (easy admin) ----> submitForm() replicates turbo submit
 * 
 * In server reponse, we can include a turbo stream to replace page elements
 * by simply targetting them with their id. It fires automatically.
 * 
 * ==========================================
 * 
 * For confirm modals:
 * Modal controller must be attached to parent element wrapping:
 *  - trigger buttons
 *  - #modal-template element
 * knowing that #modal-template must be outside of the items with trigger buttons
 * in order to not conflict with item updates with turbo-streams
 * 
 *  SECURITY:
 * - POST request for modifying state
 * - No need for CSRF token if samesite cookie ='lax' and method=POST,
 *  since cookies are not shared with third party origin for POST requests when samesite="lax".
 *  https://simonwillison.net/2021/Aug/3/samesite/
 *
 */
export default class extends Controller {
    static targets = ['dialog', 'modal', 'icon', 'title', 'details', 'confirmButton', 'confirmButtonLabel'];

    static values = {
        type: String, // see this.type
    }

    // Modal / Dialog element
    // =======================
    // For confirm modals:
    //  - Unique modal reused by all trigger buttons
    //  - created from #modal-template when open() is called
    //  - Removed on close() and disconnect()
    // Lives outside of items to simplify item updates with turbo-stream
    modal;

    // Unique Id per modal instance
    // Used for accessibility attributes
    // On a page, multiple instances can be created from template element
    // On every trigger button click, a new instance is created
    uniqueId;

    // Modal Type
    // ==========
    // 'confirm': confirmation modal for an action button (publish, unpublish, archive, delete...)
    // 'form': modal hosting a form (report, edit, ...)
    // Type can be defined globally as a value on the controller root element (data-modal-type-value)
    // and can be overriden per button as a button data attribute (data-type)
    type;

    // Clicked modal trigger button
    // A controller can host multiple buttons
    button;

    // Clicked modal trigger button dataset
    dataset;

    // Focussable elements inside opened modal
    focusableElements;

    connect() {

        this.uniqueId = 0;

        this.keydownHandler = this._handleKeydown.bind(this);
        this.boundTrapFocus = this._trapFocus.bind(this);
        
        document.addEventListener("keydown", this.keydownHandler);
    }

    disconnect() {
        document.removeEventListener("keydown", this.keydownHandler);
        
        // not necessary when this.modal lives inside element being disconnected
        // which is the case : this.element.appendChild(this.modal);
        this.modal?.remove();
    }

    open(event) {

        event.preventDefault();

        this.uniqueId ++;

        // A controller can host multiple buttons
        // For this event, the one of interest is currentTarget
        this.button = event.currentTarget;
        this.dataset = this.button.dataset;
        
        // type = global value unless overriden per button
        this.type = this.dataset.type ?? this.typeValue;

        if (this.type == 'confirm') {
            this._createConfirmationModal(event);
        }

        this._generateAccessibilityTags();

        // type 'confirm': dialogTarget is created above in createConfirmationModal()
        // type 'form': dialogTarget exists in the dom at page load
        this.dialogTarget.showModal();

        // animations
        this.dialogTarget.classList.remove('modal-hide');
        this.dialogTarget.classList.add('modal-show');

        this._hideScrollbar();

        // We could use the inert attribute to disable the rest of the page (except dialog)
        // It makes things no-selectable, non-tabbable, etc
        // https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Global_attributes/inert
        // but it behaves strangely when added to body element
        // so it needs a wrapper div around the full page.
        // We don't use that in our layout so I will use the "trap focus" bits below
        // document.getElementById('page-wrapper').inert = true;

        if (this.type == 'confirm') {
            this.confirmButtonTarget.focus();
        }
        
        // Trap focus
        // Could be replaced by 'inert' attribute on page-wrapper (see above)
        this._setFocusableElements();
        this.modalTarget.addEventListener('keydown', this.boundTrapFocus);
    }

    close(event) {

        if (this.hasDialogTarget == false) return;

        // animations
        this.dialogTarget.classList.remove('modal-show');
        this.dialogTarget.classList.add('modal-hide'); // start 150ms closing animation

        this.modalTarget.removeEventListener('keydown', this.boundTrapFocus);
        
        setTimeout(() => this._doClose(), 100);
        
        this._restoreScrollbar();

        document.removeEventListener("keydown", this.keydownHandler);
    }

    _doClose() {
        this.dialogTarget.close();

        // remove dynamically created modal
        // if modal present in dom at page load (type 'form'), do not remove
        if (this.modal) this.modal.remove();
    }

    clickOutside(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    // OPTION 1: TURBO FORM SUBMIT
    // ===========================
    // Nothing to do
    // Let turbo handle modal form submission and server response
    // Server responds with turboframe (optionally including turbostreams)
    // to replace modal turboframe

    // OPTION 2: REGULAR NON-TURBO FORM SUBMIT
    // =======================================
    // Handle form submission and server response
    // In our easyadmin setup, we do this
    async submitForm(event) {
        event.preventDefault();

        const form = this.dialogTarget.querySelector('form');
        const button = event.currentTarget;

        if (!form) {
            return;
        }

        try {
            const response = await fetch(button.getAttribute('formaction'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'text/html', // expecting HTML fragment maybe
                },
                body: new URLSearchParams(new FormData(form)),
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const html = await response.text();

            // REPLACE the form inside the modal
            form.outerHTML = html;

        } catch (error) {
            throw new Error('Error sending the report');
        }
    }

    _createConfirmationModal(event) {
        
        // creating a new dialog will register targets inside it
        this._createNewDialogFromTemplate();

        const variant = this.dataset.variant ?? 'question';

        this.titleTarget.textContent = this.dataset.title;
        this.confirmButtonLabelTarget.textContent = this.dataset.buttonLabel;

        if ('html' in this.dataset) {
            this.detailsTarget.innerHTML = this.dataset.html;
        } else {
            this.detailsTarget.style.display = 'none';
        }

        if (variant == 'warning') {
            this.confirmButtonTarget.classList.remove('btn-primary');
            this.confirmButtonTarget.classList.add('btn-danger');
        }

        // add formaction to modal submit button
        // The form we submit must live inside a turbo-frame
        // to avoid turbo error "form submit must redirect" when submitting a form outside a turbo-frame
        this.confirmButtonTarget.setAttribute('formaction', this.button.getAttribute('formaction'));

        // Inject icon from template based on type
        const iconTemplate = document.getElementById(`modal-icon-${variant}`);
        this.iconTarget.innerHTML = '';
        if (iconTemplate?.content) {
            this.iconTarget.appendChild(iconTemplate.content.cloneNode(true));
        }
    }

    _createNewDialogFromTemplate() {
        const dialogTemplate = document.getElementById('dialog-template');
        this.modal = dialogTemplate.content.firstElementChild.cloneNode(true);
        this.element.appendChild(this.modal);
    }

    _hideScrollbar() {
        document.documentElement.style.overflow = 'hidden'; // html
        document.body.style.overflow = 'hidden'; // body
        document.documentElement.style.paddingRight = `${this._getScrollbarWidth()}px`; // scrollbar width
    }

    _restoreScrollbar() {
        document.documentElement.style.overflow = 'auto'; // html
        document.body.style.overflow = 'auto'; // body
        document.documentElement.style.paddingRight = 0; // scrollbar width
    }

    /** 
     * robust way to get scrollbar width 
     */
    _getScrollbarWidth() {
        const outer = document.createElement('div');

        outer.style.visibility = 'hidden';
        outer.style.overflow = 'scroll';
        outer.style.position = 'absolute';
        outer.style.top = '-9999px';
        outer.style.width = '100px';

        document.body.appendChild(outer);

        const inner = document.createElement('div');
        inner.style.width = '100%';
        outer.appendChild(inner);

        const scrollbarWidth = outer.offsetWidth - inner.offsetWidth;

        outer.remove();

        return scrollbarWidth;
    }

    /**
     * =============
     * ACCESSIBILITY
     * =============
     */

    _handleKeydown(event) {
        if (event.key === "Escape") {
            event.preventDefault();
            this.close();
        }
    }

    _generateAccessibilityTags() {

        if (this.hasTitleTarget == false) return;

        const titleId = 'modal-' + this.uniqueId + '-title';

        this.titleTarget.id = titleId;
        this.modalTarget.setAttribute('aria-labelledby', titleId);
    }

    _setFocusableElements() {
        this.focusableElements = this.modalTarget.querySelectorAll(this._focusableSelectors);
    }

    /**
     * Trap focus inside modal
     * When tabbing away from last element, focus goes back to first element
     */
    _trapFocus(event) {
        if (event.key !== 'Tab') return;

        if (this.focusableElements.length === 0) return;

        const first = this.focusableElements[0];
        const last = this.focusableElements[this.focusableElements.length - 1];

        if (event.shiftKey) { // Shift + Tab
            if (document.activeElement === first) {
                event.preventDefault();
                last.focus();
            }
        } else { // Tab
            if (document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    }

    get _focusableSelectors() {
        return `
      a[href],
      area[href],
      input:not([disabled]):not([type="hidden"]),
      select:not([disabled]),
      textarea:not([disabled]),
      button:not([disabled]),
      iframe,
      object,
      embed,
      [tabindex]:not([tabindex="-1"]),
      [contenteditable]
    `;
    }
}