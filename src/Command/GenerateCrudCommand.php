<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Command;

use GustavoQueiroz\HyperfCrudGenerator\Generator\CrudGenerator;
use GustavoQueiroz\HyperfCrudGenerator\Generator\GeneratorContext;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Throwable;

final class GenerateCrudCommand extends HyperfCommand
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly CrudGenerator $generator,
    ) {
        parent::__construct('crud:generate');
    }

    protected function configure()
    {
        $this
            ->setDescription('Generate a CRUD structure for a Hyperf resource.')
            ->addArgument('model', InputArgument::REQUIRED, 'Model name, e.g. User')
            ->addOption('table', null, InputOption::VALUE_OPTIONAL, 'Database table name')
            ->addOption('components', null, InputOption::VALUE_OPTIONAL, 'Comma-separated components')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Generate all supported components')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing generated files');
    }

    public function handle(): int
    {
        try {
            /** @var ConfigInterface $config */
            $config = $this->container->get(ConfigInterface::class);
            $model = (string) $this->input->getArgument('model');
            $table = $this->input->getOption('table');
            $force = (bool) $this->input->getOption('force') || (bool) $config->get('crud_generator.force', false);

            $context = new GeneratorContext(
                model: $model,
                table: is_string($table) && $table !== '' ? $table : null,
                namespace: (string) $config->get('crud_generator.namespace', 'App'),
                basePath: (string) $config->get('crud_generator.base_path', BASE_PATH . '/app'),
                routesFile: (string) $config->get('crud_generator.routes_file', BASE_PATH . '/config/routes.php'),
                openApiPath: (string) $config->get('crud_generator.openapi_path', BASE_PATH . '/docs/openapi'),
                testPath: (string) $config->get('crud_generator.test_path', BASE_PATH . '/test/Cases'),
                force: $force,
            );

            $components = $this->resolveComponents($config);
            $created = $this->generator->generate($context, $components);

            $this->info(sprintf('CRUD generated for %s (%s).', $context->model, $context->table));
            foreach ($created as $path) {
                $this->line('  - ' . $path);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function resolveComponents(ConfigInterface $config): array
    {
        if ((bool) $this->input->getOption('all')) {
            return CrudGenerator::COMPONENTS;
        }

        $option = $this->input->getOption('components');
        if (is_string($option) && trim($option) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $option))));
        }

        $components = $config->get('crud_generator.components', [
            'model', 'store_request', 'update_request', 'repository', 'service', 'controller', 'routes',
        ]);

        return is_array($components) ? $components : [];
    }
}
