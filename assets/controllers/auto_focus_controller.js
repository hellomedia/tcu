import { Controller } from "@hotwired/stimulus"

/**
 * For when we want to apply focus WITHOUT the :focus-visible styles
 * 
 * Browsers analyze recent input to decide: "Should I show the focus ring?"
 * And: 
 * If the focus is caused after a click, keypress, or tab, → no visible ring.
 * If the focus is caused by automatic DOM changes, → they force :focus-visible for accessibility reasons.
 */
export default class extends Controller {
    connect() {
        this.element.focus();
        // override focus-visible styles
        this.element.classList.add('no-focus-visible');

        // if user uses tabs out and then back to the element, recover focus-visible styles as expected
        this.element.addEventListener('blur', () => {
            this.element.classList.remove('no-focus-visible');
        }, { once: true });
    }
}