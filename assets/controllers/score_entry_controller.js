import { Controller } from "@hotwired/stimulus";

/**
 * Result form (MatchResultForm): quick score entry on a phone.
 *
 * - typing a set score moves to the next field (entry order: set 1 A, set 1 B, set 2 A...);
 *   in set 3, a "1" waits for a second digit (super tie-break, 10/8)
 * - the content of a field is selected on focus: typing replaces it
 * - live preview of the points, same rules as MatchResult::setPoints() (the server computes them)
 */
export default class extends Controller {
    static targets = ["game", "points", "submit"];

    connect() {
        this.update();
    }

    select(event) {
        event.target.select();
    }

    next(event) {
        this.update();

        const input = event.target;
        const value = input.value;
        if (value === "") return;

        const waitsForSecondDigit = input.dataset.set === "3" && value === "1";
        if (waitsForSecondDigit) return;

        const fields = this._orderedGames();
        const following = fields[fields.indexOf(input) + 1];

        // last field: on the save button (also hides the phone keyboard)
        (following ?? (this.hasSubmitTarget ? this.submitTarget : null))?.focus();
    }

    update() {
        if (!this.hasPointsTarget) return;

        const sets = {};
        this.gameTargets.forEach((input) => {
            sets[input.dataset.set] ??= {};
            sets[input.dataset.set][input.dataset.side] = input.value;
        });

        let pointsA = 0;
        let pointsB = 0;
        let played = false;

        Object.values(sets).forEach(({ A, B }) => {
            if (A === "" && B === "") return;

            played = true;
            const [a, b] = this._setPoints(Number(A || 0), Number(B || 0));
            pointsA += a;
            pointsB += b;
        });

        this.pointsTargets.forEach((target) => {
            target.textContent = played ? (target.dataset.side === "A" ? pointsA : pointsB) : "–";
        });
    }

    /**
     * Same rules as MatchResult::setPoints()
     */
    _setPoints(a, b) {
        if (a < b) return this._setPoints(b, a).reverse();

        // won at 6 games with 2 games ahead, or at 7 games (7/5, 7/6)
        if (a >= 6 && (a - b >= 2 || a === 7)) {
            return [2, a === 7 && b === 6 ? 1 : 0];
        }

        // unfinished set: led by 2 games or more, otherwise tied or led by 1 game only
        return a - b >= 2 ? [1, 0] : [1, 1];
    }

    _orderedGames() {
        return [...this.gameTargets].sort((x, y) => Number(x.tabIndex) - Number(y.tabIndex));
    }
}
