<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator;

use GustavoQueiroz\HyperfCrudGenerator\Command\GenerateCrudCommand;
use GustavoQueiroz\HyperfCrudGenerator\Schema\QueryExecutor;
use GustavoQueiroz\HyperfCrudGenerator\Schema\HyperfQueryExecutor;

final class ConfigProvider
{
    public function __invoke(): array
    {
        $configFile = BASE_PATH . '/config/autoload/crud_generator.php';
        $config = is_file($configFile) ? require $configFile : [];
        $dependencies = [QueryExecutor::class => HyperfQueryExecutor::class];
        foreach (glob(($config['binding_path'] ?? BASE_PATH . '/config/crud-generator') . '/*.php') ?: [] as $file) {
            $dependencies = array_replace($dependencies, require $file);
        }
        return [
            'dependencies' => $dependencies,
            'commands' => [
                GenerateCrudCommand::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'CRUD Generator configuration.',
                    'source' => __DIR__ . '/../publish/crud_generator.php',
                    'destination' => BASE_PATH . '/config/autoload/crud_generator.php',
                ],
            ],
        ];
    }
}
