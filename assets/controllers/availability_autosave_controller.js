import { Controller } from "@hotwired/stimulus";

/**
 * Autosave of the availability grid (templates/availability/_grid.html.twig, player page).
 *
 * - every "availability-date:changed" schedules a save of the whole form, grouped over a short delay
 * - one request at a time: changes made during a save trigger another save right after
 * - the response is a set of turbo streams refreshing the summaries (never the date cards being edited)
 * - a pending save is sent when leaving the page (keepalive), a failed one asks before leaving
 * - the submit button stays in the HTML (no JS) but is hidden here
 */
export default class extends Controller {
    static targets = ["status", "actions"];

    static DELAY = 700;

    connect() {
        this.timer = null;
        this.saving = false;
        this.again = false;
        this.failed = false;

        if (this.hasActionsTarget) {
            this.actionsTarget.hidden = true;
        }

        this.flushOnLeave = () => this.flush();
        this.warnOnLeave = (event) => {
            if (this.failed) {
                event.preventDefault();
                event.returnValue = "";
            }
        };
        window.addEventListener("pagehide", this.flushOnLeave);
        window.addEventListener("beforeunload", this.warnOnLeave);
        document.addEventListener("turbo:before-visit", this.flushOnLeave);
    }

    disconnect() {
        this.flush();
        window.removeEventListener("pagehide", this.flushOnLeave);
        window.removeEventListener("beforeunload", this.warnOnLeave);
        document.removeEventListener("turbo:before-visit", this.flushOnLeave);
        clearTimeout(this.hideTimer);
    }

    schedule() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), this.constructor.DELAY);
        this.showStatus("saving", "Enregistrement…");
    }

    retry() {
        this.save();
    }

    async save() {
        clearTimeout(this.timer);
        this.timer = null;

        if (this.saving) {
            this.again = true;
            return;
        }

        this.saving = true;
        this.showStatus("saving", "Enregistrement…");

        try {
            const response = await fetch(this.element.action, this.request());

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const html = await response.text();
            if (window.Turbo?.renderStreamMessage) {
                window.Turbo.renderStreamMessage(html);
            }

            this.failed = false;
            if (!this.again) {
                this.showStatus("saved", "Enregistré ✓", 2000);
            }
        } catch (error) {
            console.error(error);
            this.failed = true;
            this.showStatus("error", "L'enregistrement a échoué.", 0, true);
        } finally {
            this.saving = false;
            if (this.again) {
                this.again = false;
                this.save();
            }
        }
    }

    /**
     * Leaving the page: send the pending changes without waiting for the answer
     */
    flush() {
        if (this.timer === null) {
            return;
        }

        clearTimeout(this.timer);
        this.timer = null;
        fetch(this.element.action, { ...this.request(), keepalive: true });
    }

    request() {
        return {
            method: "POST",
            body: new FormData(this.element),
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-Turbo-Stream-Request": "1",
                "Accept": "text/vnd.turbo-stream.html",
            },
        };
    }

    showStatus(state, text, hideAfter = 0, withRetry = false) {
        if (!this.hasStatusTarget) {
            return;
        }

        clearTimeout(this.hideTimer);

        const status = this.statusTarget;
        status.className = `avail-save-status ${state}`;
        status.textContent = text;

        if (withRetry) {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = "Réessayer";
            button.addEventListener("click", () => this.retry());
            status.append(button);
        }

        status.hidden = false;

        if (hideAfter > 0) {
            this.hideTimer = setTimeout(() => (status.hidden = true), hideAfter);
        }
    }
}
