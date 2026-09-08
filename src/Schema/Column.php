<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

final readonly class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public mixed $default = null,
        public bool $generated = false,
        public bool $identity = false,
        public ?int $length = null,
        public ?int $precision = null,
        public ?int $scale = null,
        public array $enum = [],
        public bool $unsigned = false,
    ) {
    }

    public function kind(): string
    {
        return match (strtolower($this->type)) {
            'bool', 'boolean', 'bit' => 'boolean',
            'smallint', 'integer', 'int', 'int2', 'int4', 'tinyint', 'mediumint', 'serial', 'smallserial' => 'integer',
            'bigint', 'int8', 'bigserial' => 'bigint',
            'decimal', 'numeric', 'money', 'smallmoney' => 'decimal',
            'real', 'float', 'double', 'double precision', 'float4', 'float8' => 'number',
            'json', 'jsonb' => 'json',
            'uuid', 'uniqueidentifier' => 'uuid',
            'date' => 'date',
            'datetime', 'datetime2', 'smalldatetime', 'timestamp', 'timestamptz', 'timestamp without time zone', 'timestamp with time zone', 'datetimeoffset' => 'datetime',
            'time', 'timetz', 'time without time zone', 'time with time zone' => 'time',
            'blob', 'binary', 'varbinary', 'bytea', 'image', 'tinyblob', 'mediumblob', 'longblob', 'rowversion' => 'binary',
            default => 'string',
        };
    }

    public function cast(): ?string
    {
        return match ($this->kind()) {
            'integer' => 'integer',
            'bigint' => 'string',
            'boolean' => 'boolean',
            'decimal' => 'decimal:' . ($this->scale ?? 2),
            'number' => 'float',
            'json' => 'json',
            'date' => 'date',
            'datetime' => 'datetime',
            default => null,
        };
    }
}
