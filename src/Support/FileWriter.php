<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

use RuntimeException;

final class FileWriter
{
    public function write(string $path, string $contents, bool $force = false): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory: %s', $directory));
        }

        if (is_file($path) && ! $force) {
            throw new RuntimeException(sprintf('File already exists: %s. Use --force to overwrite.', $path));
        }

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Unable to write file: %s', $path));
        }
    }

    public function upsertMarkedBlock(string $path, string $marker, string $contents): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory: %s', $directory));
        }

        $current = is_file($path) ? (string) file_get_contents($path) : "<?php\n\ndeclare(strict_types=1);\n";
        $start = sprintf('// <hyperf-crud-generator:%s>', $marker);
        $end = sprintf('// </hyperf-crud-generator:%s>', $marker);
        $block = $start . PHP_EOL . trim($contents) . PHP_EOL . $end;

        $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';
        if (preg_match($pattern, $current)) {
            $current = preg_replace($pattern, $block, $current) ?? $current;
        } else {
            $current = rtrim($current) . PHP_EOL . PHP_EOL . $block . PHP_EOL;
        }

        if (file_put_contents($path, $current) === false) {
            throw new RuntimeException(sprintf('Unable to update file: %s', $path));
        }
    }
}
