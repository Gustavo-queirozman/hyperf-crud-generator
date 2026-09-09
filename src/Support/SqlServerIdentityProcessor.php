<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

use Hyperf\Database\Exception\QueryException;
use Hyperf\Database\Query\Builder;
use Hyperf\Database\Query\Processors\Processor;
use PDO;
use PDOException;
use RuntimeException;

final class SqlServerIdentityProcessor extends Processor
{
    /** ODBC returns BIGINT as strings. Read SCOPE_IDENTITY in the same batch as INSERT, including with triggers. */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $connection = $query->getConnection();
        $batch = $sql . '; SELECT CAST(SCOPE_IDENTITY() AS bigint) AS crud_insert_id';
        try {
            $statement = $connection->getPdo()->prepare($batch);
            $connection->bindValues($statement, $connection->prepareBindings($values));
            $statement->execute();
            do {
                if ($statement->columnCount() > 0) {
                    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                        if (isset($row['crud_insert_id'])) {
                            $connection->recordsHaveBeenModified();
                            return (string) $row['crud_insert_id'];
                        }
                    }
                }
            } while ($statement->nextRowset());
        } catch (PDOException $exception) {
            throw new QueryException($batch, $values, $exception);
        } finally {
            if (isset($statement)) {
                $statement->closeCursor();
            }
        }
        throw new RuntimeException('SQL Server did not return the inserted identity.');
    }
}
