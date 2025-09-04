import { Controller } from "@hotwired/stimulus"

/**
 * NB: attach to nav element with position: relative
 */
export default class extends Controller {

    connect() {
        this.border = document.createElement("div");
        this.border.id = "menu-hover-border";

        this.element.appendChild(this.border);
    }

    enter(event) {
        // Disable on small screens
        if (window.innerWidth < 768) return;

        this.moveBorderToItem(event.currentTarget);
    }

    leave() {
        this.border.style.width = "0";
    }

    moveBorderToItem(item) {
        const { left, width } = item.getBoundingClientRect();
        const menuLeft = this.element.getBoundingClientRect().left;

        // Set color from data attribute or use default
        const hoverColor = item.dataset.hoverColor || 'var(--color-menu-border-hover)';

        this.border.style.width = `${width}px`;
        this.border.style.left = `${left - menuLeft}px`;
        this.border.style.backgroundColor = hoverColor;

        // current item hover handled in CSS
        if (item.classList.contains('current')) {
            this.border.style.width = 0;
        }
    }
}
