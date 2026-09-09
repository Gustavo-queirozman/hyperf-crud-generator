<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Generator;

use GustavoQueiroz\HyperfCrudGenerator\Support\Name;
use GustavoQueiroz\HyperfCrudGenerator\Schema\Table;
use InvalidArgumentException;

final readonly class GeneratorContext
{
    public string $model;
    public string $table;
    public string $resource;

    public function __construct(
        string $model,
        ?string $table,
        public string $namespace,
        public string $basePath,
        public string $routesFile,
        public string $openApiPath,
        public string $testPath,
        public bool $force = false,
        public ?Table $schema = null,
        public string $connection = 'default',
        public bool $dryRun = false,
        public array $modelMap = [],
        public string $testNamespace = 'HyperfTest\\Cases',
        public array $hidden = ['password', 'password_hash', 'remember_token', 'api_token', 'secret'],
        public ?string $bindingPath = null,
        public array $relatedTables = [],
        public bool $regenerate = false,
        public ?string $stubPath = null,
    ) {
        foreach ([$namespace, $testNamespace] as $value) {
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $value)) {
                throw new InvalidArgumentException('Invalid PHP namespace: ' . $value);
            }
        }
        $this->model = Name::studly($model);
        $this->table = $table ?: Name::pluralSnake($model);
        $this->resource = str_replace('_', '-', Name::snake($this->tableName()));
    }

    private function tableName(): string
    {
        $parts = explode('.', $this->table);
        return (string) end($parts);
    }

    public function variables(): array
    {
        return [
            'namespace' => $this->namespace,
            'model' => $this->model,
            'table' => $this->table,
            'resource' => $this->resource,
            'test_namespace' => $this->testNamespace,
        ];
    }
}
