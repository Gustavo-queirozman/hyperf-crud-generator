<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use GustavoQueiroz\HyperfCrudGenerator\Generator\CrudGenerator;
use GustavoQueiroz\HyperfCrudGenerator\Support\FileWriter;
use GustavoQueiroz\HyperfCrudGenerator\Support\StubRenderer;
use GustavoQueiroz\HyperfCrudGeneratorTest\Fixture;

$root = __DIR__ . '/output';
$generator = new CrudGenerator(new StubRenderer(), new FileWriter());
$generator->generate(Fixture::context($root, force: true), CrudGenerator::COMPONENTS);
spl_autoload_register(static function (string $class) use ($root): void {
    foreach (['GeneratedApp\\' => $root . '/app/', 'GeneratedTest\\' => $root . '/test/Cases/'] as $prefix => $directory) {
        if (str_starts_with($class, $prefix)) {
            $path = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($path)) {
                require $path;
            }
        }
    }
});
