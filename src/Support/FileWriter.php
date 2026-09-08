<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

use RuntimeException;

final class FileWriter
{
    public function read(string $path, string $default = ''): string
    {
        if (! is_file($path)) {
            return $default;
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read: ' . $path);
        }
        return $contents;
    }

    public function markedBlock(string $current, string $marker, string $contents, bool $force = false): string
    {
        $start = '// <hyperf-crud-generator:' . $marker . '>';
        $end = '// </hyperf-crud-generator:' . $marker . '>';
        $block = $start . "\n" . trim($contents) . "\n" . $end;
        $startCount = substr_count($current, $start);
        $endCount = substr_count($current, $end);
        if ($startCount !== $endCount || $startCount > 1) {
            throw new RuntimeException('Malformed or duplicate generated route markers: ' . $marker);
        }
        $pattern = '/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s';
        if ($startCount === 1) {
            if (! preg_match($pattern, $current, $match)) {
                throw new RuntimeException('Reversed generated route markers: ' . $marker);
            }
            if (str_replace("\r\n", "\n", $match[0]) !== $block && ! $force) {
                throw new RuntimeException('Route block already exists: ' . $marker . '. Use --force to replace it.');
            }
            // A callback preserves literal $ characters in generated routes.
            return preg_replace_callback($pattern, static fn () => $block, $current);
        }
        foreach (token_get_all($current) as $token) {
            if (is_array($token) && $token[0] === T_CLOSE_TAG) {
                throw new RuntimeException('Remove the PHP closing tag from the routes file before adding routes.');
            }
        }
        return rtrim($current) . "\n\n" . $block . "\n";
    }

    /** All conflicts are checked before creating any output file. */
    public function preflight(array $files, bool $force, array $mergedPaths = []): void
    {
        foreach ($files as $path => $contents) {
            if (file_exists($path) && ! is_file($path)) {
                throw new RuntimeException('Destination is not a regular file: ' . $path);
            }
            if (is_file($path) && ! $force && ! in_array($path, $mergedPaths, true) && $this->read($path) !== $contents) {
                throw new RuntimeException('File already exists: ' . $path . '. Use --force to overwrite.');
            }
            if (str_ends_with($path, '.php')) {
                token_get_all($contents, TOKEN_PARSE);
            }
            $ancestor = dirname($path);
            while (! file_exists($ancestor) && dirname($ancestor) !== $ancestor) {
                $ancestor = dirname($ancestor);
            }
            if (! is_dir($ancestor) || ! is_writable($ancestor)) {
                throw new RuntimeException('Destination directory is not writable: ' . $ancestor);
            }
        }
    }

    public function write(string $path, string $contents, bool $force = false): void
    {
        $this->preflight([$path => $contents], $force);
        if (is_file($path) && $this->read($path) === $contents) {
            return;
        }
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create directory: ' . $directory);
        }
        $temporary = tempnam($directory, '.crud-');
        if ($temporary === false) {
            throw new RuntimeException('Unable to stage file: ' . $path);
        }
        try {
            if (file_put_contents($temporary, $contents) === false || ! rename($temporary, $path)) {
                throw new RuntimeException('Unable to write file: ' . $path);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public function upsertMarkedBlock(string $path, string $marker, string $contents, bool $force = false): void
    {
        $current = $this->read($path, "<?php\n\ndeclare(strict_types=1);\n");
        $this->write($path, $this->markedBlock($current, $marker, $contents, $force), true);
    }
}
