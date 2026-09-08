<?php

declare(strict_types=1);

return [
    'namespace' => 'App',
    'base_path' => BASE_PATH . '/app',
    'routes_file' => BASE_PATH . '/config/routes.php',
    'openapi_path' => BASE_PATH . '/docs/openapi',
    'test_path' => BASE_PATH . '/test/Cases',
    'test_namespace' => 'HyperfTest\\Cases',
    'binding_path' => BASE_PATH . '/config/crud-generator',
    'connection' => 'default',
    'schema' => null,
    'exclude_tables' => ['migrations'],
    // Fully qualified table => model class basename. Also enables relations to existing models.
    'model_map' => [],
    // These columns remain writable but are omitted from responses, filtering and sorting.
    'hidden' => ['password', 'password_hash', 'remember_token', 'api_token', 'secret'],
    'force' => false,
    'components' => \GustavoQueiroz\HyperfCrudGenerator\Generator\CrudGenerator::COMPONENTS,
];
