<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Command;

use GustavoQueiroz\HyperfCrudGenerator\Generator\CrudGenerator;
use GustavoQueiroz\HyperfCrudGenerator\Schema\SchemaInspector;
use Psr\Container\ContainerInterface;

final class GenerateTableCommand extends GenerateCrudCommand
{
    public function __construct(ContainerInterface $container, CrudGenerator $generator, SchemaInspector $inspector)
    {
        parent::__construct($container, $generator, $inspector, 'crud:generate-table');
    }
}
