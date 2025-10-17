import { Controller } from "@hotwired/stimulus";

/**
 * Provides BASE MODAL BEHAVIOUR:
 *  - opening / closing
 *  - scroll handling
 *  - accessibility
 * 
 * Use together with:
 *  - modal_confirm_controller.js 
 *  - modal_remote_form_controller.js
 *  - ajax_controller.js (probably only if form with dependent fields)
 * 
 * ---------------------
 * 
 *  SECURITY:
 * - POST request for modifying state
 * - No need for CSRF token if samesite cookie ='lax' and method=POST,
 *  since cookies are not shared with third party origin for POST requests when samesite="lax".
 *  https://simonwillison.net/2021/Aug/3/samesite/
 *
 */

export default class extends Controller {
    static targets = ["dialog", "modal"];

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

    connect() {

        this.uniqueId = 0;

        this.keydownHandler = this._handleKeydown.bind(this);
        this.boundTrapFocus = this._trapFocus.bind(this);
        this.boundFrameLoad = this._addModalQueryParam.bind(this);

        document.addEventListener("keydown", this.keydownHandler);

        document.addEventListener('turbo:frame-load', this.boundFrameLoad);
    }

    disconnect() {
        document.removeEventListener("keydown", this.keydownHandler);
        document.removeEventListener("turbo:frame-load", this.boundFrameLoad);

        // not necessary when this.modal lives inside element being disconnected
        // which is the case : this.element.appendChild(this.modal);
        this.modal?.remove();
    }

    /**
     * =====================
     *    template cloning
     * =====================
     */
    createFromTemplate() {

        this.uniqueId++;

        const dialogTemplate = document.getElementById("dialog-template");
        this.modal = dialogTemplate.content.firstElementChild.cloneNode(true);

        // Mark as dynamic so we can safely remove it on close
        this.modal.dataset.dynamic = "1";
        this.element.appendChild(this.modal);

        return this.modal;
    }

    /**
     * =======================================
     *  public API used by child controllers
     * =======================================
     */
    open() {
        this.dialogTarget.showModal();
        this.dialogTarget.classList.remove("modal-hide");
        this.dialogTarget.classList.add("modal-show");
        this._hideScrollbar();
        this._setFocusableElements();
        this.modalTarget.addEventListener("keydown", this.boundTrapFocus);
        this.dispatch("opened");
    }

    close() {

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

        // If this.modal cloned from template, remove
        if (this.modal?.dataset.dynamic === "1") {
            this.modal.remove();
        }

        this.dispatch("closed");
    }

    clickOutside(e) {
        if (e.target === this.dialogTarget) this.close();
    }


    /**
     * =======================
     *  ADD MODAL QUERY PARAM
     * =======================
     * Add 'modal' query param to 'action' attribute on forms loaded via turbo-frame
     */
    _addModalQueryParam(event) {
        const frame = event.target;
        frame.querySelectorAll('form').forEach((form) => {
            const u = new URL(form.getAttribute('action') || window.location.href, window.location.href);
            u.searchParams.set('modal', '1');
            form.action = u.toString();
        });
    }

    /**
     * ===============
     *  SCROLL HELPERS
     * ===============
     */
    
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

    _generateAccessibilityTags(titleElement) {

        if (!titleElement) return;

        const titleId = 'modal-' + this.uniqueId + '-title';

        titleElement.id = titleId;
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
