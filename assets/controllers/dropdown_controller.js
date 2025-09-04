import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["menu"];

    connect() {
        this.boundHide = this.hide.bind(this);
    }

    toggle(event) {
        this.menuTarget.classList.toggle("hidden");
        this.menuTarget.classList.toggle("opacity-0");
        this.menuTarget.classList.toggle("scale-95");
        this.menuTarget.classList.toggle("opacity-100");
        this.menuTarget.classList.toggle("scale-100");

        this.updateListeners();
    }

    hide(event) {
        // Close the menu if click happens outside the dropdown
        if (!this.element.contains(event.target)) {
            this.closeMenu();
        }
    }

    closeMenu() {
        this.menuTarget.classList.add("hidden", "opacity-0", "scale-95");
        this.menuTarget.classList.remove("opacity-100", "scale-100");
        
        this.updateListeners();
    }

    updateListeners() {
        if (this.menuTarget.classList.contains('hidden')) {
            document.removeEventListener("click", this.boundHide);
        } else {
            document.addEventListener("click", this.boundHide);
        }
    }
}
