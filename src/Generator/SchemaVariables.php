<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Generator;

use GustavoQueiroz\HyperfCrudGenerator\Schema\Column;
use GustavoQueiroz\HyperfCrudGenerator\Support\Name;

final class SchemaVariables
{
    public function build(GeneratorContext $context): array
    {
        $table = $context->schema ?? throw new \InvalidArgumentException('Schema metadata is required. Select a configured database connection.');
        $key = $table->key();
        $modelKey = $key;
        foreach ($table->primaryKey as $primaryName) {
            if ($table->column($primaryName)->identity) {
                $modelKey = $table->column($primaryName);
                break;
            }
        }
        $keyParameters = $this->keyParameters($table->primaryKey);
        $testIdentifier = [];
        foreach ($table->primaryKey as $name) {
            $testIdentifier[$name] = $this->exampleValue($table->column($name));
        }
        $identifierExpression = count($keyParameters) === 1
            ? '$' . $keyParameters[0]['parameter']
            : '[' . implode(', ', array_map(
                static fn (array $item): string => var_export($item['column'], true) . ' => $' . $item['parameter'],
                $keyParameters
            )) . ']';
        $routeMiddlewares = array_values(array_unique(array_merge([
            \GustavoQueiroz\HyperfCrudGenerator\Http\ValidationMiddleware::class,
            \Hyperf\Validation\Middleware\ValidationMiddleware::class,
        ], array_values(array_filter($context->routeMiddlewares, 'is_string')))));
        $casts = [];
        foreach ($table->columns as $column) {
            if ($column->cast() !== null) {
                $casts[$column->name] = $column->cast();
            }
        }
        $names = array_map(static fn (Column $c) => $c->name, $table->columns);
        $writable = array_map(static fn (Column $c) => $c->name, $table->writable());
        $hidden = array_values(array_intersect($names, $context->hidden));
        $visible = array_values(array_diff($names, $hidden, array_map(static fn ($c) => $c->name, array_filter($table->columns, static fn ($c) => $c->kind() === 'binary'))));
        $sortable = array_values(array_filter($visible, fn ($name) => ! in_array($table->column($name)->kind(), ['json', 'binary'], true)));
        $searchable = array_values(array_filter($visible, fn ($name) => $table->column($name)->kind() === 'string'));
        $dates = [];
        foreach ($visible as $name) {
            if (in_array($table->column($name)->kind(), ['date', 'datetime'], true)) {
                $dates[$name] = $table->column($name)->kind() === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i:sP';
            }
        }
        $relations = [];
        $used = array_map('strtolower', $names);
        foreach ($table->foreignKeys as $fk) {
            $target = $context->modelMap[$fk['schema'] . '.' . $fk['table']] ?? null;
            if (count($fk['columns']) !== 1 || $target === null) {
                continue;
            }
            $method = 'related' . Name::studly($fk['columns'][0]);
            if (in_array(strtolower($method), $used, true)) {
                continue;
            }
            $used[] = strtolower($method);
            $relations[] = '    public function ' . $method . '(): \Hyperf\Database\Model\Relations\BelongsTo' . "\n    {\n"
                . '        return $this->belongsTo(\\' . $context->namespace . '\\Model\\' . Name::studly($target)
                . '::class, ' . var_export($fk['columns'][0], true) . ', ' . var_export($fk['references'][0], true) . ");\n    }";
        }
        foreach ($context->relatedTables as $related) {
            $target = $context->modelMap[$related->schema . '.' . $related->name] ?? null;
            if ($target === null) {
                continue;
            }
            foreach ($related->foreignKeys as $fk) {
                if ($fk['schema'] !== $table->schema || $fk['table'] !== $table->name || count($fk['columns']) !== 1) {
                    continue;
                }
                $method = 'related' . Name::studly($target) . 'By' . Name::studly($fk['columns'][0]);
                if (in_array(strtolower($method), $used, true)) {
                    continue;
                }
                $used[] = strtolower($method);
                $one = in_array($fk['columns'], $related->uniqueKeys, true) || $related->primaryKey === $fk['columns'];
                $relation = $one ? 'HasOne' : 'HasMany';
                $relations[] = '    public function ' . $method . '(): \Hyperf\Database\Model\Relations\\' . $relation . "\n    {\n"
                    . '        return $this->' . lcfirst($relation) . '(\\' . $context->namespace . '\\Model\\' . Name::studly($target)
                    . '::class, ' . var_export($fk['columns'][0], true) . ', ' . var_export($fk['references'][0], true) . ");\n    }";
            }
        }
        return $context->variables() + [
            'table_literal' => var_export($context->table, true),
            'connection_literal' => var_export($context->connection, true),
            'primary_key' => var_export($key->name, true),
            'model_primary_key' => var_export($modelKey->name, true),
            'key_type' => var_export($modelKey->kind() === 'integer' ? 'int' : 'string', true),
            'incrementing' => $modelKey->identity ? 'true' : 'false',
            'timestamps' => (in_array('created_at', $names, true) && ! $table->column('created_at')->generated)
                || (in_array('updated_at', $names, true) && ! $table->column('updated_at')->generated) ? 'true' : 'false',
            'created_at' => in_array('created_at', $names, true) && ! $table->column('created_at')->generated ? "'created_at'" : 'null',
            'updated_at' => in_array('updated_at', $names, true) && ! $table->column('updated_at')->generated ? "'updated_at'" : 'null',
            'fillable' => var_export($writable, true),
            'update_fields' => var_export(array_values(array_diff($writable, $table->primaryKey)), true),
            'casts' => var_export($casts, true),
            'hidden' => var_export($hidden, true),
            'visible' => var_export($visible, true),
            'sortable' => var_export($sortable, true),
            'searchable' => var_export($searchable, true),
            'dates' => var_export($dates, true),
            'filter_keys' => var_export('array:' . implode(',', $sortable), true),
            'filter_rules' => $this->filterRules($context, $sortable),
            'relations' => implode("\n\n", $relations),
            'soft_deletes' => in_array('deleted_at', $names, true) && $table->column('deleted_at')->nullable
                ? '    use \Hyperf\Database\Model\SoftDeletes;' : '',
            'assign_key' => implode("\n", array_map(
                static fn (string $name): string => '        $data[' . var_export($name, true)
                    . '] ??= \GustavoQueiroz\HyperfCrudGenerator\Support\Uuid::v4();',
                array_values(array_filter($table->primaryKey, fn (string $name): bool =>
                    $table->column($name)->kind() === 'uuid' && $table->column($name)->default !== null
                ))
            )),
            'store_rules' => $this->rules($context, false),
            'update_rules' => $this->rules($context, true),
            'example_payload' => var_export($this->example($context), true),
            'factory_payload' => $this->factory($context),
            'update_payload' => var_export($this->updateExample($context), true),
            'primary_keys' => var_export($table->primaryKey, true),
            'route_identifier' => implode('', array_map(static fn (array $item): string => '/{' . $item['parameter'] . '}', $keyParameters)),
            'route_middlewares' => var_export($routeMiddlewares, true),
            'controller_identifier_signature' => implode(', ', array_map(static fn (array $item): string => 'string $' . $item['parameter'], $keyParameters)),
            'controller_identifier_use' => implode(', ', array_map(static fn (array $item): string => '$' . $item['parameter'], $keyParameters)),
            'identifier_expression' => $identifierExpression,
            'repository_delete' => $this->repositoryDelete($table),
            'repository_create_return' => $table->hasCompositeKey()
                ? '        return $this->findOrFail($this->identifier($model));'
                : '        return $model->refresh();',
            'repository_update' => $table->hasCompositeKey()
                ? "        \$id = \$this->identifier(\$model);\n        \$model->fill(\$data);\n        if (\$model->getDirty() !== []) {\n            \$this->queryByIdentifier(\$id)->update(\$model->getDirty());\n        }\n        return \$this->findOrFail(\$id);"
                : "        \$model->fill(\$data);\n        \$model->save();\n        return \$model->refresh();",
            'test_id' => var_export(count($testIdentifier) === 1 ? reset($testIdentifier) : $testIdentifier, true),
            'test_identifier_payload' => var_export($testIdentifier, true),
            'test_controller_arguments' => implode(', ', array_map(
                fn (array $item): string => '(string) ' . var_export($testIdentifier[$item['column']], true),
                $keyParameters
            )),
            'model_identifier' => count($table->primaryKey) === 1
                ? '$model->getAttribute(' . var_export($key->name, true) . ')'
                : '[' . implode(', ', array_map(static fn (string $name): string =>
                    var_export($name, true) . ' => $model->getAttribute(' . var_export($name, true) . ')', $table->primaryKey)) . ']',
            'updated_identifier' => count($table->primaryKey) === 1
                ? '$updated->getAttribute(' . var_export($key->name, true) . ')'
                : '[' . implode(', ', array_map(static fn (string $name): string =>
                    var_export($name, true) . ' => $updated->getAttribute(' . var_export($name, true) . ')', $table->primaryKey)) . ']',
        ];
    }

