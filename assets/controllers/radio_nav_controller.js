import { Controller } from "@hotwired/stimulus";

/**
 * Keyboard navigation in a group of radios rendered as pills / cards / grid cells
 * (booking forms, see @admin/form/form_theme.html.twig).
 *
 *  <div data-controller="radio-nav" data-radio-nav-submit-value="true">
 *      <label><input type="radio" ...></label> ...
 *  </div>
 *
 * - Left / Right : previous / next radio in DOM order
 * - Up / Down    : nearest radio on the previous / next visual row (works for a 2-column card grid
 *                  as well as for the time x court table, where booked cells have no radio)
 * - Enter        : submit the form when 'submit' is true (dependent field: match, slot),
 *                  ignored otherwise (trigger field: group, date, whose change reloads the form)
 *
 * Moving checks the radio, like native radio groups do, so a change event is dispatched.
 */
export default class extends Controller {
    static values = {
        submit: { type: Boolean, default: false },
    };

    connect() {
        this.onKeydown = this.onKeydown.bind(this);
        this.element.addEventListener("keydown", this.onKeydown);
    }

    disconnect() {
        this.element.removeEventListener("keydown", this.onKeydown);
    }

    onKeydown(event) {
        const current = event.target;
        if (!(current instanceof HTMLInputElement) || current.type !== "radio") return;

        switch (event.key) {
            case "ArrowLeft":
            case "ArrowRight":
                event.preventDefault();
                this._moveTo(this._sibling(current, event.key === "ArrowRight" ? 1 : -1));
                break;
            case "ArrowUp":
            case "ArrowDown":
                event.preventDefault();
                this._moveTo(this._vertical(current, event.key === "ArrowDown" ? 1 : -1));
                break;
            case "Enter":
                event.preventDefault();
                if (this.submitValue && current.checked) {
                    this._submit(current.form);
                }
                break;
        }
    }

    get radios() {
        return [...this.element.querySelectorAll('input[type="radio"]:not([disabled])')];
    }

    _sibling(current, direction) {
        const radios = this.radios;
        const index = radios.indexOf(current);
        if (index === -1) return null;

        return radios[(index + direction + radios.length) % radios.length];
    }

    /**
     * Nearest radio on the next / previous visual row (compared on the label's box)
     */
    _vertical(current, direction) {
        const box = (radio) => (radio.closest("label") ?? radio).getBoundingClientRect();
        const from = box(current);
        const fromCenter = from.left + from.width / 2;

        let best = null;
        let bestDistance = Infinity;

        for (const radio of this.radios) {
            if (radio === current) continue;
            const rect = box(radio);
            const rowDelta = direction > 0 ? rect.top - from.bottom : from.top - rect.bottom;
            if (rowDelta < -1) continue; // not on a following / preceding row

            const center = rect.left + rect.width / 2;
            // rows first, then horizontal proximity
            const distance = rowDelta * 10000 + Math.abs(center - fromCenter);
            if (distance < bestDistance) {
                bestDistance = distance;
                best = radio;
            }
        }

        return best;
    }

    _moveTo(radio) {
        if (!radio) return;

        radio.focus();
        if (!radio.checked) {
            radio.click(); // checks + dispatches input/change like a native radio group
        }
    }

    _submit(form) {
        if (!form) return;

        const submitter = form.querySelector('button[type="submit"], input[type="submit"]');
        form.requestSubmit(submitter ?? undefined);
    }
}
