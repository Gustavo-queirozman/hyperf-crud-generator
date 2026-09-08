<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

final class SqlServerCatalog implements Catalog
{
    public function tables(): string
    {
        return 'SELECT t.name AS table_name FROM sys.tables t JOIN sys.schemas s ON s.schema_id = t.schema_id WHERE s.name = ? AND t.is_ms_shipped = 0 ORDER BY t.name';
    }

    public function columns(): string
    {
        return <<<'SQL'
SELECT c.name AS column_name, ty.name AS data_type,
       CASE WHEN c.is_nullable = 1 THEN 'YES' ELSE 'NO' END AS is_nullable,
       d.definition AS column_default,
       CASE WHEN c.max_length < 0 THEN NULL WHEN ty.name IN ('nvarchar', 'nchar') THEN c.max_length / 2 ELSE c.max_length END AS max_length,
       c.precision AS numeric_precision, c.scale AS numeric_scale,
       c.is_identity, c.is_computed AS is_generated
FROM sys.columns c
JOIN sys.tables t ON t.object_id = c.object_id
JOIN sys.schemas s ON s.schema_id = t.schema_id
JOIN sys.types ty ON ty.user_type_id = c.system_type_id AND ty.user_type_id = ty.system_type_id
LEFT JOIN sys.default_constraints d ON d.object_id = c.default_object_id
WHERE s.name = ? AND t.name = ? ORDER BY c.column_id
SQL;
    }

    public function indexes(): string
    {
        return <<<'SQL'
SELECT i.name AS index_name, c.name AS column_name, ic.key_ordinal AS position, i.is_primary_key AS is_primary
FROM sys.indexes i
JOIN sys.tables t ON t.object_id = i.object_id
JOIN sys.schemas s ON s.schema_id = t.schema_id
JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE s.name = ? AND t.name = ? AND i.is_unique = 1 AND i.has_filter = 0 AND ic.key_ordinal > 0
ORDER BY i.name, ic.key_ordinal
SQL;
    }

    public function foreignKeys(): string
    {
        return <<<'SQL'
SELECT fk.name AS constraint_name, c.name AS column_name, fs.name AS foreign_schema,
       ft.name AS foreign_table, fc.name AS foreign_column, fkc.constraint_column_id AS position
FROM sys.foreign_keys fk
JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
JOIN sys.tables t ON t.object_id = fk.parent_object_id
JOIN sys.schemas s ON s.schema_id = t.schema_id
JOIN sys.columns c ON c.object_id = t.object_id AND c.column_id = fkc.parent_column_id
JOIN sys.tables ft ON ft.object_id = fk.referenced_object_id
JOIN sys.schemas fs ON fs.schema_id = ft.schema_id
JOIN sys.columns fc ON fc.object_id = ft.object_id AND fc.column_id = fkc.referenced_column_id
WHERE s.name = ? AND t.name = ? ORDER BY fk.name, fkc.constraint_column_id
SQL;
    }
}
