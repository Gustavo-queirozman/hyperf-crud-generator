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

        foreach ($variables as $key => $value) {
            $contents = str_replace('{{ ' . $key . ' }}', (string) $value, $contents);
        }

        return $contents;
    }
}
