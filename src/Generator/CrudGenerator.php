<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Generator;

use GustavoQueiroz\HyperfCrudGenerator\Support\FileWriter;
use GustavoQueiroz\HyperfCrudGenerator\Support\Name;
use GustavoQueiroz\HyperfCrudGenerator\Support\StubRenderer;
use GustavoQueiroz\HyperfCrudGenerator\Support\Manifest;
use InvalidArgumentException;

final class CrudGenerator
{
    public const COMPONENTS = [
        'model', 'dto', 'resource', 'store_request', 'update_request',
        'repository_interface', 'repository', 'binding', 'service', 'controller',
        'factory', 'seeder', 'policy', 'routes', 'openapi', 'swagger', 'test', 'integration_test',
    ];

    private const DEPENDENCIES = [
        'resource' => ['model'],
        'repository' => ['model', 'resource', 'repository_interface'],
        'binding' => ['repository'],
        'service' => ['dto', 'repository_interface', 'repository', 'binding'],
        'controller' => ['service', 'resource', 'store_request', 'update_request', 'policy'],
        'routes' => ['controller'],
        'factory' => ['model'],
        'seeder' => ['factory'],
        'test' => ['controller'],
        'integration_test' => ['repository', 'factory'],
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
        if ($contexts === []) {
            throw new InvalidArgumentException('No tables selected.');
        }
        $components = $this->resolveComponents($components);
        $files = $mergedPaths = $models = $resources = [];
        $manifest = new Manifest();
        $manifests = [];
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
            $manifestPath = ($context->bindingPath ?? dirname($context->routesFile) . '/crud-generator') . '/manifest.json';
            $manifests[$manifestPath] ??= $manifest->load($manifestPath);
            foreach ($components as $component) {
                if (in_array($component, ['routes', 'swagger'], true)) {
                    $path = $context->routesFile;
                    $current = $files[$path] ?? $this->writer->read($path, "<?php\n\ndeclare(strict_types=1);\n");
                    $routeContents = $this->render($component, $variables, $context);
                    $routeKey = $manifest->key($path, dirname($context->basePath)) . '#' . ($component === 'swagger' ? '_swagger' : $context->model);
                    $canReplace = $context->force;
                    if ($context->regenerate && preg_match('~// <hyperf-crud-generator:' . preg_quote($component === 'swagger' ? '_swagger' : $context->model, '~') . '>\R(.*?)\R// </hyperf-crud-generator:~s', $current, $match)) {
                        $canReplace = $canReplace || ($manifests[$manifestPath]['files'][$routeKey] ?? null) === $manifest->hash(trim($match[1]));
                    }
                    $files[$path] = $this->writer->markedBlock($current,
                        $component === 'swagger' ? '_swagger' : $context->model,
                        $routeContents, $canReplace);
                    $manifests[$manifestPath]['files'][$routeKey] = $manifest->hash(trim($routeContents));
                    $mergedPaths[] = $path;
                    continue;
                }
                foreach ($this->destinations($component, $context) as $stub => $path) {
                    if (isset($files[$path])) {
                        throw new InvalidArgumentException('Duplicate generated file: ' . $path);
                    }
                    $contents = $component === 'openapi'
                        ? json_encode((new OpenApiGenerator())->document($context), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"
                        : $this->render($stub, $variables, $context);
                    $key = $manifest->key($path, dirname($context->basePath));
                    $existing = $this->writer->read($path);
                    if ($existing !== '') {
                        $contents = $manifest->preserve($contents, $existing);
                        if ($context->regenerate && ($manifests[$manifestPath]['files'][$key] ?? null) === $manifest->hash($existing)) {
                            $mergedPaths[] = $path;
                        }
                    }
                    $files[$path] = $contents;
                    $manifests[$manifestPath]['files'][$key] = $manifest->hash($contents);
                }
            }
        }
        foreach ($manifests as $path => $data) {
            ksort($data['files']);
            $files[$path] = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $mergedPaths[] = $path;
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
            'policy' => ['policy' => "$base/Policy/{$model}Policy.php"],
            'integration_test' => ['repository_test' => rtrim($c->testPath, '/\\') . "/{$model}RepositoryIntegrationTest.php"],
            'openapi' => ['openapi' => rtrim($c->openApiPath, '/\\') . '/' . Name::snake($model) . '.json'],
            'test' => [
                'test' => rtrim($c->testPath, '/\\') . "/{$model}ControllerTest.php",
                'service_test' => rtrim($c->testPath, '/\\') . "/{$model}ServiceTest.php",
            ],
            default => throw new InvalidArgumentException('Unknown component: ' . $component),
        };
    }

    private function render(string $stub, array $variables, GeneratorContext $context): string
    {
        $custom = $context->stubPath !== null ? rtrim($context->stubPath, '/\\') . '/' . $stub . '.stub' : null;
        $path = $custom !== null && is_file($custom) ? $custom : dirname(__DIR__, 2) . '/stubs/' . $stub . '.stub';
        return $this->renderer->render($path, $variables);
    }
}
