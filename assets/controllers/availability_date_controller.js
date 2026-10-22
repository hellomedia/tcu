import { Controller } from "@hotwired/stimulus";

/**
 * One date card of the availability grid (templates/availability/_grid.html.twig).
 *
 * - "Toute la journée" checks every time slot, "Pas dispo ce jour" unchecks them all
 * - each slot has its own "answered" marker (hidden input): an unchecked answered slot means "not available",
 *   as opposed to an unanswered slot which means "not filled in"
 * - on an untouched date, any interaction answers the whole date (unchecked slots = not available)
 * - on a date already answered in part (slots added since), touching a slot answers that slot only:
 *   the new slots stay "not filled in" until the player clicks them (unchecking one then means "not available")
 *   or uses a quick button
 * - the quick buttons, the badge and the status line follow the state
 * - each change dispatches "availability-date:changed" (autosave on the player page)
 */
export default class extends Controller {
    static targets = ["allBtn", "noneBtn", "time", "answered", "badge", "status"];

    connect() {
        this.refresh();
    }

    setAll() {
        this.timeTargets.forEach((input) => (input.checked = true));
        this.answerAll();
        this.changed();
    }

    setNone() {
        this.timeTargets.forEach((input) => (input.checked = false));
        this.answerAll();
        this.changed();
    }

    changeTime(event) {
        if (this.answeredCount() === 0) {
            this.answerAll();
        } else {
            this.answer(event.target);
        }
        this.changed();
    }

    changed() {
        this.refresh();
        this.dispatch("changed"); // autosave (availability_autosave_controller)
    }

    answerAll() {
        this.timeTargets.forEach((input) => this.answer(input));
    }

    answer(input) {
        const i = this.timeTargets.indexOf(input);
        this.answeredTargets[i].value = "1";
        input.closest(".avail-time").dataset.answered = "1";
    }

    answeredCount() {
        return this.answeredTargets.filter((input) => input.value === "1").length;
    }

    refresh() {
        const total = this.timeTargets.length;
        const available = this.timeTargets.filter((input) => input.checked).length;
        const answered = this.answeredCount();
        const complete = total > 0 && answered === total;

        if (this.hasAllBtnTarget) {
            this.allBtnTarget.classList.toggle("active", complete && available === total);
        }
        if (this.hasNoneBtnTarget) {
            this.noneBtnTarget.classList.toggle("active", complete && available === 0);
        }

        if (answered === 0) return; // server-rendered texts stay for untouched dates

        const status = complete ? "answered" : "partial";
        this.element.classList.remove("none", "partial", "answered");
        this.element.classList.add(status);

        if (this.hasBadgeTarget) {
            this.badgeTarget.className = `avail-badge ${status}`;
            this.badgeTarget.textContent = complete ? "renseigné" : "à compléter";
        }
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = available > 0
                ? `Disponible sur ${available} créneau${available > 1 ? "x" : ""} sur ${total}`
                : (complete ? "Pas disponible ce jour-là" : "Pas disponible sur les autres créneaux");
        }
    }
}
