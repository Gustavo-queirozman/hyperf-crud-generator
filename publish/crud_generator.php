<?php

declare(strict_types=1);

return [
    'namespace' => 'App',
    'base_path' => BASE_PATH . '/app',
    'routes_file' => BASE_PATH . '/config/routes.php',
    'openapi_path' => BASE_PATH . '/docs/openapi',
    'test_path' => BASE_PATH . '/test/Cases',
    'force' => false,
    'components' => [
        'model',
        'store_request',
        'update_request',
        'repository',
        'service',
        'controller',
        'routes',
    ],
];
