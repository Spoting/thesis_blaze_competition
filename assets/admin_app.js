import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/admin_app.css';

import { Chart } from 'chart.js';

import zoomPlugin from 'chartjs-plugin-zoom';
import annotationPlugin from 'chartjs-plugin-annotation';
import 'chartjs-adapter-luxon';




// Register the zoom plugin
document.addEventListener('chartjs:init', function (event) {
    const Chart = event.detail.Chart;
    Chart.register(zoomPlugin); 
    Chart.register(annotationPlugin);
    // Chart.register(adapterPlugin);
});

// php bin/console tailwind:build --watch
// php bin/console importmap:
// php bin/console asset-map:compile