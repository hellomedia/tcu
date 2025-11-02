import { Controller } from "@hotwired/stimulus";

/*

USAGE SUMMARY
=============

    Especially useful for forms with dependent fields, but can be used in all cases.

    - Use data-action="submit->ajax#submit" on form for ajax on submit button click
    - Use data-action="change->ajax#submitOnChange" on form for ajax on any select/checkbox of the form
    - Use data-action="change->ajax#submitOnChange" on field for ajax on that field (useful for trigger/dependent fields)
    - Combine as necessary 

    All this could probably be done with turbo and turbo frames.
    Main difference with turboframe:
        - only 'container' div is replaced (but that could be done with a turbo-frame instead of container div)
        - a bit more flexbility for handling of loading indicator (but that could be done with turbo events)
        - actions are a bit more explicit:
                - submit->ajax#submit
                - change->ajax#submitOnChange
            but again, we could keep those and use requestSubmit() to hook into turbo handling.


A) Ajax on full form submit
===========================

    {{ form_start(form, {
        'attr': {
            'data-controller': 'ajax',
            'data-action': 'submit->ajax#submit'
        }
    }) }}
        <div data-ajax-target="container">
            {{ form_rest(form) }}
        </div>
        
        {% include 'component/modal/_actions.html.twig' %}
        OR {% include 'form/_form_actions.html.twig' %}
        OR {% include '@admin/form/_ajax_button.html.twig' %}
    
    </form>
    
B) Ajax on full form submit + onChange for all select/checkbox (Guabao)
=======================================================================

    {{ form_start(form, {
        'attr': {
            'data-controller': 'ajax',
            'data-action': 'submit->ajax#submit change->ajax#submitOnChange'
        }
    }) }}
        <div data-ajax-target="container">
            {{ form_rest(form) }}
        </div>

        {% include 'component/modal/_actions.html.twig' %}
        OR {% include 'form/_form_actions.html.twig' %}
        OR {% include '@admin/form/_ajax_button.html.twig' %}
        
    </form>

C) Ajax only on change of dependent fields (no form submit btn, no other fields)
================================================================================

        {{ form_start(form, { 'attr': {'data-controller': 'ajax'} }) }}

            <div data-ajax-target="container">
                {{ form_row(form.trigggerField) }}
                
                {% if form.dependentField is defined %}
                    {{ form_row(form.dependentField) }}
                {% endif %}

                {% set submitBtn = form_row(form.save) %}

                {{ form_rest(form) }}
            </div>
            
            {% embed 'component/modal/_actions.html.twig' %}
                {% block submit_button %}
                    {{ submitBtn|raw }}
                {% endblock %}
            {% endembed %}

        </form>

    == FORM ==

        In form type, add action on trigger fields

        $builder->add('triggerProperty', FormFieldType::class, [
            'attr' => [
                'data-action' => 'change->ajax#submitOnChange',
            ]
        ]);

        and add submit button so we can check if it's clicked

        $builder->add('save', SubmitType::class);

    == CONTROLLER ==

        $form = $this->createForm(FooForm::class, $foo);
        
        $form->handleRequest($request);

        $submitBtn = $form->get('save');
        assert($submitBtn instanceof ClickableInterface);

        // isClicked() avoids submitting when updating dependent field
        if ($submitBtn->isClicked() && $form->isSubmitted() && $form->isValid()) {

            $this->entityManager->persist($foo);
            $this->entityManager->flush();

            $feedback = 'Success!';

            if ($request->query->get('modal')) {
                return $this->render('@admin/slot/modal/add_booking_success.html.twig', [
                    'feedback' => $feedback,
                    'element_to_update' => $elementToUpdate, // element to update on page with turbo stream
                ]);
            }

            $this->addFlash('success', $feedback);

            return $this->redirectToRoute('another_route');
        }

        if ($request->query->get('modal')) {
            return $this->render('foo/modal/bar.html.twig', [
                'form' => $form,
            ]);
        }

        return $this->render('foo.html.twig', [
            'form' => $form,
        ]);

D) Ajax on change of dependent fields + full form submit
========================================================

    Same as 2) and add on form:
    
        'data-action': 'submit->ajax#submit'

 */

