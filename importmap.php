<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    'admin_app' => [
        'path' => './assets/admin_app.js',
        'entrypoint' => true,
    ],
    'announcements' => [
        'path' => './assets/js/announcements.js',
        'entrypoint' => true,
    ],
    'competition-list' => [
        'path' => './assets/js/competition-list.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    'chart.js' => [
        'version' => '4.2.0',
    ],
    'chartjs-plugin-zoom' => [
        'version' => '2.2.0',
    ],
    'hammerjs' => [
        'version' => '2.0.8',
    ],
    'chart.js/helpers' => [
        'version' => '4.4.5',
    ],
    '@kurkle/color' => [
        'version' => '0.3.2',
    ],
    'chartjs-plugin-annotation' => [
        'version' => '3.1.0',
    ],
    'chartjs-adapter-luxon' => [
        'version' => '1.3.1',
    ],
    'luxon' => [
        'version' => '3.2.1',
    ],
];
