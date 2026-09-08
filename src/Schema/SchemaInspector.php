<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

use InvalidArgumentException;

final class SchemaInspector
{
    public function __construct(private readonly QueryExecutor $executor)
    {
    }

    public function catalog(string $driver): Catalog
    {
        return match (strtolower($driver)) {
            'mysql', 'mariadb' => new MySqlCatalog(),
            'pgsql', 'postgres', 'postgresql' => new PostgresCatalog(),
            'sqlsrv', 'sqlserver', 'mssql' => new SqlServerCatalog(),
            default => throw new InvalidArgumentException("Unsupported database driver: {$driver}"),
        };
    }

    public function tables(string $connection, string $driver, string $schema): array
    {
        return array_column($this->executor->select($connection, $this->catalog($driver)->tables(), [$schema]), 'table_name');
    }

    public function inspect(string $table, string $connection, string $driver, string $schema): Table
    {
        $catalog = $this->catalog($driver);
        $rows = $this->executor->select($connection, $catalog->columns(), [$schema, $table]);
        if ($rows === []) {
            throw new InvalidArgumentException("Table {$schema}.{$table} was not found or has no visible columns.");
        }
        $columns = [];
        foreach ($rows as $row) {
            $extra = strtolower((string) ($row['extra'] ?? ''));
            $type = strtolower($row['data_type']);
            $identity = $this->truth($row['is_identity'] ?? false) || str_contains($extra, 'auto_increment')
                || str_starts_with((string) ($row['column_default'] ?? ''), 'nextval(');
            $generated = $this->truth($row['is_generated'] ?? false)
                || ! empty($row['generation_expression']) || str_contains($extra, 'on update')
                || ($catalog instanceof SqlServerCatalog && in_array($type, ['timestamp', 'rowversion'], true));
            if ($catalog instanceof SqlServerCatalog && $type === 'timestamp') {
                $type = 'rowversion';
            }
            $enum = isset($row['enum_values']) ? json_decode($row['enum_values'], true, flags: JSON_THROW_ON_ERROR) : [];
            if ($type === 'enum' && isset($row['column_type'])) {
                preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", $row['column_type'], $matches);
                $enum = array_map(static fn ($v) => str_replace("''", "'", stripcslashes($v)), $matches[1]);
            }
            $columns[] = new Column(
                name: $row['column_name'], type: $type, nullable: $this->truth($row['is_nullable']),
                default: $row['column_default'] ?? null, generated: $generated, identity: $identity,
                length: isset($row['max_length']) && (int) $row['max_length'] > 0 ? (int) $row['max_length'] : null,
                precision: isset($row['numeric_precision']) ? (int) $row['numeric_precision'] : null,
                scale: isset($row['numeric_scale']) ? (int) $row['numeric_scale'] : null,
                enum: $enum ?? [], unsigned: str_contains($row['column_type'] ?? '', 'unsigned'),
            );
        }
        $primary = $unique = [];
        foreach ($this->executor->select($connection, $catalog->indexes(), [$schema, $table]) as $row) {
            if ($this->truth($row['is_primary'])) {
                $primary[] = $row['column_name'];
            } else {
                $unique[$row['index_name']][] = $row['column_name'];
            }
        }
        // Functional indexes cannot be represented as ordinary column validation rules.
        $unique = array_filter($unique, static fn ($key) => ! in_array(null, $key, true));
        $foreign = [];
        foreach ($this->executor->select($connection, $catalog->foreignKeys(), [$schema, $table]) as $row) {
            $name = $row['constraint_name'];
            $foreign[$name]['schema'] = $row['foreign_schema'];
            $foreign[$name]['table'] = $row['foreign_table'];
            $foreign[$name]['columns'][] = $row['column_name'];
            $foreign[$name]['references'][] = $row['foreign_column'];
        }
        return new Table($table, $schema, $columns, $primary, array_values($unique), array_values($foreign));
    }

    private function truth(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'YES', 'ALWAYS', 't'], true);
    }
}
