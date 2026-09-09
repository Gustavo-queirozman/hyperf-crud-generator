<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

/** Keep the compatibility processor local to queries from generated models. */
trait SqlServerIdentity
{
    protected function newBaseQueryBuilder()
    {
        $query = parent::newBaseQueryBuilder();
        $connection = $query->getConnection();
        if ($connection->getConfig('driver') === 'sqlsrv' && $connection->getConfig('odbc') === true) {
            $query->processor = new SqlServerIdentityProcessor();
        }
        return $query;
    }
}
