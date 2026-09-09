<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

use RuntimeException;

final class Manifest
{
    private const CUSTOM = '~(?<start>[ \t]*// <crud-custom>\R)(?<body>.*?)(?<end>[ \t]*// </crud-custom>)~s';

    public function hash(string $contents): string
    {
        return hash('sha256', preg_replace_callback(self::CUSTOM,
            static fn ($match) => $match['start'] . $match['end'], str_replace("\r\n", "\n", $contents)));
    }

    public function preserve(string $generated, string $existing): string
    {
        if (! preg_match(self::CUSTOM, $existing, $match)) {
            return $generated;
        }
        if (! preg_match(self::CUSTOM, $generated)) {
            throw new RuntimeException('The new template removes a custom code section. Preserve it manually before regenerating.');
        }
        return preg_replace_callback(self::CUSTOM,
            static fn ($new) => $new['start'] . $match['body'] . $new['end'], $generated);
    }

    public function load(string $path): array
    {
        if (! is_file($path)) {
            return ['version' => '2.0.0', 'files' => []];
        }
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data['files'] ?? null)) {
            throw new RuntimeException('Invalid generation manifest: ' . $path);
        }
        return $data;
    }

    public function key(string $path, string $directory): string
    {
        $path = str_replace('\\', '/', $path);
        $directory = rtrim(str_replace('\\', '/', $directory), '/') . '/';
        return str_starts_with($path, $directory) ? substr($path, strlen($directory)) : $path;
    }
}
