<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

use RuntimeException;

final class StubRenderer
{
    public function render(string $stub, array $variables): string
    {
        if (! is_file($stub)) {
            throw new RuntimeException(sprintf('Stub not found: %s', $stub));
        }

        $contents = file_get_contents($stub);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read stub: %s', $stub));
        }

        return preg_replace_callback('/\{\{ ([a-z_]+) \}\}/', static function ($match) use ($variables) {
            if (! array_key_exists($match[1], $variables)) {
                throw new RuntimeException('Unresolved stub variable: ' . $match[1]);
            }
            return (string) $variables[$match[1]];
        }, $contents);
    }
}
