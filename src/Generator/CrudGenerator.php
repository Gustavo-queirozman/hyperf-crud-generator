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
        'model', 'dto', 'resource', 'store_request', 'update_request',
        'repository_interface', 'repository', 'binding', 'service', 'controller',
        'factory', 'seeder', 'routes', 'openapi', 'swagger', 'test',
    ];

    private const DEPENDENCIES = [
        'resource' => ['model'],
        'repository' => ['model', 'resource', 'repository_interface'],
        'binding' => ['repository'],
        'service' => ['dto', 'repository_interface', 'repository', 'binding'],
        'controller' => ['service', 'resource', 'store_request', 'update_request'],
        'routes' => ['controller'],
        'factory' => ['model'],
        'seeder' => ['factory'],
        'test' => ['controller'],
        'swagger' => ['openapi'],
    ];

    public function __construct(private readonly StubRenderer $renderer, private readonly FileWriter $writer)
    {
    }

    public function generate(GeneratorContext $context, array $components): array
    {
        return $this->generateBatch([$context], $components);
    }

    /** @param GeneratorContext[] $contexts */
    public function generateBatch(array $contexts, array $components): array
    {
        if ($contexts === []) {
            throw new InvalidArgumentException('No tables selected.');
        }
        $files = $this->plan($contexts, $components);
        if (! $contexts[0]->dryRun) {
            foreach ($files as $path => $contents) {
                $this->writer->write($path, $contents, true);
            }
        }
        return array_keys($files);
    }

    /** Produces a complete, conflict-checked preview without writing files. */
    public function plan(array $contexts, array $components): array
    {
        $components = $this->resolveComponents($components);
        $files = $mergedPaths = $models = $resources = [];
        foreach ($contexts as $context) {
            if ($context->force !== $contexts[0]->force || $context->dryRun !== $contexts[0]->dryRun) {
                throw new InvalidArgumentException('All batch contexts must use the same force and dry-run options.');
            }
            $modelKey = strtolower($context->namespace . '\\' . $context->model);
            if (isset($models[$modelKey]) || isset($resources[$context->resource])) {
                throw new InvalidArgumentException('Model or route collision for table: ' . $context->table . '. Configure model_map or select tables separately.');
            }
            $models[$modelKey] = $resources[$context->resource] = true;
            $variables = (new SchemaVariables())->build($context);
            foreach ($components as $component) {
                if (in_array($component, ['routes', 'swagger'], true)) {
                    $path = $context->routesFile;
                    $current = $files[$path] ?? $this->writer->read($path, "<?php\n\ndeclare(strict_types=1);\n");
                    $files[$path] = $this->writer->markedBlock($current,
                        $component === 'swagger' ? '_swagger' : $context->model,
                        $this->render($component, $variables), $context->force);
                    $mergedPaths[] = $path;
                    continue;
                }
                foreach ($this->destinations($component, $context) as $stub => $path) {
                    if (isset($files[$path])) {
                        throw new InvalidArgumentException('Duplicate generated file: ' . $path);
                    }
                    $files[$path] = $component === 'openapi'
                        ? json_encode((new OpenApiGenerator())->document($context), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"
                        : $this->render($stub, $variables);
                }
            }
        }
        $this->writer->preflight($files, $contexts[0]->force ?? false, array_unique($mergedPaths));
        return $files;
    }

    public function resolveComponents(array $components): array
    {
        $unknown = array_diff($components, self::COMPONENTS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown components: ' . implode(', ', $unknown));
        }
        if ($components === []) {
            throw new InvalidArgumentException('Select at least one component.');
        }
        $resolved = [];
        $visit = function (string $component) use (&$visit, &$resolved): void {
            foreach (self::DEPENDENCIES[$component] ?? [] as $dependency) {
                if (! isset($resolved[$dependency])) {
                    $visit($dependency);
                }
            }
            $resolved[$component] = true;
        };
        foreach ($components as $component) {
            $visit($component);
        }
        return array_keys($resolved);
    }

    private function destinations(string $component, GeneratorContext $c): array
    {
        $base = rtrim($c->basePath, '/\\');
        $model = $c->model;
        return match ($component) {
            'model' => ['model' => "$base/Model/$model.php"],
            'dto' => ['dto' => "$base/DTO/{$model}Data.php"],
            'resource' => ['resource' => "$base/Resource/{$model}Resource.php"],
            'store_request' => ['store_request' => "$base/Request/$model/Store{$model}Request.php"],
            'update_request' => ['update_request' => "$base/Request/$model/Update{$model}Request.php"],
            'repository_interface' => ['repository_interface' => "$base/Contract/{$model}RepositoryInterface.php"],
            'repository' => ['repository' => "$base/Repository/{$model}Repository.php"],
            'binding' => ['binding' => ($c->bindingPath ?? dirname($c->routesFile) . '/crud-generator') . "/$model.php"],
            'service' => ['service' => "$base/Service/{$model}Service.php"],
            'controller' => ['controller' => "$base/Controller/{$model}Controller.php"],
            'factory' => ['factory' => "$base/Factory/{$model}Factory.php"],
            'seeder' => ['seeder' => "$base/Seeder/{$model}Seeder.php"],
            'openapi' => ['openapi' => rtrim($c->openApiPath, '/\\') . '/' . Name::snake($model) . '.json'],
            'test' => [
                'test' => rtrim($c->testPath, '/\\') . "/{$model}ControllerTest.php",
                'service_test' => rtrim($c->testPath, '/\\') . "/{$model}ServiceTest.php",
            ],
            default => throw new InvalidArgumentException('Unknown component: ' . $component),
        };
    }

    private function render(string $stub, array $variables): string
    {
        return $this->renderer->render(dirname(__DIR__, 2) . '/stubs/' . $stub . '.stub', $variables);
    }
}
