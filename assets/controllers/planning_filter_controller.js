import { Controller } from "@hotwired/stimulus";

/**
 * Planning page filters:
 *  - free slots only
 *  - focus on one poule (click on its legend item, or ?poule=<id> in the url)
 *
 * Filtered slot rows are dimmed (not hidden) so the times stay aligned across courts.
 * The state is kept in localStorage and re-applied when a slot row is replaced by a turbo stream.
 *
 *  <div data-controller="planning-filter" data-planning-filter-storage-key-value="planning-filter">
 *      <input type="checkbox" data-planning-filter-target="freeOnly" data-action="planning-filter#toggleFreeOnly">
 *      <button data-group-id="1" data-planning-filter-target="group" data-action="planning-filter#toggleGroup">
 *      <button data-planning-filter-target="reset" data-action="planning-filter#reset">
 *      <div data-planning-filter-target="planning"> ... .slot-row.booked[data-group-id] / .slot-row.free ... </div>
 *  </div>
 */
export default class extends Controller {
    static targets = ["freeOnly", "group", "reset", "planning"];
    static values = {
        storageKey: { type: String, default: "planning-filter" },
    };

    connect() {
        this.state = this._load();

        // link from a poule (public poules page): ?poule=<id> focuses on it, then the param is dropped from the url
        const url = new URL(window.location.href);
        if (url.searchParams.has("poule")) {
            this.state.groupId = url.searchParams.get("poule");
            this._save();
            url.searchParams.delete("poule");
            history.replaceState(history.state, "", url);
        }

        // remembered poule not on this page any more (other season...): forget it, or everything would be filtered out
        if (this.state.groupId && !this.groupTargets.some((button) => button.dataset.groupId === this.state.groupId)) {
            this.state.groupId = null;
            this._save();
        }

        // slot rows replaced by turbo streams lose their classes: re-apply
        this.observer = new MutationObserver(() => this._apply());
        this.observer.observe(this.planningTarget, { childList: true, subtree: true });

        this._apply();
    }

    disconnect() {
        this.observer?.disconnect();
    }

    toggleFreeOnly() {
        this.state.freeOnly = this.freeOnlyTarget.checked;
        this._save();
        this._apply();
    }

    toggleGroup(event) {
        const id = event.currentTarget.dataset.groupId;
        this.state.groupId = this.state.groupId === id ? null : id;
        this._save();
        this._apply();
    }

    reset() {
        this.state = { freeOnly: false, groupId: null };
        this._save();
        this._apply();
    }

    _apply() {
        const { freeOnly, groupId } = this.state;

        if (this.hasFreeOnlyTarget) {
            this.freeOnlyTarget.checked = freeOnly;
        }
        this.groupTargets.forEach((button) => {
            button.classList.toggle("active", button.dataset.groupId === groupId);
        });
        if (this.hasResetTarget) {
            this.resetTarget.hidden = !freeOnly && !groupId;
        }

        this.planningTarget.querySelectorAll(".date").forEach((date) => {
            let visible = 0;

            date.querySelectorAll(".slot-row:not(.slot-row-placeholder)").forEach((row) => {
                const booked = row.classList.contains("booked");
                let dimmed = false;

                if (booked) {
                    // poule focus hides the other poules ; "free only" alone hides every booked slot
                    dimmed = groupId ? row.dataset.groupId !== groupId : freeOnly;
                } else {
                    // free slots stay visible with "free only", even when focusing on a poule :
                    // that is where its remaining matches can go
                    dimmed = !!groupId && !freeOnly;
                }

                row.classList.toggle("dimmed", dimmed);
                if (!dimmed) visible++;
            });

            date.classList.toggle("all-dimmed", visible === 0);
        });
    }

    _load() {
        try {
            const stored = JSON.parse(localStorage.getItem(this.storageKeyValue) || "{}");
            return { freeOnly: !!stored.freeOnly, groupId: stored.groupId || null };
        } catch {
            return { freeOnly: false, groupId: null };
        }
    }

    _save() {
        localStorage.setItem(this.storageKeyValue, JSON.stringify(this.state));
    }
}
