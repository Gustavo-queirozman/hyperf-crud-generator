<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Generator;

use GustavoQueiroz\HyperfCrudGenerator\Schema\Column;

final class OpenApiGenerator
{
    public function document(GeneratorContext $context): array
    {
        $table = $context->schema;
        $model = $context->model;
        $schemas = [];
        foreach (['Response', 'Store', 'Update'] as $mode) {
            $columns = match ($mode) {
                'Store' => $table->writable(), 'Update' => $table->writable(true),
                default => array_filter($table->columns, fn ($c) => ! in_array($c->name, $context->hidden, true) && $c->kind() !== 'binary'),
            };
            $properties = $required = [];
            foreach ($columns as $column) {
                $properties[$column->name] = $this->property($column);
                if ($mode === 'Store' && ! $column->nullable && $column->default === null) {
                    $required[] = $column->name;
                }
                if ($mode === 'Response' && ($column->identity || $column->generated || in_array($column->name, ['created_at', 'updated_at', 'deleted_at'], true))) {
                    $properties[$column->name]['readOnly'] = true;
                }
                if ($mode !== 'Response' && in_array($column->name, $context->hidden, true)) {
                    $properties[$column->name]['writeOnly'] = true;
                }
            }
            $schema = ['type' => 'object', 'properties' => $properties ?: new \stdClass()];
            if ($required !== []) {
                $schema['required'] = $required;
            }
            $schemas[$model . $mode] = $schema;
        }
        $ref = static fn (string $name) => ['$ref' => '#/components/schemas/' . $name];
        $json = static fn (array $schema) => ['application/json' => ['schema' => $schema]];
        $error = ['type' => 'object', 'properties' => [
            'message' => ['type' => 'string'],
            'errors' => ['type' => 'object', 'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']]],
        ], 'required' => ['message']];
        $errors = [
            '404' => ['description' => 'Resource not found', 'content' => $json($error)],
            '409' => ['description' => 'Database constraint conflict', 'content' => $json($error)],
            '422' => ['description' => 'Validation failed', 'content' => $json($error)],
        ];
        $item = ['type' => 'object', 'required' => ['data'], 'properties' => ['data' => $ref($model . 'Response')]];
        $success = static fn (array $schema, string $description = 'OK') => ['description' => $description, 'content' => $json($schema)];
        $operation = static fn (string $action) => ['tags' => [$model], 'operationId' => lcfirst($model) . ucfirst($action), 'summary' => ucfirst($action) . ' ' . $model];
        $sortable = array_values(array_map(static fn ($c) => $c->name, array_filter($table->columns,
            fn ($c) => ! in_array($c->name, $context->hidden, true) && ! in_array($c->kind(), ['json', 'binary'], true))));
        $query = static fn ($name, $schema) => ['name' => $name, 'in' => 'query', 'schema' => $schema];
        $list = $operation('list') + [
            'parameters' => [
                $query('page', ['type' => 'integer', 'minimum' => 1, 'default' => 1]),
                $query('per_page', ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 15]),
                $query('sort', ['type' => 'string', 'enum' => $sortable, 'default' => $table->key()->name]),
                $query('direction', ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc']),
                $query('search', ['type' => 'string', 'maxLength' => 255]),
                ['name' => 'filter', 'in' => 'query', 'style' => 'deepObject', 'explode' => true,
                    'description' => 'Equality filters. Allowed columns: ' . implode(', ', $sortable),
                    'schema' => ['type' => 'object', 'additionalProperties' => ['type' => 'string']]],
            ],
            'responses' => [
                '200' => $success(['type' => 'object', 'properties' => [
                    'data' => ['type' => 'array', 'items' => $ref($model . 'Response')],
                    'meta' => ['type' => 'object', 'properties' => array_fill_keys(
                        ['current_page', 'per_page', 'total', 'last_page'], ['type' => 'integer']
                    )],
                ], 'required' => ['data', 'meta']]),
                '422' => $errors['422'],
            ],
        ];
        $store = $operation('create') + [
            'requestBody' => ['required' => true, 'content' => $json($ref($model . 'Store'))],
            'responses' => ['201' => $success($item, 'Created'), '409' => $errors['409'], '422' => $errors['422']],
        ];
        $update = $operation('update') + [
            'description' => 'Updates only supplied writable fields. Primary key and generated columns are immutable.',
            'requestBody' => ['required' => true, 'content' => $json($ref($model . 'Update'))],
            'responses' => ['200' => $success($item)] + $errors,
        ];
        $id = $this->property($table->key());
        unset($id['nullable'], $id['readOnly']);
        return [
            'openapi' => '3.0.3',
            'info' => ['title' => $model . ' API', 'version' => '1.0.0'],
            'servers' => [['url' => '/']],
            'paths' => [
                '/' . $context->resource => ['get' => $list, 'post' => $store],
                '/' . $context->resource . '/{id}' => [
                    'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => $id]],
                    'get' => $operation('show') + ['responses' => ['200' => $success($item), '404' => $errors['404']]],
                    'put' => $update,
                    'patch' => array_replace($update, ['operationId' => lcfirst($model) . 'Patch']),
                    'delete' => $operation('delete') + ['responses' => [
                        '204' => ['description' => 'Deleted'], '404' => $errors['404'], '409' => $errors['409'],
                    ]],
                ],
            ],
            'components' => ['schemas' => $schemas],
            'x-database' => ['table' => $context->table, 'primaryKey' => $table->primaryKey,
                'uniqueKeys' => $table->uniqueKeys, 'foreignKeys' => $table->foreignKeys],
        ];
    }

    private function property(Column $column): array
    {
        $schema = match ($column->kind()) {
            'integer' => ['type' => 'integer', 'format' => 'int32'],
            'bigint' => ['type' => 'string', 'pattern' => '^-?[0-9]+$', 'description' => '64-bit integer serialized as a string to preserve precision.'],
            'decimal' => ['type' => 'string', 'pattern' => '^-?[0-9]+(\.[0-9]+)?$', 'description' => 'Exact decimal serialized as a string.'],
            'number' => ['type' => 'number', 'format' => 'double'],
            'boolean' => ['type' => 'boolean'],
            'json' => ['oneOf' => [['type' => 'object', 'additionalProperties' => true], ['type' => 'array', 'items' => new \stdClass()]]],
            'uuid' => ['type' => 'string', 'format' => 'uuid'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            'binary' => ['type' => 'string', 'format' => 'binary'],
            default => ['type' => 'string'],
        };
        if ($column->nullable) {
            $schema['nullable'] = true;
        }
        if ($column->kind() === 'string' && $column->length !== null) {
            $schema['maxLength'] = $column->length;
        }
        if ($column->enum !== []) {
            $schema['enum'] = $column->enum;
        }
        // Database defaults may be SQL expressions, so never advertise them as literal JSON defaults.
        if ($column->default !== null) {
            $schema['x-database-default'] = $column->default;
        }
        return $schema;
    }
}
