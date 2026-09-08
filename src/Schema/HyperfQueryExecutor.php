<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

use Hyperf\DbConnection\Db;

final class HyperfQueryExecutor implements QueryExecutor
{
    public function select(string $connection, string $sql, array $bindings = []): array
    {
        return array_map(static fn ($row) => (array) $row, Db::connection($connection)->select($sql, $bindings));
    }
}
