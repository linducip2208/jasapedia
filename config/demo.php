<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo data master switch
    |--------------------------------------------------------------------------
    | DemoDataSeeder never runs from DatabaseSeeder unless BOTH:
    |   - APP_ENV is local/demo/testing
    |   - DEMO_DATA_ENABLED=true
    | Production must seed demo data ONLY via the explicit command with --force.
    */
    'enabled' => env('DEMO_DATA_ENABLED', false),

    // Version tag; bump to invalidate older demo datasets.
    'seed' => env('DEMO_SEED', '20260901'),

    // Default service listing target for jasapedia:seed-demo.
    'services' => (int) env('DEMO_SERVICES', 10000),

    // Supporting entity defaults.
    'defaults' => [
        'providers' => 2500,
        'customers' => 5000,
        'orders' => 3000,
        'projects' => 500,
        'rfqs' => 500,
        'reviews' => 7000,
        'corporates' => 50,
    ],

    // Non-routable email domain used for every demo account.
    'email_domain' => env('DEMO_EMAIL_DOMAIN', 'example.test'),
];
