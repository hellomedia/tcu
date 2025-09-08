import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["form", "container", "spinner", "submitBtn"];
    static values = { debounce: { type: Number, default: 250 } };

    connect() {
        this._submitTimer = null;
        this._isSubmitting = false;
    }

    // Native submit (from button or Enter)
    async submit(event) {
        event?.preventDefault();

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

    // Auto-submit when select/checkbox changes (debounced)
    submitOnChange(event) {
        console.log('etststet')
        const el = event.target;
        const isSelect = el instanceof HTMLSelectElement;
        const isCheckbox = el instanceof HTMLInputElement && el.type === "checkbox";
        if (!isSelect && !isCheckbox) return;

        // Debounce to avoid double fires & fast toggles
        clearTimeout(this._submitTimer);
        this._submitTimer = setTimeout(() => {
            // Use requestSubmit so native validations still apply     
            this.formTarget.requestSubmit();
        }, this.debounceValue);
    }

    showLoading() {
        this.spinnerTarget.classList.remove("hidden");
        this.submitBtnTarget?.setAttribute("disabled", "disabled");
        this.submitBtnTarget?.setAttribute("aria-busy", "true");
    }

    hideLoading() {
        if (this.hasSpinnerTarget) this.spinnerTarget.classList.add("hidden");
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.removeAttribute("disabled");
            this.submitBtnTarget.removeAttribute("aria-busy");
        }
    }
}
