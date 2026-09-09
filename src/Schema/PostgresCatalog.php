<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

final class PostgresCatalog implements Catalog
{
    public function tables(): string
    {
        return "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY table_name";
    }

    public function columns(): string
    {
        return <<<'SQL'
SELECT c.column_name, c.udt_name AS data_type, c.is_nullable, c.column_default,
       c.character_maximum_length AS max_length, c.numeric_precision, c.numeric_scale,
       c.is_identity, c.is_generated, c.generation_expression,
       (SELECT json_agg(e.enumlabel ORDER BY e.enumsortorder)::text FROM pg_catalog.pg_enum e
        JOIN pg_catalog.pg_type t ON t.oid = e.enumtypid
        JOIN pg_catalog.pg_namespace n ON n.oid = t.typnamespace
        WHERE t.typname = c.udt_name AND n.nspname = c.udt_schema) AS enum_values
FROM information_schema.columns c
WHERE c.table_schema = ? AND c.table_name = ? ORDER BY c.ordinal_position
SQL;
    }

    public function indexes(): string
    {
        return <<<'SQL'
SELECT idx.relname AS index_name, att.attname AS column_name, key.ordinality AS position,
       i.indisprimary AS is_primary
FROM pg_catalog.pg_index i
JOIN pg_catalog.pg_class tab ON tab.oid = i.indrelid
JOIN pg_catalog.pg_namespace ns ON ns.oid = tab.relnamespace
JOIN pg_catalog.pg_class idx ON idx.oid = i.indexrelid
CROSS JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS key(attnum, ordinality)
JOIN pg_catalog.pg_attribute att ON att.attrelid = tab.oid AND att.attnum = key.attnum
WHERE ns.nspname = ? AND tab.relname = ? AND i.indisunique
  AND i.indpred IS NULL AND i.indexprs IS NULL AND key.ordinality <= i.indnkeyatts
ORDER BY idx.relname, key.ordinality
SQL;
    }

    public function foreignKeys(): string
    {
        return <<<'SQL'
SELECT con.conname AS constraint_name, local.attname AS column_name,
       fns.nspname AS foreign_schema, ft.relname AS foreign_table,
       remote.attname AS foreign_column, key.ordinality AS position,
       CASE con.confupdtype WHEN 'c' THEN 'CASCADE' WHEN 'n' THEN 'SET NULL' WHEN 'd' THEN 'SET DEFAULT' WHEN 'r' THEN 'RESTRICT' ELSE 'NO ACTION' END AS on_update,
       CASE con.confdeltype WHEN 'c' THEN 'CASCADE' WHEN 'n' THEN 'SET NULL' WHEN 'd' THEN 'SET DEFAULT' WHEN 'r' THEN 'RESTRICT' ELSE 'NO ACTION' END AS on_delete
FROM pg_catalog.pg_constraint con
JOIN pg_catalog.pg_class tab ON tab.oid = con.conrelid
JOIN pg_catalog.pg_namespace ns ON ns.oid = tab.relnamespace
JOIN pg_catalog.pg_class ft ON ft.oid = con.confrelid
JOIN pg_catalog.pg_namespace fns ON fns.oid = ft.relnamespace
CROSS JOIN LATERAL unnest(con.conkey, con.confkey) WITH ORDINALITY AS key(local_num, foreign_num, ordinality)
JOIN pg_catalog.pg_attribute local ON local.attrelid = tab.oid AND local.attnum = key.local_num
JOIN pg_catalog.pg_attribute remote ON remote.attrelid = ft.oid AND remote.attnum = key.foreign_num
WHERE ns.nspname = ? AND tab.relname = ? AND con.contype = 'f'
ORDER BY con.conname, key.ordinality
SQL;
    }

    public function checks(): string
    {
        return <<<'SQL'
SELECT con.conname AS constraint_name, pg_get_constraintdef(con.oid, true) AS expression
FROM pg_catalog.pg_constraint con
JOIN pg_catalog.pg_class tab ON tab.oid = con.conrelid
JOIN pg_catalog.pg_namespace ns ON ns.oid = tab.relnamespace
WHERE ns.nspname = ? AND tab.relname = ? AND con.contype = 'c'
ORDER BY con.conname
SQL;
    }
}
