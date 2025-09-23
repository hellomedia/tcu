import { Controller } from "@hotwired/stimulus";

/*

USAGE SUMMARY
=============
- Use data-action="submit->ajax#submit" on form for ajax on submit button click
- Use data-action="change->ajax#submitOnChange" on form for ajax on any select/checkbox of the form
- Use data-action="change->ajax#submitOnChange" on field for ajax on that field (useful for trigger/dependent fields)
- Combine as necessary 

1) Ajax on full form submit
===========================

    <div class="form-wrapper" data-controller="ajax">
        {{ form_start(form, {
            'attr': {
                'data-ajax-target': 'form',
                'data-action': 'submit->ajax#submit'
            }
        }) }}
            <div data-ajax-target="container">
                {{ form_rest(form) }}
            </div>
            {% include '@admin/form/_ajax_button.html.twig' %}
        </form>
    </div>
    
2) Ajax on full form submit + onChange for all select/checkbox
==============================================================

    <div class="form-wrapper" data-controller="ajax">
        {{ form_start(form, {
            'attr': {
                'data-ajax-target': 'form',
                'data-action': 'submit->ajax#submit change->ajax#submitOnChange'
            }
        }) }}
            <div data-ajax-target="container">
                {{ form_rest(form) }}
            </div>
            {% include '@admin/form/_ajax_button.html.twig' %}
        </form>
    </div>

3) Ajax only on change of dependent fields (no form submit btn, no other fields)
==========================================================================

        <div class="form-wrapper" data-controller="ajax">
            {{ form_start(form, { 'attr': {'data-ajax-target': 'form'} }) }}

                <div data-ajax-target="container">
                    {{ form_row(form.trigggerField) }}
                    
                    {% if form.dependentField is defined %}
                        {{ form_row(form.dependentField) }}
                    {% endif %}

                    {% set submitBtn = form_row(form.save) %}

                    {{ form_rest(form) }}
                </div>
                
                {{ submitBtn|raw }}
            </form>
        </div>

        == FORM ==

        In form type, add action to trigger fields

        $builder->add('triggerProperty', FormFieldType::class, [
            'attr' => [
                'data-action' => 'change->ajax#submitOnChange',
            ]
        ]);

        and add submit button so we can check if it's clicked

        $builder->add('save', AjaxSubmitType::class);

        == CONTROLLER ==

        $form = $this->createForm(FooForm::class, $foo);
        
        $form->handleRequest($request);

        $submitBtn = $form->get('save');
        assert($submitBtn instanceof ClickableInterface);

        // isClicked() avoids submitting when updating dependent field
        if ($submitBtn->isClicked() && $form->isSubmitted() && $form->isValid()) {

            $this->entityManager->persist($foo);
            $this->entityManager->flush();

            $this->addFlash('success', 'message');

            return $this->redirectToRoute('route');
        }

        return $this->render('foo.html.twig', [
            'form' => $form,
        ]);

4) Ajax on change of dependent fields + full form submit
========================================================

Same as 2) and add on form:
    
        'data-action': 'submit->ajax#submit'

 */

export default class extends Controller {
    static targets = ["form", "container", "spinner", "submitBtn"];
    static values = {
        debounce: { type: Number, default: 250 },
    };

    connect() {
        this._submitTimer = null;
        this._isSubmitting = false;
    }

    // Attach when you want full-submit to be AJAX
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

    async _submitAjax() {
        if (this._isSubmitting) return;
        this._isSubmitting = true;

        // Optional: cancel in-flight request if user types fast
        this._abortController?.abort();
        this._abortController = new AbortController();

        this.showLoading();

        try {
            const response = await fetch(this.formTarget.action, {
                method: "POST",
                body: new FormData(this.formTarget),
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
        this.containerTarget.style.opacity = 0.3;
        this.hasSpinnerTarget && this.spinnerTarget.classList.remove("hidden");
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.setAttribute("disabled", "disabled");
            this.submitBtnTarget.setAttribute("aria-busy", "true");
        }
    }

    hideLoading() {
        this.containerTarget.style.opacity = 1;
        this.hasSpinnerTarget && this.spinnerTarget.classList.add("hidden");
        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.removeAttribute("disabled");
            this.submitBtnTarget.removeAttribute("aria-busy");
        }
    }
}
