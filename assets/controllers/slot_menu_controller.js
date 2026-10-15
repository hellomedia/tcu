import { Controller } from "@hotwired/stimulus";

/**
 * Planning page : menu of a booked slot (see _slot_actions.html.twig).
 *
 * The menu is a native popover: it is rendered in the top layer, so the overflow of .date
 * never clips it, and Escape / a click outside close it. It stays in the DOM of the planning,
 * so its links keep reaching the modal controllers of the planning root.
 *
 * - Desktop: opened by the dots button, placed next to it, moved up to stay in the viewport.
 * - Phone: opened by a tap on the strip, styled as a bottom sheet (CSS), the dots are hidden.
 *
 * Attached to the .slot-row: rows replaced by turbo streams get a fresh controller.
 */
const PHONE_QUERY = "(max-width: 767.98px)";

export default class extends Controller {
    static targets = ["button", "menu"];

    connect() {
        this.boundHide = this.hide.bind(this);
    }

    disconnect() {
        this._removeListeners();
    }

    /**
     * Dots button. A click outside an open popover closes it before the click event (light dismiss):
     * pointerdown tells whether the menu was open, so that this click doesn't reopen it.
     */
    rememberState() {
        this.wasOpen = this._isOpen();
    }

    toggle() {
        const wasOpen = this.wasOpen ?? this._isOpen();
        this.wasOpen = null;

        wasOpen ? this.hide() : this.show();
    }

    /**
     * Phone: the whole strip opens the menu (except its own links, e.g. the confirmation dots)
     */
    openFromStrip(event) {
        if (!this._isPhone() || event.target.closest("a, button")) return;

        this.show();
    }

    show() {
        if (this._isOpen()) return;

        this.menuTarget.showPopover();
        this._place();
    }

    hide() {
        if (this._isOpen()) this.menuTarget.hidePopover();
    }

    /**
     * An action was chosen: its own handler (modal...) has already run, close the menu
     */
    closeOnItem(event) {
        if (event.target.closest("a, button")) this.hide();
    }

    beforeToggle(event) {
        const open = event.newState === "open";

        this.element.classList.toggle("menu-open", open);
        if (this.hasButtonTarget) this.buttonTarget.setAttribute("aria-expanded", open);

        if (open && !this._isPhone()) {
            // the menu is placed in the viewport: it would stay behind when the page or the date scrolls
            window.addEventListener("scroll", this.boundHide, { capture: true, passive: true });
            window.addEventListener("resize", this.boundHide);
        } else {
            this._removeListeners();
        }
    }

    /**
     * Desktop: left of the dots, top aligned with the row, moved up if it overflows the viewport
     */
    _place() {
        const style = this.menuTarget.style;

        if (this._isPhone() || !this.hasButtonTarget) {
            style.top = style.left = "";
            return;
        }

        const margin = 8;
        const viewportWidth = document.documentElement.clientWidth;
        const viewportHeight = window.innerHeight;
        const button = this.buttonTarget.getBoundingClientRect();
        const row = this.element.getBoundingClientRect();
        const menu = this.menuTarget.getBoundingClientRect();

        let left = button.left - menu.width - 4;
        if (left < margin) {
            left = Math.min(button.right - menu.width, viewportWidth - menu.width - margin);
        }

        let top = Math.min(row.top, viewportHeight - menu.height - margin);
        top = Math.max(margin, top);

        style.left = `${Math.max(margin, left)}px`;
        style.top = `${top}px`;
    }

    _isOpen() {
        return this.menuTarget.matches(":popover-open");
    }

    _isPhone() {
        return window.matchMedia(PHONE_QUERY).matches;
    }

    _removeListeners() {
        window.removeEventListener("scroll", this.boundHide, { capture: true });
        window.removeEventListener("resize", this.boundHide);
    }
}
