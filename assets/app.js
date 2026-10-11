import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import './styles/app.css';

import * as Turbo from '@hotwired/turbo';

// Turbo Drive is off: links and forms are plain browser navigations.
// Browsers now handle what Drive was for (no white flash between pages, page transitions:
// @view-transition in view-transition.css, instant back/forward cache), and Drive came with
// its own problems (stale page snapshots, session changes not picked up, lazy Stimulus controllers).
// The rest of Turbo stays:
//  - turbo-frames with data-turbo="true" (modals): links and forms inside a frame are still handled
//  - turbo-streams rendered from our own fetch() calls (Turbo.renderStreamMessage: autosave, drag & drop...)
// The admin (admin.js) never used Drive (EasyAdmin sets data-turbo="false" on <html>).
Turbo.session.drive = false;
