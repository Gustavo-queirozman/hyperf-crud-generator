<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Command;

use GustavoQueiroz\HyperfCrudGenerator\Generator\CrudGenerator;
use GustavoQueiroz\HyperfCrudGenerator\Generator\GeneratorContext;
use GustavoQueiroz\HyperfCrudGenerator\Schema\SchemaInspector;
use GustavoQueiroz\HyperfCrudGenerator\Support\Name;
use GustavoQueiroz\HyperfCrudGenerator\Support\Diff;
use Hyperf\Command\Command;
use Hyperf\Contract\ConfigInterface;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

class GenerateCrudCommand extends Command
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly CrudGenerator $generator,
        private readonly SchemaInspector $inspector,
        string $name = 'crud:generate',
    ) {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Generate a complete Hyperf API from an existing database schema.')
            ->addArgument('model', InputArgument::OPTIONAL, 'Model name, e.g. User')
            ->addOption('table', null, InputOption::VALUE_REQUIRED, 'Table name; model is inferred when omitted')
            ->addOption('database', null, InputOption::VALUE_NONE, 'Generate all tables in the selected database schema')
            ->addOption('tables', null, InputOption::VALUE_REQUIRED, 'Comma-separated table allowlist for --database')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Comma-separated tables to exclude')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Connection name in config/autoload/databases.php')
            ->addOption('schema', null, InputOption::VALUE_REQUIRED, 'PostgreSQL/SQL Server schema or MySQL/MariaDB database')
            ->addOption('components', null, InputOption::VALUE_REQUIRED, 'Comma-separated components; dependencies are included')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Generate every component')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Inspect and validate the output without writing files')
            ->addOption('diff', null, InputOption::VALUE_NONE, 'Print a unified diff without writing files')
            ->addOption('regenerate', null, InputOption::VALUE_NONE, 'Update previously generated, unmodified files and preserve custom sections')
            ->addOption('skip-unsupported', null, InputOption::VALUE_NONE, 'In batch mode, report and skip tables with missing/composite primary keys')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Replace existing generated files and marked route blocks');
    }

    public function handle(): int
    {
        try {
            $config = $this->container->get(ConfigInterface::class);
            $connection = (string) ($this->input->getOption('connection') ?? $config->get('crud_generator.connection', 'default'));
            $database = $config->get('databases.' . $connection);
            if (! is_array($database)) {
                throw new InvalidArgumentException('Database connection is not configured: ' . $connection);
            }
            if (! empty($database['prefix'])) {
                throw new InvalidArgumentException('Use a connection without a table prefix for schema generation and generated models.');
            }
            $driver = (string) ($database['driver'] ?? 'mysql');
            $defaultSchema = match ($driver) {
                'pgsql', 'postgres', 'postgresql' => 'public',
                'sqlsrv', 'sqlserver', 'mssql' => 'dbo',
                default => (string) ($database['database'] ?? ''),
            };
            $schema = (string) ($this->input->getOption('schema') ?? $config->get('crud_generator.schema') ?? $defaultSchema);
            if ($schema === '') {
                throw new InvalidArgumentException('Provide --schema or configure the database name.');
            }
            $batch = (bool) $this->input->getOption('database') || $this->getName() === 'crud:generate-database';
            $model = $this->input->getArgument('model');
            $table = $this->input->getOption('table');
            if ($this->getName() === 'crud:generate-table' && $model !== null) {
                if ($table !== null) {
                    throw new InvalidArgumentException('Use either the positional table or --table.');
                }
                $table = $model;
                $model = null;
            }
            if ($batch && ($model !== null || $table !== null)) {
                throw new InvalidArgumentException('--database cannot be combined with a model or --table.');
            }
            if (! $batch && ($this->input->getOption('tables') !== null || $this->input->getOption('skip-unsupported'))) {
                throw new InvalidArgumentException('--tables and --skip-unsupported require --database.');
            }
            if (! $batch && $model === null && $table === null) {
                throw new InvalidArgumentException('Provide a model, --table, or --database.');
            }
            if (is_string($table) && str_contains($table, '.')) {
                [$schema, $table] = explode('.', $table, 2);
            }
            $tables = $batch ? $this->inspector->tables($connection, $driver, $schema) : [$table ?? Name::pluralSnake($model)];
            $selected = $this->csv($this->input->getOption('tables'));
            if ($selected !== []) {
                $missing = array_diff($selected, $tables);
                if ($missing !== []) {
                    throw new InvalidArgumentException('Tables not found: ' . implode(', ', $missing));
                }
                $tables = array_values(array_intersect($tables, $selected));
            }
            $exclude = $this->csv($this->input->getOption('exclude'));
            if ($batch) {
                $exclude = array_merge($config->get('crud_generator.exclude_tables', ['migrations']), $exclude);
            }
            $tables = array_values(array_diff($tables, $exclude));
            $metadata = [];
            $map = $config->get('crud_generator.model_map', []);
            foreach ($tables as $tableName) {
                $meta = $this->inspector->inspect($tableName, $connection, $driver, $schema);
                try {
                    $meta->key();
                } catch (InvalidArgumentException $e) {
                    if (! $batch || ! $this->input->getOption('skip-unsupported')) {
                        throw $e;
                    }
                    $this->warn($e->getMessage());
                    continue;
                }
                $metadata[] = $meta;
                $map[$schema . '.' . $tableName] = ! $batch && is_string($model)
                    ? Name::studly($model)
                    : ($map[$schema . '.' . $tableName] ?? Name::modelFromTable($tableName));
            }
            $contexts = [];
            foreach ($metadata as $meta) {
                $contexts[] = new GeneratorContext(
                    model: $map[$meta->schema . '.' . $meta->name],
                    table: $meta->schema . '.' . $meta->name,
                    namespace: $config->get('crud_generator.namespace', 'App'),
                    basePath: $config->get('crud_generator.base_path', BASE_PATH . '/app'),
                    routesFile: $config->get('crud_generator.routes_file', BASE_PATH . '/config/routes.php'),
                    openApiPath: $config->get('crud_generator.openapi_path', BASE_PATH . '/docs/openapi'),
                    testPath: $config->get('crud_generator.test_path', BASE_PATH . '/test/Cases'),
                    force: (bool) $this->input->getOption('force') || $config->get('crud_generator.force', false),
                    schema: $meta, connection: $connection,
                    dryRun: (bool) $this->input->getOption('dry-run') || (bool) $this->input->getOption('diff'),
                    modelMap: $map,
                    testNamespace: $config->get('crud_generator.test_namespace', 'HyperfTest\\Cases'),
                    hidden: $config->get('crud_generator.hidden', ['password', 'password_hash', 'remember_token', 'api_token', 'secret']),
                    bindingPath: $config->get('crud_generator.binding_path', BASE_PATH . '/config/crud-generator'),
                    relatedTables: $metadata,
                    regenerate: (bool) $this->input->getOption('regenerate'),
                    stubPath: $config->get('crud_generator.stub_path'),
                );
            }
            $components = $this->input->getOption('all') ? CrudGenerator::COMPONENTS
                : ($this->csv($this->input->getOption('components')) ?: $config->get('crud_generator.components', CrudGenerator::COMPONENTS));
            if ($this->input->getOption('diff')) {
                $files = $this->generator->plan($contexts, $components);
                foreach ($files as $path => $contents) {
                    $this->output->write(Diff::unified($path, is_file($path) ? (string) file_get_contents($path) : '', $contents), false, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
                }
                $paths = array_keys($files);
            } else {
                $paths = $this->generator->generateBatch($contexts, $components);
            }
            $this->info(sprintf('%s %d table(s), %d file(s).',
                $this->input->getOption('dry-run') || $this->input->getOption('diff') ? 'Preview validated:' : 'Generated:', count($contexts), count($paths)));
            foreach ($paths as $path) {
                $this->line('  - ' . $path);
            }
            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Query exceptions contain SQL and bindings; keep connection internals out of CLI output.
            $this->error($exception instanceof \Hyperf\Database\Exception\QueryException
                ? 'Schema query failed. Check the configured connection, catalog permissions and database version.'
                : $exception->getMessage());
            return self::FAILURE;
        }
    }

    private function csv(mixed $value): array
    {
        return is_string($value) ? array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($v) => $v !== '')) : [];
    }
}