    private function rules(GeneratorContext $context, bool $update): string
    {
        $table = $context->schema;
        $lines = [];
        $writableNames = array_map(static fn (Column $column): string => $column->name, $table->writable($update));
        $routeByColumn = [];
        foreach ($this->keyParameters($table->primaryKey) as $parameter) {
            $routeByColumn[$parameter['column']] = $parameter['parameter'];
        }
        foreach ($table->writable($update) as $column) {
            $requiredKey = in_array($column->name, $table->primaryKey, true) && $column->kind() !== 'uuid';
            $rules = [var_export($update || (! $requiredKey && ($column->nullable || $column->default !== null)) ? 'sometimes' : 'required', true)];
            $rules[] = var_export($column->nullable ? 'nullable' : 'required', true);
            $type = match ($column->kind()) {
                'integer' => 'integer', 'bigint', 'decimal', 'number' => 'numeric',
                'boolean' => 'boolean', 'json' => 'array',
                'date' => 'date_format:Y-m-d', 'datetime' => 'date',
                'uuid' => 'uuid', 'time' => 'date_format:H:i:s',
                default => 'string',
            };
            $rules[] = var_export($type, true);
            if ($column->kind() === 'bigint') {
                $rules[] = var_export('regex:/^-?[0-9]+$/', true);
            }
            if ($column->kind() === 'decimal' && $column->precision !== null && $column->scale !== null) {
                $whole = max(1, $column->precision - $column->scale);
                $pattern = '/^-?[0-9]{1,' . $whole . '}' . ($column->scale > 0 ? '(\.[0-9]{1,' . $column->scale . '})?' : '') . '$/';
                $rules[] = var_export('regex:' . $pattern, true);
            }
            if ($column->length !== null && $column->kind() === 'string') {
                $rules[] = var_export('max:' . $column->length, true);
            }
            if ($column->unsigned) {
                $rules[] = "'min:0'";
            }
            if ($column->enum !== []) {
                $rules[] = '\Hyperf\Validation\Rule::in(' . var_export($column->enum, true) . ')';
            }
            $rules = array_merge($rules, $this->checkRules($table->checks, $column));
            foreach ($table->uniqueKeys as $unique) {
                $applicable = array_values(array_intersect($unique, $writableNames));
                if (! in_array($column->name, $applicable, true)) {
                    continue;
                }
                if (count($unique) > 1) {
                    $requiredWith = array_values(array_diff($applicable, [$column->name]));
                    if ($requiredWith !== []) {
                        $rules[] = var_export('required_with:' . implode(',', $requiredWith), true);
                    }
                    if ($column->name !== $applicable[array_key_last($applicable)]) {
                        continue;
                    }
                }
                $rule = '\Hyperf\Validation\Rule::unique(' . var_export($context->connection . '.' . $context->table, true)
                    . ', ' . var_export($column->name, true) . ')';
                $scope = array_values(array_diff($unique, [$column->name]));
                if ($scope !== []) {
                    $clauses = [];
                    foreach ($scope as $name) {
                        $value = in_array($name, $writableNames, true)
                            ? '$this->input(' . var_export($name, true) . ')'
                            : ($update && isset($routeByColumn[$name])
                                ? '$this->route(' . var_export($routeByColumn[$name], true) . ')' : null);
                        if ($value === null) {
                            continue 2;
                        }
                        $clauses[] = '->where(' . var_export($name, true) . ', ' . $value . ')';
                    }
                    $rule .= '->where(fn ($query) => $query' . implode('', $clauses) . ')';
                }
                if ($update) {
                    if (count($table->primaryKey) === 1) {
                        $rule .= '->ignore($this->route(\'id\'), ' . var_export($table->key()->name, true) . ')';
                    } else {
                        $parameters = $this->keyParameters($table->primaryKey);
                        $exclusions = [];
                        foreach ($parameters as $parameter) {
                            $method = $exclusions === [] ? 'where' : 'orWhere';
                            $exclusions[] = '->' . $method . '(' . var_export($parameter['column'], true)
                                . ', \'!=\', $this->route(' . var_export($parameter['parameter'], true) . '))';
                        }
                        $rule .= '->where(fn ($query) => $query->where(fn ($current) => $current' . implode('', $exclusions) . '))';
                    }
                }
                $rules[] = $rule;
            }
            foreach ($table->foreignKeys as $foreign) {
                $position = array_search($column->name, $foreign['columns'], true);
                if ($position === false) {
                    continue;
                }
                $applicable = array_values(array_intersect($foreign['columns'], $writableNames));
                if (count($foreign['columns']) > 1) {
                    $requiredWith = array_values(array_diff($applicable, [$column->name]));
                    if ($requiredWith !== []) {
                        $rules[] = var_export('required_with:' . implode(',', $requiredWith), true);
                    }
                    if ($column->name !== $applicable[array_key_last($applicable)]) {
                        continue;
                    }
                }
                $rule = '\Hyperf\Validation\Rule::exists('
                    . var_export($context->connection . '.' . $foreign['schema'] . '.' . $foreign['table'], true)
                    . ', ' . var_export($foreign['references'][$position], true) . ')';
                $scope = [];
                foreach ($foreign['columns'] as $index => $local) {
                    if ($index !== $position) {
                        $value = in_array($local, $writableNames, true)
                            ? '$this->input(' . var_export($local, true) . ')'
                            : ($update && isset($routeByColumn[$local])
                                ? '$this->route(' . var_export($routeByColumn[$local], true) . ')' : null);
                        if ($value === null) {
                            continue 2;
                        }
                        $scope[] = '->where(' . var_export($foreign['references'][$index], true)
                            . ', ' . $value . ')';
                    }
                }
                if ($scope !== []) {
                    $rule .= '->where(fn ($query) => $query' . implode('', $scope) . ')';
                }
                $rules[] = $rule;
            }
            $lines[] = '            ' . var_export($column->name, true) . ' => [' . implode(', ', array_unique($rules)) . '],';
        }
        $allowed = array_map(static fn ($c) => $c->name, $table->writable($update));
        foreach ($table->columns as $column) {
            if (! in_array($column->name, $allowed, true)) {
                $lines[] = '            ' . var_export($column->name, true) . " => ['prohibited'],";
            }
        }
        return implode("\n", $lines);
    }

