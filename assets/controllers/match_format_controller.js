import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["doublesOnly"];

    connect() {
        this.update();
    }

    update() {
        const select = this.element.querySelector('[data-action~="change->match-format#update"]')
            || this.element.querySelector('select[name$="[format]"]');
        const isDoubles = (select?.value || "") === "DOUBLE";

        this.doublesOnlyTargets.forEach((row) => {
            row.style.display = isDoubles ? "" : "none";
        });
    }
}
