<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Schema;

interface QueryExecutor
{
    public function select(string $connection, string $sql, array $bindings = []): array;
}
