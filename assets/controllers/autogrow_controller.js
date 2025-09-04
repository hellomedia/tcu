import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    resize() {
        this.element.style.height = "auto"; // Reset height to calculate the new size
        this.element.style.height = `${this.element.scrollHeight}px`; // Set height to the scroll height
    }

}