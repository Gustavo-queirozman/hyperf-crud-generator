<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGenerator\Support;

final class Diff
{
    /** A single unified hunk with three context lines; intended for generation previews. */
    public static function unified(string $path, string $before, string $after): string
    {
        if ($before === $after) {
            return '';
        }
        $a = $before === '' ? [] : explode("\n", rtrim(str_replace("\r\n", "\n", $before), "\n"));
        $b = $after === '' ? [] : explode("\n", rtrim(str_replace("\r\n", "\n", $after), "\n"));
        $prefix = 0;
        while (isset($a[$prefix], $b[$prefix]) && $a[$prefix] === $b[$prefix]) {
            ++$prefix;
        }
        $suffix = 0;
        while ($suffix < min(count($a), count($b)) - $prefix && $a[count($a) - 1 - $suffix] === $b[count($b) - 1 - $suffix]) {
            ++$suffix;
        }
        $start = max(0, $prefix - 3);
        $tail = min(3, $suffix);
        $lines = ["--- a/$path", "+++ b/$path",
            sprintf('@@ -%d,%d +%d,%d @@', $a === [] ? 0 : $start + 1, count($a) - $suffix + $tail - $start,
                $b === [] ? 0 : $start + 1, count($b) - $suffix + $tail - $start)];
        foreach (array_slice($a, $start, $prefix - $start) as $line) {
            $lines[] = ' ' . $line;
        }
        foreach (array_slice($a, $prefix, count($a) - $prefix - $suffix) as $line) {
            $lines[] = '-' . $line;
        }
        foreach (array_slice($b, $prefix, count($b) - $prefix - $suffix) as $line) {
            $lines[] = '+' . $line;
        }
        foreach (array_slice($b, count($b) - $suffix, $tail) as $line) {
            $lines[] = ' ' . $line;
        }
        return implode("\n", $lines) . "\n";
    }
}
