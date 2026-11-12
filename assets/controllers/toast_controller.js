// https://symfonycasts.com/screencast/last-stack/fancier-toast

import { Controller } from '@hotwired/stimulus';
import { useTransition } from 'stimulus-use';

export default class extends Controller {

    static values = {
        autoClose: Number
    }

    static targets = ['timerbar'];

    connect() {

        // https://stimulus-use.github.io/stimulus-use/#/use-transition
        useTransition(this, {
            leaveActive: 'leave-active',
            leaveFrom: 'leave-from',
            leaveTo: 'leave-to',
            transitioned: true,
        });

        if (this.autoCloseValue) {
            setTimeout(() => {
                this.close();
            }, this.autoCloseValue);
        }

        if (this.hasTimerbarTarget) {
            // set timeout of 10ms to enable the timerbar to position itself with full width
            // before transitionning to width 0 with CSS transition
            setTimeout(() => {
                this.timerbarTarget.style.width = 0;
            }, 10);
        }
    }

    close() {
        // https://stimulus-use.github.io/stimulus-use/#/use-transition
        this.leave();
    }
}