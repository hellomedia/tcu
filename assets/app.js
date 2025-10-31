import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import './styles/app.css';

import * as Turbo from '@hotwired/turbo';

// https://symfonycasts.com/screencast/last-stack/turbo-drive
// Disable Turbo drive while keeping other parts of Turbo (frames and streams)
// BUT turbo frames needs ajax anyway, so it's much easier with turbo drive
// NB: At the moment, there seems to be a bug with turbo drive
// with stimulus lazy controllers getting only downloaded on full page load, not on turbo navigation
// which forces us to make the controllers eager for now.
// Turbo.session.drive = false;

// Comment id: fGroZT
// Used for detecting session changes (login if configured so, logout, role change, server clearing sessions)
// and triggering a full page reload - instead of partial content update - to take the new session into account
// NB: This does not make session data changes visible (theme, ...) if the session itself was not changed
// To trigger a full page reload in those cases, we use data-turbo=false on links that change session data
document.addEventListener("turbo:before-render", (event) => {
    const newSessionId = event.detail.newBody.querySelector("meta[name='session-id']")?.content;
    const currentSessionId = document.querySelector("meta[name='session-id']")?.content;

    if (newSessionId && newSessionId !== currentSessionId) {
        window.location.href = "/";
    }
});

// Preparing the page to be cached
// Listen for the turbo:before-cache event if you need to prepare the document before Turbo Drive caches it.
// You can use this event to reset forms, collapse expanded UI elements,
// or tear down any third-party widgets so the page is ready to be displayed again.
// https://turbo.hotwired.dev/handbook/building#understanding-caching
document.addEventListener("turbo:before-cache", function () {
    // Important : reset forms to their state at page load
    document.querySelectorAll("form").forEach(form => form.reset());
});
