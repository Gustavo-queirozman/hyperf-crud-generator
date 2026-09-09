<?php

declare(strict_types=1);

namespace GustavoQueiroz\HyperfCrudGeneratorTest\Unit;

use GustavoQueiroz\HyperfCrudGenerator\Generator\CrudGenerator;
use GustavoQueiroz\HyperfCrudGenerator\Schema\Column;
use GustavoQueiroz\HyperfCrudGenerator\Schema\Table;
use GustavoQueiroz\HyperfCrudGenerator\Support\FileWriter;
use GustavoQueiroz\HyperfCrudGenerator\Support\StubRenderer;
use GustavoQueiroz\HyperfCrudGeneratorTest\Fixture;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase
{
    private string $root;
    private CrudGenerator $generator;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/hyperf-crud-test-' . bin2hex(random_bytes(8));
        $this->generator = new CrudGenerator(new StubRenderer(), new FileWriter());
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testAllArtifactsAreValidAndIdempotent(): void
    {
        $context = Fixture::context($this->root);
        $paths = $this->generator->generate($context, CrudGenerator::COMPONENTS);
        self::assertCount(19, $paths);
        foreach ($paths as $path) {
            self::assertFileExists($path);
            $contents = file_get_contents($path);
            self::assertStringNotContainsString('{{ ', $contents);
            if (str_ends_with($path, '.php')) {
                token_get_all($contents, TOKEN_PARSE);
            }
        }
        $before = file_get_contents($context->routesFile);
        $this->generator->generate($context, CrudGenerator::COMPONENTS);
        self::assertSame($before, file_get_contents($context->routesFile));
        self::assertStringContainsString("'/users'", $before);
        self::assertStringContainsString("['PUT', 'PATCH']", $before);
        $model = file_get_contents($this->root . '/app/Model/User.php');
        self::assertStringContainsString("'decimal:2'", $model);
        self::assertStringContainsString('relatedTeamId', $model);
        $store = file_get_contents($this->root . '/app/Request/User/StoreUserRequest.php');
        self::assertStringContainsString("'email' => ['required', 'string', 'max:120'", $store);
        self::assertStringContainsString("'id' => ['prohibited']", $store);
        self::assertStringContainsString("Rule::exists('reporting.public.teams', 'id')", $store);
        $update = file_get_contents($this->root . '/app/Request/User/UpdateUserRequest.php');
        self::assertStringContainsString("->ignore(\$this->route('id'), 'id')", $update);
        self::assertStringNotContainsString("Rule::unique('reporting.public.users', 'name')", $update);
        $spec = json_decode(file_get_contents($this->root . '/docs/openapi/user.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('password', $spec['components']['schemas']['UserResponse']['properties']);
        self::assertSame(['email', 'name', 'password'], $spec['components']['schemas']['UserStore']['required']);
        self::assertSame('string', $spec['paths']['/users/{id}']['parameters'][0]['schema']['type']);
        self::assertArrayNotHasKey('content', $spec['paths']['/users/{id}']['delete']['responses']['204']);
        $ids = [];
        foreach ($spec['paths'] as $path) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
                if (isset($path[$method])) {
                    $ids[] = $path[$method]['operationId'];
                }
            }
        }
        self::assertCount(count($ids), array_unique($ids));
    }

    public function testDryRunDoesNotCreateDirectories(): void
    {
        self::assertNotEmpty($this->generator->generate(Fixture::context($this->root, dryRun: true), CrudGenerator::COMPONENTS));
        self::assertDirectoryDoesNotExist($this->root);
    }

    public function testConflictDoesNotWriteEarlierFiles(): void
    {
        mkdir($this->root . '/app/Service', 0775, true);
        file_put_contents($this->root . '/app/Service/UserService.php', '<?php // custom');
        try {
            $this->generator->generate(Fixture::context($this->root), CrudGenerator::COMPONENTS);
            self::fail('Expected conflict');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('--force', $e->getMessage());
            self::assertFileDoesNotExist($this->root . '/app/Model/User.php');
            self::assertSame('<?php // custom', file_get_contents($this->root . '/app/Service/UserService.php'));
        }
        $this->generator->generate(Fixture::context($this->root, force: true), CrudGenerator::COMPONENTS);
        self::assertFileExists($this->root . '/app/Model/User.php');
    }

    public function testBatchCollisionDoesNotWriteAnything(): void
    {
        $this->expectExceptionMessage('collision');
        try {
            $this->generator->generateBatch([Fixture::context($this->root), Fixture::context($this->root)], ['model']);
        } finally {
            self::assertDirectoryDoesNotExist($this->root);
        }
    }

    public function testUuidAndNoTimestamps(): void
    {
        $table = new Table('tokens', 'auth', [new Column('code', 'uuid'), new Column('label', 'varchar', nullable: true)], ['code']);
        $files = $this->generator->plan([Fixture::context($this->root, $table, model: 'Token')], ['model', 'store_request', 'openapi']);
        self::assertStringContainsString('public bool $timestamps = false;', $files[$this->root . '/app/Model/Token.php']);
        self::assertStringContainsString("'code' => ['required', 'uuid']", $files[$this->root . '/app/Request/Token/StoreTokenRequest.php']);
        $spec = json_decode($files[$this->root . '/docs/openapi/token.json'], true);
        self::assertSame('uuid', $spec['paths']['/tokens/{id}']['parameters'][0]['schema']['format']);
    }

    public function testDependenciesAreIncluded(): void
    {
        $components = $this->generator->resolveComponents(['controller']);
        foreach (['dto', 'model', 'resource', 'repository_interface', 'binding', 'repository', 'service', 'store_request', 'update_request'] as $component) {
            self::assertContains($component, $components);
        }
    }

    public function testRegenerationPreservesCustomSectionsAndAddsColumns(): void
    {
        $this->generator->generate(Fixture::context($this->root), ['model']);
        $path = $this->root . '/app/Model/User.php';
        $contents = file_get_contents($path);
        $contents = str_replace('    // </crud-custom>', "    public function customValue(): string { return 'kept'; }\n    // </crud-custom>", $contents);
        file_put_contents($path, $contents);
        $table = Fixture::table();
        $changed = new Table($table->name, $table->schema, [...$table->columns, new Column('nickname', 'varchar', nullable: true)], $table->primaryKey);
        $this->generator->generate(Fixture::context($this->root, $changed, regenerate: true), ['model']);
        $result = file_get_contents($path);
        self::assertStringContainsString('customValue()', $result);
        self::assertStringContainsString("'nickname'", $result);
        $manifest = json_decode(file_get_contents($this->root . '/config/crud-generator/manifest.json'), true);
        self::assertArrayHasKey('app/Model/User.php', $manifest['files']);
    }

    public function testRegenerationRejectsChangesOutsideCustomSections(): void
    {
        $this->generator->generate(Fixture::context($this->root), ['model']);
        $path = $this->root . '/app/Model/User.php';
        $contents = str_replace("protected ?string \$connection = 'reporting';", "protected ?string \$connection = 'custom';", file_get_contents($path));
        file_put_contents($path, $contents);
        $this->expectExceptionMessage('--force');
        try {
            $this->generator->generate(Fixture::context($this->root, regenerate: true), ['model']);
        } finally {
            self::assertSame($contents, file_get_contents($path));
        }
    }
}
