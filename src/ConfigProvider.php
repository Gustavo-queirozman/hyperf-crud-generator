<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator;

use GustavoQueiroz\HyperfCrudGenerator\Command\GenerateCrudCommand;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
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