    private function filterRules(GeneratorContext $context, array $columns): string
    {
        $lines = [];
        foreach ($columns as $name) {
            $column = $context->schema->column($name);
            $type = match ($column->kind()) {
                'integer' => 'integer', 'bigint' => 'regex:/^-?[0-9]+$/',
                'decimal', 'number' => 'numeric', 'boolean' => 'boolean',
                'uuid' => 'uuid', 'date', 'datetime' => 'date',
                default => 'string',
            };
            $lines[] = '            ' . var_export('filter.' . $name, true) . ' => [\'sometimes\', '
                . ($column->nullable ? "'nullable', " : '') . var_export($type, true) . '],';
        }
        return implode("\n", $lines);
    }

    public function example(GeneratorContext $context): array
    {
        $values = [];
        foreach ($context->schema->writable() as $c) {
            if ($c->default !== null && (! in_array($c->name, $context->schema->primaryKey, true) || $c->kind() === 'uuid')) {
                continue;
            }
            $values[$c->name] = $c->enum[0] ?? match ($c->kind()) {
                'integer' => 1, 'bigint' => '1', 'decimal' => '1.00', 'number' => 1.5,
                'boolean' => true, 'json' => ['example' => 'value'],
                'uuid' => 'd52031f2-026c-4a79-83f8-e8892c734c81',
                'date' => '2026-01-01', 'datetime' => '2026-01-01 12:00:00', 'time' => '12:00:00',
                default => substr('example', 0, $c->length ?? 7),
            };
        }
        return $values;
    }

