import { Controller } from "@hotwired/stimulus";

/**
 * Planning page : drag a booked slot and drop it on a free slot to move the match.
 *
 * Attached to the same element as the 'modal' and 'modal-confirm' controllers:
 *
 *  <div class="planning dnd-enabled"
 *       data-controller="modal modal-confirm modal-remote-form planning-dnd"
 *       data-planning-dnd-token-value="{{ csrf_token('move-booking') }}"
 *       data-planning-dnd-url-value="{{ path('admin_match_move_booking_to', {id: 'MATCH', slot: 'SLOT'}) }}">
 *
 * Slot rows (see _slot_row.html.twig):
 *   .slot-row.booked[data-match-id][data-date-id][data-starts-at][data-has-confirmations] > .slot-content[draggable]
 *   .slot-row.free[data-slot-id][data-date-id][data-starts-at][data-label]
 *
 * - Same date and time (court change) or no player confirmation yet : the move is posted right away,
 *   the response is a set of turbo streams replacing the two slot rows.
 * - Otherwise the player confirmations will be reset : the confirmation modal is opened first
 *   (it posts to the same route with ?modal=1 and shows the usual success modal + streams).
 *
 * Events are delegated from the planning root, so rows replaced by turbo streams keep working.
 */
export default class extends Controller {
    static values = {
        token: String,
        url: String,
    };

    connect() {
        this.source = null;
        this.handlers = {
            dragstart: this.onDragStart.bind(this),
            dragend: this.onDragEnd.bind(this),
            dragover: this.onDragOver.bind(this),
            dragleave: this.onDragLeave.bind(this),
            drop: this.onDrop.bind(this),
        };
        Object.entries(this.handlers).forEach(([name, handler]) => this.element.addEventListener(name, handler));
    }

    disconnect() {
        Object.entries(this.handlers).forEach(([name, handler]) => this.element.removeEventListener(name, handler));
    }

    onDragStart(event) {
        const row = event.target.closest?.(".slot-row.booked");
        if (!row) return;

        this.source = row;
        row.classList.add("dragging");
        this.element.classList.add("dragging-active");

        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", row.dataset.matchId);
    }

    onDragEnd() {
        this.source?.classList.remove("dragging");
        this.element.classList.remove("dragging-active");
        this.element.querySelectorAll(".drop-target").forEach((row) => row.classList.remove("drop-target"));
        this.source = null;
    }

    onDragOver(event) {
        const row = this._freeRow(event.target);
        if (!row || !this.source) return;

        event.preventDefault(); // allow drop
        event.dataTransfer.dropEffect = "move";

        if (!row.classList.contains("drop-target")) {
            row.classList.add("drop-target");
            row.querySelector(".slot-content-inner")?.setAttribute("data-drop-label", `Déplacer ici`);
        }
    }

    onDragLeave(event) {
        const row = this._freeRow(event.target);
        if (row && !row.contains(event.relatedTarget)) {
            row.classList.remove("drop-target");
        }
    }

    onDrop(event) {
        const target = this._freeRow(event.target);
        const source = this.source;
        if (!target || !source) return;

        event.preventDefault();
        target.classList.remove("drop-target");

        const url = this.urlValue.replace("MATCH", source.dataset.matchId).replace("SLOT", target.dataset.slotId);

        const sameMoment = source.dataset.dateId === target.dataset.dateId
            && source.dataset.startsAt === target.dataset.startsAt;
        const resetsConfirmations = source.dataset.hasConfirmations === "1" && !sameMoment;

        if (resetsConfirmations) {
            this._confirm(url, source, target);
        } else {
            this._move(url);
        }
    }

    /**
     * Confirmation modal (modal-confirm controller) : same flow as the in-page confirm buttons
     */
    _confirm(url, source, target) {
        const confirmController = this.application.getControllerForElementAndIdentifier(this.element, "modal-confirm");
        if (!confirmController) {
            if (window.confirm("Les confirmations des joueurs seront réinitialisées. Déplacer le match ?")) {
                this._move(url);
            }
            return;
        }

        const button = document.createElement("button");
        button.setAttribute("formaction", url);
        button.dataset.title = "Déplacer le match";
        button.dataset.html = `<p><b>${this._escape(source.dataset.matchLabel)}</b><br>→ ${this._escape(target.dataset.label)}</p>`
            + `<p>La date ou l'heure change : les confirmations des joueurs seront réinitialisées.</p>`;
        button.dataset.buttonLabel = "Déplacer";
        button.dataset.variant = "warning";
        button.dataset.csrf = this.tokenValue;

        confirmController.open({ preventDefault() {}, currentTarget: button });
    }

    /**
     * Direct move : the response is a set of turbo streams
     */
    async _move(url) {
        const body = new FormData();
        body.set("token", this.tokenValue);

        try {
            const response = await fetch(url, {
                method: "POST",
                body,
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "text/vnd.turbo-stream.html, text/html",
                },
            });

            const html = await response.text();

            if (!response.ok) {
                window.alert(response.status === 409 ? html : "Le déplacement a échoué.");
                return;
            }

            if (window.Turbo?.renderStreamMessage) {
                window.Turbo.renderStreamMessage(html);
            }
        } catch (error) {
            console.error(error);
            window.alert("Le déplacement a échoué.");
        }
    }

    _freeRow(element) {
        return element.closest?.(".slot-row.free") ?? null;
    }

    _escape(text) {
        const div = document.createElement("div");
        div.textContent = text ?? "";
        return div.innerHTML;
    }
}
