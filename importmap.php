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
    'controlroom' => [
        'path' => './assets/controlroom.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.12',
    ],
    'stimulus-use' => [
        'version' => '0.52.3',
    ],
    '@stimulus-components/sortable' => [
        'version' => '5.0.2',
    ],
    'sortablejs' => [
        'version' => '1.15.6',
    ],
    '@fancyapps/ui' => [
        'version' => '5.0.36',
    ],
    'chart.js' => [
        'version' => '4.5.0',
    ],
    '@kurkle/color' => [
        'version' => '0.3.4',
    ],
    '@rails/request.js' => [
        'version' => '0.0.12',
    ],
];
