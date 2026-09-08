<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

use Doctrine\Inflector\InflectorFactory;
use InvalidArgumentException;

final class Name
{
    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', trim($value));
        $value = str_replace(' ', '', ucwords($value));

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value)
            || in_array(strtolower($value), ['class', 'trait', 'interface', 'enum', 'match', 'readonly', 'function', 'parent', 'self', 'static', 'string', 'int', 'float', 'bool', 'array', 'object', 'mixed', 'null', 'true', 'false', 'void', 'never', 'callable', 'iterable', 'resource', 'namespace', 'use', 'new', 'clone', 'abstract', 'final', 'public', 'private', 'protected', 'return', 'echo', 'print', 'const', 'extends', 'implements', 'if', 'else', 'elseif', 'while', 'do', 'for', 'foreach', 'switch', 'case', 'default', 'break', 'continue', 'try', 'catch', 'finally', 'throw', 'yield', 'global', 'unset', 'isset', 'empty', 'eval', 'include', 'require', 'list', 'exit', 'die', 'declare', 'goto', 'instanceof', 'insteadof', 'and', 'or', 'xor'], true)) {
            throw new InvalidArgumentException('Invalid PHP model name: ' . $value);
        }
        return $value;
    }

    public static function snake(string $value): string
    {
        $value = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', self::studly($value)) ?? $value;
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value) ?? $value;

        return strtolower($value);
    }

    public static function pluralSnake(string $value): string
    {
        return InflectorFactory::create()->build()->pluralize(self::snake($value));
    }

    public static function modelFromTable(string $table): string
    {
        return self::studly(InflectorFactory::create()->build()->singularize($table));
    }

    public static function kebabPlural(string $value): string
    {
        return str_replace('_', '-', self::pluralSnake($value));
    }
}
