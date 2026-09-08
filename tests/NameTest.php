<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGeneratorTest;

use GustavoQueiroz\HyperfCrudGenerator\Support\Name;
use PHPUnit\Framework\TestCase;

final class NameTest extends TestCase
{
    public function testNames(): void
    {
        self::assertSame('UserProfile', Name::studly('user_profile'));
        self::assertSame('user_profile', Name::snake('UserProfile'));
        self::assertSame('users', Name::pluralSnake('User'));
        self::assertSame('categories', Name::pluralSnake('Category'));
    }
}
