<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Generator;

use GustavoQueiroz\HyperfCrudGenerator\Support\FileWriter;
use GustavoQueiroz\HyperfCrudGenerator\Support\Name;
use GustavoQueiroz\HyperfCrudGenerator\Support\StubRenderer;
use InvalidArgumentException;

final class CrudGenerator
{
    public const COMPONENTS = [
        'model',
        'store_request',
        'update_request',
        'repository',
        'service',
        'controller',
        'routes',
        'openapi',
        'test',
    ];

    public function __construct(
        private readonly StubRenderer $renderer,
        private readonly FileWriter $writer,
    ) {
    }

    public function generate(GeneratorContext $context, array $components): array
    {
        $components = array_values(array_unique($components));
        $unknown = array_diff($components, self::COMPONENTS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown components: ' . implode(', ', $unknown));
        }

        $created = [];
        foreach ($components as $component) {
            $path = $this->generateComponent($component, $context);
            $created[] = $path;
        }

        return $created;
    }

    private function generateComponent(string $component, GeneratorContext $context): string
    {
        $map = [
            'model' => ['model.stub', $context->basePath . '/Model/' . $context->model . '.php'],
            'store_request' => ['store_request.stub', $context->basePath . '/Request/' . $context->model . '/Store' . $context->model . 'Request.php'],
            'update_request' => ['update_request.stub', $context->basePath . '/Request/' . $context->model . '/Update' . $context->model . 'Request.php'],
            'repository' => ['repository.stub', $context->basePath . '/Repository/' . $context->model . 'Repository.php'],
            'service' => ['service.stub', $context->basePath . '/Service/' . $context->model . 'Service.php'],
            'controller' => ['controller.stub', $context->basePath . '/Controller/' . $context->model . 'Controller.php'],
            'openapi' => ['openapi.stub', rtrim($context->openApiPath, '/') . '/' . Name::snake($context->model) . '.yaml'],
            'test' => ['test.stub', rtrim($context->testPath, '/') . '/' . $context->model . 'ControllerTest.php'],
        ];

        if ($component === 'routes') {
            $contents = $this->renderer->render($this->stubPath('routes.stub'), $context->variables());
            $this->writer->upsertMarkedBlock($context->routesFile, $context->model, $contents);
            return $context->routesFile . ' [route block]';
        }

        [$stub, $path] = $map[$component];
        $contents = $this->renderer->render($this->stubPath($stub), $context->variables());
        $this->writer->write($path, $contents, $context->force);

        return $path;
    }

    private function stubPath(string $stub): string
    {
        return dirname(__DIR__, 2) . '/stubs/' . $stub;
    }
}
