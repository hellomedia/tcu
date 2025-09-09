import { Controller } from "@hotwired/stimulus";

/*

1) Ajax on full form submit
===========================

    <div class="form-wrapper" data-controller="ajax">
        {{ form_start(form, {
            'attr': {
                'data-ajax-target': 'form',
            }
        }) }}
            <div data-ajax-target="container">
                {{ form_rest(form) }}
            </div>
            {% include '@admin/form/_ajax_button.html.twig' %}
        </form>
    </div>

2) Ajax only on change of dependent fields (not full form)
=======================================================

NB: novalidate allows to trigger submit if the depedent field
is required and not filled (changed multiple times)

    <div class="form-wrapper"
        data-controller="ajax"
        data-ajax-full-ajax-submit-value="false"
    >
        {{ form_start(form, {
            'attr': {
                'novalidate': 'novalidate',
                'data-ajax-target': 'form',
            }
        }) }}
            <div data-ajax-target="container">
                {{ form_rest(form) }}
            </div>
            {% include '@admin/form/_ajax_button.html.twig' %}
        </form>
    </div>

3) Ajax on change of dependent fields + full form submit
========================================================

NB: novalidate allows to trigger submit if the depedent field
is required and not filled (changed multiple times)

    <div class="form-wrapper" data-controller="ajax">
        {{ form_start(form, {
            'attr': {
                'novalidate': 'novalidate',
                'data-ajax-target': 'form',
            }
        }) }}
            <div data-ajax-target="container">
                {{ form_rest(form) }}
            </div>
            {% include '@admin/form/_ajax_button.html.twig' %}
        </form>
    </div>

2) and 3) Inputs that should trigger Ajax on change
===================================================

<select data-action="change->ajax#submitOnChange">...</select>
<input type="checkbox" data-action="change->ajax#submitOnChange">

Submit button overrides
=======================

<!-- Full page (or Turbo) submit -->
<button type="submit" data-ajax="full">Save</button>

<!-- Force AJAX submit even if fullAjaxSubmit=false -->
<button type="submit" data-ajax="ajax">Save (AJAX)</button>

<!-- Force hard reload (no Turbo) -->
<button type="submit" data-ajax="full" data-hard="true">Save & Reload</button>
 */

export default class extends Controller {
    static targets = ["form", "container", "spinner", "submitBtn"];
    static values = {
        debounce: { type: Number, default: 250 },
        fullAjaxSubmit: { type: Boolean, default: true }
    };

    connect() {
        this._submitTimer = null;
        this._isSubmitting = false;
        this._ajaxMode = false; // set to true only for change-triggered submits

        this._onSubmit = this.submit.bind(this);
        this.formTarget.addEventListener('submit', this._onSubmit);
    }

    disconnect() {
        this.hasFormTarget && this.formTarget.removeEventListener('submit', this._onSubmit);
    }

    // Handles all form submits
    submit(event) {

        const submitter = event?.submitter;
        const buttonForcesFull = submitter?.dataset.ajax === "full";
        const buttonForcesAjax = submitter?.dataset.ajax === "ajax";

        // Decide whether to hijack
        const shouldAjax =
            (this._ajaxMode || this.fullAjaxSubmitValue || buttonForcesAjax) && !buttonForcesFull;

        // Optional: allow a "hard" full reload (bypass Turbo) when requested
        if (!shouldAjax && submitter?.dataset.hard === "true") {
            event.preventDefault();
            this.formTarget.setAttribute("data-turbo", "false");
            this.formTarget.submit(); // real browser submit
            return;
        }

        if (!shouldAjax) {
            // Let the native/Turbo submit proceed
            return;
        }

        event.preventDefault();
        this._ajaxMode = false; // reset immediately
        this._ajaxSubmit();
    }

    // Debounced auto-submit for selects/checkboxes
    submitOnChange(event) {
        const el = event.target;
        const isSelect = el instanceof HTMLSelectElement;
        const isCheckbox = el instanceof HTMLInputElement && el.type === "checkbox";
        if (!isSelect && !isCheckbox) return;

        clearTimeout(this._submitTimer);
        this._submitTimer = setTimeout(() => {
            this._ajaxMode = true; // only this submit will be hijacked
            // here we don't want to check form validity
            // we want to trigger onchange even if fields are missing
            // because it can be the case that the field that needs to change
            // is a required field
            // NB: We aslo need to set "novalidate" on the form.
            this.formTarget.requestSubmit(); // fires submit()
        }, this.debounceValue);
    }

    async _ajaxSubmit() {
        if (this._isSubmitting) return;
        this._isSubmitting = true;
        this.showLoading();

        try {
            const response = await fetch(this.formTarget.action, {
                method: "POST",
                body: new FormData(this.formTarget),
                headers: { "X-Requested-With": "XMLHttpRequest" },
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const html = await response.text();
            const doc = new DOMParser().parseFromString(html, "text/html");
            const container = doc.querySelector("[data-ajax-target='container']");
            if (!container) throw new Error("No [data-ajax-target='container'] in response");

            this.containerTarget.innerHTML = container.innerHTML;
        } catch (err) {
            console.error(err);
        } finally {
            this.hideLoading();
            this._isSubmitting = false;
        }
    }

    showLoading() {
        this.containerTarget.style.opacity = 0.3;
        this.hasSpinnerTarget && this.spinnerTarget.classList.remove("hidden");
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.setAttribute("disabled", "disabled");
            this.submitBtnTarget.setAttribute("aria-busy", "true");
        }
    }

    hideLoading() {
        this.containerTarget.style.opacity = 1;
        this.hasSpinnerTarget && this.spinnerTarget.classList.add("hidden");
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.removeAttribute("disabled");
            this.submitBtnTarget.removeAttribute("aria-busy");
        }
    }
}