    private function updateExample(GeneratorContext $context): array
    {
        $foreign = [];
        foreach ($context->schema->foreignKeys as $fk) {
            $foreign = array_merge($foreign, $fk['columns']);
        }
        foreach ($context->schema->writable(true) as $c) {
            if (! in_array($c->name, $foreign, true) && $c->kind() === 'string' && $c->enum === []) {
                return [$c->name => substr('updated', 0, $c->length ?? 7)];
            }
        }
        return [];
    }

    private function factory(GeneratorContext $context): string
    {
        $lines = [];
        foreach ($this->example($context) as $name => $example) {
            $c = $context->schema->column($name);
            $isFk = false;
            foreach ($context->schema->foreignKeys as $fk) {
                $isFk = $isFk || in_array($name, $fk['columns'], true);
            }
            if ($isFk && ! $c->nullable) {
                $value = '$overrides[' . var_export($name, true) . '] ?? throw new \InvalidArgumentException('
                    . var_export('Provide an existing foreign-key value for ' . $name, true) . ')';
            } elseif ($c->nullable) {
                $value = 'null';
            } elseif ($c->kind() === 'uuid') {
                $value = 'self::uuid()';
            } elseif ($c->kind() === 'string' && $c->enum === [] && in_array([$name], $context->schema->uniqueKeys, true)) {
                $value = 'substr(bin2hex(random_bytes(16)), 0, ' . min(32, $c->length ?? 32) . ')';
            } else {
                $value = var_export($example, true);
            }
            $lines[] = '            ' . var_export($name, true) . ' => ' . $value . ',';
        }
        return implode("\n", $lines);
    }

