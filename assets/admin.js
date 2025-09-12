/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

// Load all CSS directly in this file
// DO NOT USE 2nd level imports ( like... import './styles/admin.css -- and then import css from there )
// 2nd level imports are not handled by asset mapper
// They can be handled by tailwind -- as we do for assets/styles/app.css
// BUT symfonycast/tailwind binary which builds tailwind assets only supports 1 entry point
// ( see symfonycasts_tailwind.yaml )
// So in the end, 2nd level imports are not recognized in a file other than app.css

// DO NOT import lights.css
// it only kicks in if body has .light class, which we don't do in easyadmin
// import './styles/themes/light.css';
// INSTEAD, import custom version for easyadmin or rely on easyadmin variables

import './styles/admin/admin-variables.css';
import './styles/admin/admin.css';
import './styles/admin/grid.css';
import './styles/admin/card.css';
import './styles/admin/chart.css';
import './styles/admin/ajax-submit.css';

import "./styles/admin/modal-variables.css";
import "./styles/components/dropdown.css";
import "./styles/components/modal.css";
import "./styles/components/modal-icon.css";
import "./styles/components/modal-animation.css";

// chartjs *with luxon adapter* loaded *before starting stimulus application*
// Loading luxon adapter in the page script, after chartjs controller connects, breaks chartjs
// https://github.com/symfony/ux/issues/682#issuecomment-1421057632
// bin/console importmap:require chart.js
import { Chart } from 'chart.js';
// https://github.com/chartjs/chartjs-adapter-luxon
// bin/console importmap:require chartjs-adapter-luxon
import 'chartjs-adapter-luxon';

// start the stimulus application
// and import stimulus controllers:
// - from Symfony UX
// - custom controllers
import './bootstrap.js';
