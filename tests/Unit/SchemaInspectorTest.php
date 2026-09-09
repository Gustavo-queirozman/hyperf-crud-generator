<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGeneratorTest\Unit;

use GustavoQueiroz\HyperfCrudGenerator\Schema\Column;
use GustavoQueiroz\HyperfCrudGenerator\Schema\QueryExecutor;
use GustavoQueiroz\HyperfCrudGenerator\Schema\SchemaInspector;
use GustavoQueiroz\HyperfCrudGenerator\Schema\Table;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemaInspectorTest extends TestCase
{
    public static function drivers(): array
    {
        return [['mysql'], ['mariadb'], ['pgsql'], ['sqlsrv']];
    }

    #[DataProvider('drivers')]
    public function testNormalizedMetadataAndBoundIdentifiers(string $driver): void
    {
        $executor = $this->createMock(QueryExecutor::class);
        $catalog = (new SchemaInspector($executor))->catalog($driver);
        $executor->expects(self::exactly(4))->method('select')->willReturnCallback(
            static function ($connection, $sql, $bindings) use ($catalog) {
                self::assertSame('reporting', $connection);
                self::assertSame(['custom', "odd'table"], $bindings);
                self::assertStringNotContainsString("odd'table", $sql);
                if ($sql === $catalog->columns()) {
                    return [
                        ['column_name' => 'code', 'data_type' => 'varchar', 'is_nullable' => 'NO', 'max_length' => 30],
                        ['column_name' => 'total', 'data_type' => 'numeric', 'is_nullable' => 'YES', 'numeric_scale' => 4, 'is_generated' => 1],
                    ];
                }
                if ($sql === $catalog->indexes()) {
                    return [
                        ['index_name' => 'pk', 'column_name' => 'code', 'is_primary' => '1'],
                        ['index_name' => 'unique_pair', 'column_name' => 'code', 'is_primary' => 0],
                        ['index_name' => 'unique_pair', 'column_name' => 'total', 'is_primary' => 0],
                    ];
                }
                if ($sql === $catalog->foreignKeys()) {
                    return [
                    ['constraint_name' => 'fk_pair', 'column_name' => 'code', 'foreign_schema' => 'custom', 'foreign_table' => 'other', 'foreign_column' => 'ref'],
                    ['constraint_name' => 'fk_pair', 'column_name' => 'total', 'foreign_schema' => 'custom', 'foreign_table' => 'other', 'foreign_column' => 'value'],
                    ];
                }
                return [['constraint_name' => 'total_positive', 'expression' => 'CHECK (total >= 0)']];
            }
        );
        $table = (new SchemaInspector($executor))->inspect("odd'table", 'reporting', $driver, 'custom');
        self::assertSame('code', $table->key()->name);
        self::assertSame([['code', 'total']], $table->uniqueKeys);
        self::assertSame(['code', 'total'], $table->foreignKeys[0]['columns']);
        self::assertSame('NO ACTION', $table->foreignKeys[0]['on_delete']);
        self::assertSame('CHECK (total >= 0)', $table->checks[0]['expression']);
        self::assertSame(30, $table->column('code')->length);
        self::assertSame('decimal:4', $table->column('total')->cast());
        self::assertTrue($table->column('total')->generated);
    }

    public function testMysqlEnumIdentityAndOnUpdate(): void
    {
        $executor = $this->createMock(QueryExecutor::class);
        $executor->method('select')->willReturnOnConsecutiveCalls([
            ['column_name' => 'id', 'data_type' => 'int', 'is_nullable' => 'NO', 'extra' => 'auto_increment'],
            ['column_name' => 'status', 'data_type' => 'enum', 'column_type' => "enum('new','it''s done','a,b')", 'is_nullable' => 'NO'],
            ['column_name' => 'modified', 'data_type' => 'timestamp', 'extra' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP', 'is_nullable' => 'NO'],
        ], [['index_name' => 'PRIMARY', 'column_name' => 'id', 'is_primary' => 1]], [], []);
        $table = (new SchemaInspector($executor))->inspect('users', 'default', 'mysql', 'app');
        self::assertTrue($table->key()->identity);
        self::assertSame(['new', "it's done", 'a,b'], $table->column('status')->enum);
        self::assertTrue($table->column('modified')->generated);
    }

    public function testPostgresSerialAndEnum(): void
    {
        $executor = $this->createMock(QueryExecutor::class);
        $executor->method('select')->willReturnOnConsecutiveCalls([
            ['column_name' => 'id', 'data_type' => 'int8', 'is_nullable' => 'NO', 'column_default' => "nextval('users_id_seq'::regclass)"],
            ['column_name' => 'status', 'data_type' => 'user_status', 'is_nullable' => 'NO', 'enum_values' => '["new","active"]'],
        ], [['index_name' => 'pk', 'column_name' => 'id', 'is_primary' => 't']], [], []);
        $table = (new SchemaInspector($executor))->inspect('users', 'default', 'pgsql', 'public');
        self::assertTrue($table->key()->identity);
        self::assertSame(['new', 'active'], $table->column('status')->enum);
    }

    public function testSqlServerTimestampIsNotDate(): void
    {
        $executor = $this->createMock(QueryExecutor::class);
        $executor->method('select')->willReturnOnConsecutiveCalls([
            ['column_name' => 'version', 'data_type' => 'timestamp', 'is_nullable' => 'NO'],
        ], [], [], []);
        $table = (new SchemaInspector($executor))->inspect('users', 'default', 'sqlsrv', 'dbo');
        self::assertSame('binary', $table->column('version')->kind());
        self::assertTrue($table->column('version')->generated);
    }

    public function testCompositeKeyIsExposedInSchemaOrder(): void
    {
        $table = new Table('pivot', 'public', [new Column('a', 'int'), new Column('b', 'int')], ['a', 'b']);
        self::assertTrue($table->hasCompositeKey());
        self::assertSame('a', $table->key()->name);
        self::assertSame(['a', 'b'], $table->primaryKey);
    }

    public function testUnsupportedDriverFails(): void
    {
        $this->expectExceptionMessage('Unsupported database driver');
        (new SchemaInspector($this->createMock(QueryExecutor::class)))->catalog('oracle');
    }
}