    /** @return array<int, array{column: string, parameter: string}> */
    private function keyParameters(array $primaryKey): array
    {
        $composite = count($primaryKey) > 1;
        return array_map(static fn (string $column, int $index): array => [
            'column' => $column,
            'parameter' => $composite ? 'key' . ($index + 1) : 'id',
        ], $primaryKey, array_keys($primaryKey));
    }

    private function exampleValue(Column $column): string
    {
        return match ($column->kind()) {
            'uuid' => 'd52031f2-026c-4a79-83f8-e8892c734c81',
            default => '1',
        };
    }

    private function repositoryDelete(\GustavoQueiroz\HyperfCrudGenerator\Schema\Table $table): string
    {
        if (! $table->hasCompositeKey()) {
            return '        $model->delete();';
        }
        $softDeletes = false;
        foreach ($table->columns as $column) {
            $softDeletes = $softDeletes || ($column->name === 'deleted_at' && $column->nullable);
        }
        return $softDeletes
            ? "        \$this->queryByIdentifier(\$this->identifier(\$model))->update(['deleted_at' => \$model->freshTimestampString()]);"
            : '        $this->queryByIdentifier($this->identifier($model))->delete();';
    }

    private function checkRules(array $checks, Column $column): array
    {
        $rules = [];
        $quoted = preg_quote($column->name, '/');
        $identifier = '(?:"' . $quoted . '"|`' . $quoted . '`|\[' . $quoted . '\]|\b' . $quoted . '\b)';
        foreach ($checks as $check) {
            $expression = (string) ($check['expression'] ?? '');
            if (preg_match('/\bOR\b/i', $expression)) {
                continue;
            }
            if (preg_match('/' . $identifier . '\s+BETWEEN\s+\(?\s*(-?\d+(?:\.\d+)?)\s*\)?\s+AND\s+\(?\s*(-?\d+(?:\.\d+)?)\s*\)?/i', $expression, $match)) {
                $rules[] = var_export('between:' . $match[1] . ',' . $match[2], true);
            } else {
                if (preg_match('/' . $identifier . '\s*>=\s*\(?\s*(-?\d+(?:\.\d+)?)/i', $expression, $match)) {
                    $rules[] = var_export('min:' . $match[1], true);
                }
                if (preg_match('/' . $identifier . '\s*<=\s*\(?\s*(-?\d+(?:\.\d+)?)/i', $expression, $match)) {
                    $rules[] = var_export('max:' . $match[1], true);
                }
            }
            if ($column->kind() === 'string' && preg_match('/(?:CHAR_LENGTH|CHARACTER_LENGTH|LEN|LENGTH)\s*\(\s*' . $identifier . '\s*\)\s*<=\s*(\d+)/i', $expression, $match)) {
                $rules[] = var_export('max:' . $match[1], true);
            }
            if (preg_match('/' . $identifier . '\s+IN\s*\(([^()]*)\)/i', $expression, $match)) {
                preg_match_all("/'((?:''|[^'])*)'|(-?\\d+(?:\\.\\d+)?)/", $match[1], $values, PREG_SET_ORDER);
                $allowed = array_map(static fn (array $value): string|int|float => $value[1] !== ''
                    ? str_replace("''", "'", $value[1])
                    : (str_contains($value[2], '.') ? (float) $value[2] : (int) $value[2]), $values);
                if ($allowed !== []) {
                    $rules[] = '\Hyperf\Validation\Rule::in(' . var_export($allowed, true) . ')';
                }
            }
        }
        return $rules;
    }
}
