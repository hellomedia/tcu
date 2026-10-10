import { Controller } from "@hotwired/stimulus";

/**
 * One date card of the availability grid (templates/availability/_grid.html.twig).
 *
 * - "Toute la journée" checks / unchecks every time slot
 * - any interaction marks the date as answered (hidden input): unchecked slots then mean "not available",
 *   as opposed to an untouched date which means "not filled in"
 * - the badge and the status line follow the state
 */
export default class extends Controller {
    static targets = ["all", "allLabel", "time", "answered", "badge", "status"];
    static values = {
        answered: { type: Number, default: 0 },
    };

    connect() {
        this.refresh();
    }

    toggleAll() {
        const checked = this.allTarget.checked;
        this.timeTargets.forEach((input) => (input.checked = checked));
        this.markAnswered();
        this.refresh();
    }

    changeTime() {
        this.markAnswered();
        this.refresh();
    }

    markAnswered() {
        this.answeredValue = 1;
        this.answeredTarget.value = "1";
        this.element.dataset.answered = "1";
    }

    refresh() {
        const total = this.timeTargets.length;
        const available = this.timeTargets.filter((input) => input.checked).length;
        const answered = this.answeredValue === 1;

        if (this.hasAllTarget) {
            this.allTarget.checked = total > 0 && available === total;
            this.allTarget.indeterminate = available > 0 && available < total;
        }
        if (this.hasAllLabelTarget) {
            this.allLabelTarget.textContent = answered && available === 0 ? "Pas disponible ce jour-là" : "Toute la journée";
        }

        if (!answered) return; // server-rendered texts stay for untouched dates

        const status = total > 0 ? "answered" : "none";
        this.element.classList.remove("none", "partial", "answered");
        this.element.classList.add(status);

        if (this.hasBadgeTarget) {
            this.badgeTarget.className = `avail-badge ${status}`;
            this.badgeTarget.textContent = "renseigné";
        }
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = available === 0
                ? "Pas disponible ce jour-là"
                : `Disponible sur ${available} créneau${available > 1 ? "x" : ""} sur ${total}`;
        }
    }
}
