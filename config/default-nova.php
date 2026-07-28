<?php

return [

    'prevent_lazy_loading' => true,
    'with_breadcrumbs' => filter_var(env('NOVA_TEST_WITH_BREADCRUMBS', '1'), FILTER_VALIDATE_BOOLEAN),
    'without_notifications' => filter_var(env('NOVA_TEST_WITHOUT_NOTIFICATIONS', '1'), FILTER_VALIDATE_BOOLEAN),
    'resource_in' => 'Nova/Models',

    'initial_path' => '/resources/users',

    //    'assets' => ['js/nova.js', 'css/nova.css'],

    'assets' => env('NOVA_ASSETS_TEST') ? [env('NOVA_ASSETS_TEST')] : [],

    'policies' => [
        'namespace' => 'App\\Nova\\Policies\\',
    ],

];
