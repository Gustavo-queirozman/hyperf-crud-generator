<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGeneratorTest\Unit;

use GustavoQueiroz\HyperfCrudGenerator\Support\FileWriter;
use GustavoQueiroz\HyperfCrudGenerator\Support\Name;
use PHPUnit\Framework\TestCase;

final class SupportTest extends TestCase
{
    public function testNamesAndInflections(): void
    {
        self::assertSame('UserProfile', Name::studly('user_profile'));
        self::assertSame('api_key', Name::snake('APIKey'));
        self::assertSame('categories', Name::pluralSnake('Category'));
        self::assertSame('people', Name::pluralSnake('Person'));
        self::assertSame('Person', Name::modelFromTable('people'));
        self::assertSame('users', Name::pluralSnake('users'));
    }

    public function testInvalidNameFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Name::studly('../../Unsafe');
    }

    public function testRouteReplacementPreservesCustomCodeAndDollarSigns(): void
    {
        $writer = new FileWriter();
        $custom = "<?php\n\$outside = 1;";
        $first = $writer->markedBlock($custom, 'User', '$value = 123;');
        self::assertStringStartsWith($custom, $first);
        $second = $writer->markedBlock($first, 'User', '$value = 456;', true);
        self::assertStringContainsString('$value = 456;', $second);
        self::assertStringNotContainsString('$value = 123;', $second);
        self::assertSame($second, $writer->markedBlock($second, 'User', '$value = 456;'));
    }

    public function testEditedRoutesAreProtected(): void
    {
        $writer = new FileWriter();
        $first = $writer->markedBlock('<?php', 'User', '$value = 123;');
        $this->expectExceptionMessage('--force');
        $writer->markedBlock($first, 'User', '$value = 456;');
    }

    public function testMalformedMarkersFail(): void
    {
        $this->expectExceptionMessage('Malformed');
        (new FileWriter())->markedBlock('<?php // <hyperf-crud-generator:User>', 'User', '');
    }
}