export default class extends Controller {
    static targets = ["container"];
    static values = {
        debounce: { type: Number, default: 250 },
    };

    connect() {
        this.form = this.element;
        this.submitBtn = this.form.querySelector('[type="submit"]');
        this._submitTimer = null;
        this._isSubmitting = false;
    }

    // Attach when you want full-submit to be manual AJAX,
    // not handled by turbo
    async submit(event) {
        event.preventDefault();

        await this._submitAjax();
    }

    // Debounced auto-submit for selects/checkboxes/radios
    submitOnChange(event) {
        const el = event.target;
        const isSelect = el instanceof HTMLSelectElement;
        const isCheckbox = el instanceof HTMLInputElement && el.type === "checkbox";
        const isRadio = el instanceof HTMLInputElement && el.type === "radio";
        if (!isSelect && !isCheckbox && !isRadio) return;

        clearTimeout(this._submitTimer);

        // setTimeout accepts async functions inside the callback
        this._submitTimer = setTimeout(() => {
            this._submitAjax();
        }, this.debounceValue);
    }

    /**
     * UNUSED
     * Instead of using _submitAjax, which replicates Turbo behaviour,
     * we could use this and tap into vanilla Turbo.
     */
    _requestSubmit() {
        this.form.requestSubmit();
    }

    /**
     * Replicates turbo ajax call with custom behaviours
     * useful for forms with dependent fields:
     *    - only replaces the 'container' element
     *    - fades 'container' element on loading
     */
    async _submitAjax() {
        if (this._isSubmitting) return;
        this._isSubmitting = true;

        // Optional: cancel in-flight request if user types fast
        this._abortController?.abort();
        this._abortController = new AbortController();

        this.showLoading();

        try {
            const response = await fetch(this.form.action, {
                method: "POST",
                body: new FormData(this.form),
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "text/vnd.turbo-stream.html, text/html"
                },
                signal: this._abortController.signal,
            });

            const contentType = response.headers.get("content-type") || "";
            const html = await response.text(); // read body even on 422

            // If server responded with Turbo Streams (standalone turbo streams),
            // let Turbo process them
            if (contentType.includes("text/vnd.turbo-stream.html")) {
                if (window.Turbo?.renderStreamMessage) {
                    window.Turbo.renderStreamMessage(html);
                }
                return; // streams handled; nothing more to do here
            }

            // For regular HTML, accept both 2xx and 422
            // NB: Regular HTML can also include turbo streams, in which case they are
            // processed by turbo automatically when they are rendered in the DOM
            if (response.ok || response.status === 422) {
                const doc = new DOMParser().parseFromString(html, "text/html");
                const container = doc.querySelector("[data-ajax-target='container']");
                if (!container) throw new Error("No [data-ajax-target='container'] in response");
                this.containerTarget.innerHTML = container.innerHTML;
            } else {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch (err) {
            if (err.name !== "AbortError") {
                console.error(err);
            }
        } finally {
            this.hideLoading();
            this._isSubmitting = false;
        }
    }

    showLoading() {
        // custom fading we wouldn't get with Vanilla turbo
        // (unless we attach a turbo:start listener like in loading_controller.js)
        this.containerTarget.style.opacity = 0.3;
        
        // tap into global loading button behaviour
        const loadingController = this.application.getControllerForElementAndIdentifier(this.submitBtn, 'loading');
        loadingController.start();
    }

    hideLoading() {
        // custom fading we wouldn't get with Vanilla turbo
        // (unless we attach a turbo:start listener like in loading_controller.js)
        this.containerTarget.style.opacity = 1;
        
        // tap into global loading button behaviour
        const loadingController = this.application.getControllerForElementAndIdentifier(this.submitBtn, 'loading');
        loadingController.stop();
    }
}
