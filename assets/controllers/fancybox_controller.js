import { Controller } from '@hotwired/stimulus'
import { Fancybox } from "@fancyapps/ui";

import '../styles/vendor/fancybox.css';

export default class extends Controller {
    connect() {
        Fancybox.bind('[data-fancybox]', {
            animated: true,
            showClass: 'fancybox-fadeIn',
            hideClass: 'fancybox-fadeOut',
        })
    }
}
