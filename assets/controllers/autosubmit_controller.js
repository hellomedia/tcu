import { Controller } from '@hotwired/stimulus';

/* USAGE

	{# The form targets the frame so only the list is swapped #}
	{{ form_start(form, {
        attr: {
            'data-controller': 'autosubmit',
            'data-action': 'change->autosubmit#submit',
            'data-turbo-frame': 'foo'
        }
    }) }}

*/
export default class extends Controller {
    submit() {
        // Progressive enhancement: Turbo will target the frame, but plain HTML also works
        this.element.requestSubmit();
    }
}
