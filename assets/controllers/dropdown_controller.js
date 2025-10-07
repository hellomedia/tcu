import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["menu"];

    connect() {
        this.boundHide = this.hide.bind(this);
    }

    toggle(event) {
        this.menuTarget.classList.toggle("open");
        this.updateListeners();
    }

    hide(event) {
        // Close the menu if click happens outside the dropdown
        if (!this.element.contains(event.target)) {
            this.closeMenu();
        }
    }

    closeMenu() {
        this.menuTarget.classList.remove("open");
        this.updateListeners();
    }

    updateListeners() {
        if (this.menuTarget.classList.contains('open')) {
            document.addEventListener("click", this.boundHide);
        } else {
            document.removeEventListener("click", this.boundHide);
        }
    }
}
