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
            'key_type' => var_export($key->kind() === 'integer' ? 'int' : 'string', true),
            'incrementing' => $key->identity ? 'true' : 'false',
            'timestamps' => (in_array('created_at', $names, true) && ! $table->column('created_at')->generated)
                || (in_array('updated_at', $names, true) && ! $table->column('updated_at')->generated) ? 'true' : 'false',
            'created_at' => in_array('created_at', $names, true) && ! $table->column('created_at')->generated ? "'created_at'" : 'null',
            'updated_at' => in_array('updated_at', $names, true) && ! $table->column('updated_at')->generated ? "'updated_at'" : 'null',
            'fillable' => var_export($writable, true),
            'update_fields' => var_export(array_values(array_diff($writable, [$key->name])), true),
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
            'assign_key' => $key->kind() === 'uuid' && $key->default !== null
                ? '        $data[' . var_export($key->name, true) . '] ??= \GustavoQueiroz\HyperfCrudGenerator\Support\Uuid::v4();'
                : '',
            'store_rules' => $this->rules($context, false),
            'update_rules' => $this->rules($context, true),
            'example_payload' => var_export($this->example($context), true),
            'factory_payload' => $this->factory($context),
            'update_payload' => var_export($this->updateExample($context), true),
            'test_id' => var_export($key->kind() === 'uuid' ? 'd52031f2-026c-4a79-83f8-e8892c734c81' : ($key->kind() === 'integer' ? 1 : '1'), true),
        ];
    }

    private function rules(GeneratorContext $context, bool $update): string
    {
        $table = $context->schema;
        $lines = [];
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
            foreach ($table->uniqueKeys as $unique) {
                if ($unique !== [$column->name]) {
                    continue; // Composite constraints remain enforced by the database.
                }
                $rule = '\Hyperf\Validation\Rule::unique(' . var_export($context->connection . '.' . $context->table, true)
                    . ', ' . var_export($column->name, true) . ')';
                if ($update) {
                    $rule .= '->ignore($this->route(\'id\'), ' . var_export($table->key()->name, true) . ')';
                }
                $rules[] = $rule;
            }
            foreach ($table->foreignKeys as $foreign) {
                if ($foreign['columns'] === [$column->name]) {
                    $rules[] = '\Hyperf\Validation\Rule::exists('
                        . var_export($context->connection . '.' . $foreign['schema'] . '.' . $foreign['table'], true)
                        . ', ' . var_export($foreign['references'][0], true) . ')';
                }
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
}
