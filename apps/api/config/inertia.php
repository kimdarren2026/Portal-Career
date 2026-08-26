<?php

/**
 * Overrides only what differs from `inertiajs/inertia-laravel`'s package
 * defaults (merged via ServiceProvider::mergeConfigFrom — every other key
 * keeps its vendor default). Frontend source lives in `apps/web/src/pages`,
 * not the package's default `resources/js/pages` (this Laravel app has no
 * `resources/js` directory at all — see vite.config.ts).
 */
return [

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [
            base_path('../web/src/pages'),
        ],

        'extensions' => [
            'vue',
        ],

    ],

];
