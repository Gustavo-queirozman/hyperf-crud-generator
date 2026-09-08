<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

use InvalidArgumentException;

final readonly class Table
{
    /** @param Column[] $columns */
    public function __construct(
        public string $name,
        public string $schema,
        public array $columns,
        public array $primaryKey,
        public array $uniqueKeys = [],
        public array $foreignKeys = [],
    ) {
    }

    public function column(string $name): Column
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }
        throw new InvalidArgumentException("Unknown column {$this->name}.{$name}");
    }

    public function key(): Column
    {
        if (count($this->primaryKey) !== 1) {
            throw new InvalidArgumentException("Table {$this->name} requires a single primary key for Hyperf ORM CRUD; composite or missing primary keys are not supported.");
        }
        return $this->column($this->primaryKey[0]);
    }

    public function writable(bool $update = false): array
    {
        return array_values(array_filter($this->columns, fn (Column $c) => ! $c->generated
            && ! $c->identity && ! in_array($c->name, ['created_at', 'updated_at', 'deleted_at'], true)
            && (! $update || ! in_array($c->name, $this->primaryKey, true))));
    }
}
