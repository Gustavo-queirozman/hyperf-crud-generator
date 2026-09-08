<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

final class Name
{
    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', trim($value));
        $value = str_replace(' ', '', ucwords($value));

        return $value;
    }

    public static function snake(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', self::studly($value)) ?? $value;

        return strtolower($value);
    }

    public static function pluralSnake(string $value): string
    {
        $snake = self::snake($value);

        if (str_ends_with($snake, 'y') && ! preg_match('/[aeiou]y$/', $snake)) {
            return substr($snake, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/', $snake)) {
            return $snake . 'es';
        }

        if (str_ends_with($snake, 's')) {
            return $snake;
        }

        return $snake . 's';
    }

    public static function kebabPlural(string $value): string
    {
        return str_replace('_', '-', self::pluralSnake($value));
    }
}
