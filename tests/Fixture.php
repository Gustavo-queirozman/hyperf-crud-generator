<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGeneratorTest;

use GustavoQueiroz\HyperfCrudGenerator\Generator\GeneratorContext;
use GustavoQueiroz\HyperfCrudGenerator\Schema\Column;
use GustavoQueiroz\HyperfCrudGenerator\Schema\Table;

final class Fixture
{
    public static function table(): Table
    {
        return new Table('users', 'public', [
            new Column('id', 'bigint', identity: true),
            new Column('email', 'varchar', length: 120),
            new Column('name', 'varchar', length: 100),
            new Column('password', 'varchar', length: 255),
            new Column('team_id', 'bigint', nullable: true),
            new Column('balance', 'numeric', default: '0.00', precision: 12, scale: 2),
            new Column('active', 'boolean', default: 'true'),
            new Column('settings', 'jsonb', nullable: true),
            new Column('status', 'varchar', default: 'pending', enum: ['pending', 'active']),
            new Column('created_at', 'timestamp'),
            new Column('updated_at', 'timestamp', nullable: true),
            new Column('computed', 'int', generated: true),
        ], ['id'], [['email'], ['name', 'team_id']], [
            ['schema' => 'public', 'table' => 'teams', 'columns' => ['team_id'], 'references' => ['id']],
        ]);
    }

    public static function context(string $root, ?Table $table = null, bool $force = false, bool $dryRun = false, string $model = 'User'): GeneratorContext
    {
        $table ??= self::table();
        return new GeneratorContext($model, $table->schema . '.' . $table->name, 'GeneratedApp', "$root/app",
            "$root/config/routes.php", "$root/docs/openapi", "$root/test/Cases", $force,
            $table, 'reporting', $dryRun, ['public.teams' => 'Team'], 'GeneratedTest');
    }
}
