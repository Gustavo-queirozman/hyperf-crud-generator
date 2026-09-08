<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Generator;

use GustavoQueiroz\HyperfCrudGenerator\Support\Name;

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
    ) {
        $this->model = Name::studly($model);
        $this->table = $table ?: Name::pluralSnake($model);
        $this->resource = Name::kebabPlural($model);
    }

    public function variables(): array
    {
        return [
            'namespace' => $this->namespace,
            'model' => $this->model,
            'table' => $this->table,
            'resource' => $this->resource,
        ];
    }
}
