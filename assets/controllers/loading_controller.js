import { Controller } from "@hotwired/stimulus";

import '../styles/component/loading-btn.css';

/**
 * 
 * Usage:
 *      <button
            type="submit"
            class="btn btn-primary"
            data-controller="loading"
            data-loading-target="button"  
        >
            <span>{% block submit_label 'Save'|trans %}</span>
            <div class="loader" data-loading-target="loader"><div class="spinner"></div></div>
        </button>
 * 
 * Also in form_theme, button_widget:
 * 
    {% block button_widget %}
        {% set attr = attr|merge({'data-controller': 'loading', 
                                'data-loading-target': 'button', 
                                'class': (attr.class|default('') ~ ' btn')|trim }) %}
        <button {{ block('button_attributes') }}>
            <span>{{ label|default('Save') }}</span>
            <div class="loader" data-loading-target="loader"><div class="spinner"></div></div>
        </button>
    {% endblock %}
 */
export default class extends Controller {
    static targets = ["button", "loader"];

    connect() {

        this.form = this.buttonTarget.closest('form');

        this.boundStart = this.start.bind(this);
        this.boundStop = this.stop.bind(this);

        // Forms submitted by Turbo: inside a turbo-frame (modals). No Turbo Drive: the other forms are native submits.
        // Attaching loading#start to turbo:submit-start event on the button itself does not work
        // Attaching loading#start to turbo:submit-start event on the form does not work either
        // We must attach it to turbo:submit-start on the * document * !
        // https://chatgpt.com/share/67dfc927-0268-8012-8e95-d22ec7399be4
        document.addEventListener('turbo:submit-start', this.boundStart);
        document.addEventListener('turbo:submit-end', this.boundStop);

        // Native submit (full page): only the button of the submitted form
        this.boundNativeSubmit = (event) => {
            // If Turbo intercepted the submit, it will have prevented default.
            if (event.defaultPrevented) return;
            this.start(event);
        };
        document.addEventListener('submit', this.boundNativeSubmit);

        // Page restored from the back/forward cache after a native submit: the spinner would still be on
        this.boundPageShow = (event) => {
            if (event.persisted) this.stop();
        };
        window.addEventListener('pageshow', this.boundPageShow);
    }

    disconnect() {
        // Clean up listeners to avoid memory leaks
        document.removeEventListener('turbo:submit-start', this.boundStart);
        document.removeEventListener('turbo:submit-end', this.boundStop);
        document.removeEventListener('submit', this.boundNativeSubmit);
        window.removeEventListener('pageshow', this.boundPageShow);
    }

    start(event) {

        // if called from outside (ajax_controller), no event is passed
        if (event) {
            const form = event.target;
            if (form !== this.form) return; // Skip unrelated forms
        }

        this.loaderTarget.classList.add("active");

        if (this.hasButtonTarget) {
            // DO NOT set disabled attribute on button
            // It prevents form submission for non-turbo full page submit
            // this.buttonTarget.setAttribute("disabled", "disabled");
            // Instead, we prevent double click and give visual feedback with CSS
            this.buttonTarget.setAttribute("aria-busy", "true");
        }
    }

    stop() {
        this.loaderTarget.classList.remove("active");

        if (this.hasButtonTarget) {
            // see above
            // this.buttonTarget.removeAttribute("disabled");
            this.buttonTarget.removeAttribute("aria-busy");
        }
    }
}