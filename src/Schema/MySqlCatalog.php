<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

final class MySqlCatalog implements Catalog
{
    public function tables(): string
    {
        return "SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME";
    }

    public function columns(): string
    {
        return <<<'SQL'
SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type, IS_NULLABLE AS is_nullable,
       COLUMN_DEFAULT AS column_default, CHARACTER_MAXIMUM_LENGTH AS max_length,
       NUMERIC_PRECISION AS numeric_precision, NUMERIC_SCALE AS numeric_scale,
       EXTRA AS extra, COLUMN_TYPE AS column_type, GENERATION_EXPRESSION AS generation_expression
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION
SQL;
    }

    public function indexes(): string
    {
        return <<<'SQL'
SELECT INDEX_NAME AS index_name, COLUMN_NAME AS column_name, SEQ_IN_INDEX AS position,
       CASE WHEN INDEX_NAME = 'PRIMARY' THEN 1 ELSE 0 END AS is_primary
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND NON_UNIQUE = 0 ORDER BY INDEX_NAME, SEQ_IN_INDEX
SQL;
    }

    public function foreignKeys(): string
    {
        return <<<'SQL'
SELECT kcu.CONSTRAINT_NAME AS constraint_name, kcu.COLUMN_NAME AS column_name,
       kcu.REFERENCED_TABLE_SCHEMA AS foreign_schema, kcu.REFERENCED_TABLE_NAME AS foreign_table,
       kcu.REFERENCED_COLUMN_NAME AS foreign_column, kcu.ORDINAL_POSITION AS position,
       rc.UPDATE_RULE AS on_update, rc.DELETE_RULE AS on_delete
FROM information_schema.KEY_COLUMN_USAGE kcu
LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
  ON rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA AND rc.TABLE_NAME = kcu.TABLE_NAME
 AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ? AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY kcu.CONSTRAINT_NAME, kcu.ORDINAL_POSITION
SQL;
    }

    public function checks(): string
    {
        return <<<'SQL'
SELECT cc.CONSTRAINT_NAME AS constraint_name, cc.CHECK_CLAUSE AS expression
FROM information_schema.CHECK_CONSTRAINTS cc
JOIN information_schema.TABLE_CONSTRAINTS tc
  ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
WHERE tc.TABLE_SCHEMA = ? AND tc.TABLE_NAME = ? AND tc.CONSTRAINT_TYPE = 'CHECK'
ORDER BY cc.CONSTRAINT_NAME
SQL;
    }
}
