<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGeneratorTest\Unit;

use GustavoQueiroz\HyperfCrudGenerator\Command\GenerateCrudCommand;
use GustavoQueiroz\HyperfCrudGenerator\Command\GenerateDatabaseCommand;
use GustavoQueiroz\HyperfCrudGenerator\Command\GenerateTableCommand;
use GustavoQueiroz\HyperfCrudGenerator\Generator\CrudGenerator;
use GustavoQueiroz\HyperfCrudGenerator\Schema\QueryExecutor;
use GustavoQueiroz\HyperfCrudGenerator\Schema\SchemaInspector;
use GustavoQueiroz\HyperfCrudGenerator\Support\FileWriter;
use GustavoQueiroz\HyperfCrudGenerator\Support\StubRenderer;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CommandTest extends TestCase
{
    private function tester(string $class = GenerateCrudCommand::class): CommandTester
    {
        defined('BASE_PATH') || define('BASE_PATH', sys_get_temp_dir() . '/crud-command-preview');
        $config = $this->createMock(ConfigInterface::class);
        $values = ['databases.default' => ['driver' => 'pgsql'], 'crud_generator.exclude_tables' => ['migrations']];
        $config->method('get')->willReturnCallback(static fn ($key, $default = null) => $values[$key] ?? $default);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(ConfigInterface::class)->willReturn($config);
        $executor = $this->createMock(QueryExecutor::class);
        $executor->method('select')->willReturnCallback(static function ($connection, $sql) {
            if (str_contains($sql, 'information_schema.tables')) {
                return [['table_name' => 'users'], ['table_name' => 'migrations']];
            }
            if (str_contains($sql, 'information_schema.columns')) {
                return [['column_name' => 'id', 'data_type' => 'int4', 'is_nullable' => 'NO', 'is_identity' => 'YES']];
            }
            if (str_contains($sql, 'pg_catalog.pg_index')) {
                return [['index_name' => 'pk', 'column_name' => 'id', 'is_primary' => true]];
            }
            return [];
        });
        $reflection = new \ReflectionClass($class);
        $command = $reflection->newInstanceWithoutConstructor();
        // Command parsing is tested without entering a coroutine; no engine constants are needed.
        (new \ReflectionProperty(\Hyperf\Command\Command::class, 'hookFlags'))->setValue($command, 0);
        $reflection->getConstructor()->invoke($command, $container, new CrudGenerator(new StubRenderer(), new FileWriter()), new SchemaInspector($executor));
        $property = new \ReflectionProperty(\Hyperf\Command\Command::class, 'coroutine');
        $property->setValue($command, false);
        return new CommandTester($command);
    }

    public function testTableAliasInfersModelAndDoesNotWrite(): void
    {
        $tester = $this->tester(GenerateTableCommand::class);
        self::assertSame(0, $tester->execute(['model' => 'users', '--dry-run' => true, '--components' => 'model']));
        self::assertStringContainsString('User.php', $tester->getDisplay());
        self::assertStringContainsString('Preview validated: 1 table(s)', $tester->getDisplay());
    }

    public function testDatabaseAliasExcludesMigrations(): void
    {
        $tester = $this->tester(GenerateDatabaseCommand::class);
        self::assertSame(0, $tester->execute(['--diff' => true, '--components' => 'model']));
        self::assertStringContainsString('+++ b/', $tester->getDisplay());
        self::assertStringContainsString('Preview validated: 1 table(s)', $tester->getDisplay());
    }

    public function testInvalidOptionsFailBeforeGeneration(): void
    {
        $tester = $this->tester();
        self::assertSame(1, $tester->execute(['model' => 'User', '--database' => true]));
        self::assertStringContainsString('cannot be combined', $tester->getDisplay());
    }

    public function testMissingAllowlistTableFailsClearly(): void
    {
        $tester = $this->tester();
        self::assertSame(1, $tester->execute(['--database' => true, '--tables' => 'missing']));
        self::assertStringContainsString('Tables not found: missing', $tester->getDisplay());
    }
}
