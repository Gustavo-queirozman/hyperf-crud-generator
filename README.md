# Hyperf CRUD Generator

**English** | [Português](README.pt-BR.md) | [简体中文](README.zh-CN.md)

A **schema-aware CRUD API generator** for **Hyperf 3.1 and 3.2** applications, designed to turn existing database tables into an organized, validated, and documented API structure.

The package inspects the real database schema and can automatically generate Models, DTOs, Resources, Requests, Repositories, Services, Controllers, Policies, routes, Factories, Seeders, OpenAPI/Swagger documentation, and tests.

> Current release: **v1.2.0**

## Key Features

- Generate code from existing database tables.
- Generate a specific table or an entire database/schema.
- Support for **MySQL**, **MariaDB**, **PostgreSQL**, and **SQL Server**.
- Automatic Model name inference from table names.
- Reads columns, simple/composite primary keys, `UNIQUE` indexes, foreign keys, referential actions, and `CHECK` constraints.
- Generates `$fillable`, `$casts`, `$hidden`, timestamps, and `SoftDeletes` from the schema.
- Generates validation rules from types, nullability, length, enums, simple/composite `UNIQUE`, simple/composite foreign keys, and common `CHECK` expressions.
- Generates `BelongsTo`, `HasOne`, and `HasMany` relationships when they can be inferred.
- Repository Interface + implementation with automatic container binding.
- DTOs to restrict the fields accepted by the service layer.
- Resources to control the fields returned by the API.
- Pagination, sorting, equality filters, and text search.
- Policies with a configurable authorization adapter.
- Configurable authentication/authorization middleware on generated routes.
- Factory and Seeder generation.
- OpenAPI 3.0.3 JSON generation.
- Swagger UI at `/docs`.
- Controller and Service tests.
- Repository integration tests.
- `--dry-run` and `--diff` before writing files.
- Batch generation with allowlists and exclusions.
- Generation manifest for safe regeneration.
- Preservation of custom code inside `<crud-custom>` blocks.
- Preflight validation of every target file before writing, preventing partial generation when conflicts occur.

---

## Requirements

- PHP **8.2+**
- Hyperf **3.1 or 3.2**
- A database connection configured in `config/autoload/databases.php`

Main package dependencies:

```text
hyperf/command
doctrine/inflector
hyperf/contract
hyperf/db-connection
hyperf/http-server
hyperf/paginator
hyperf/validation
```

For PostgreSQL, the target application must have a PostgreSQL driver compatible with the installed Hyperf version.

For SQL Server, the application must have `hyperf/database-sqlserver` installed in a version compatible with the current Hyperf installation.

> The generator does not support database connections configured with a table `prefix`. For schema introspection, use a connection without a table prefix.

---

## Installation

### Composer

If the package is available in the Composer repository used by the project:

```bash
composer require gustavoqueiroz/hyperf-crud-generator:^1.2
```

Publish the configuration file:

```bash
php bin/hyperf.php vendor:publish gustavoqueiroz/hyperf-crud-generator
```

The file will be created at:

```text
config/autoload/crud_generator.php
```

### Local development with a `path` repository

In the Hyperf application's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../hyperf-crud-generator",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Then run:

```bash
composer require gustavoqueiroz/hyperf-crud-generator:@dev
php bin/hyperf.php vendor:publish gustavoqueiroz/hyperf-crud-generator
```

---

# Supported Databases

| Database | Recognized Driver | Default Schema |
| --- | --- | --- |
| MySQL | `mysql` | configured database name |
| MariaDB | `mariadb` | configured database name |
| PostgreSQL | `pgsql`, `postgres`, `postgresql` | `public` |
| SQL Server | `sqlsrv`, `sqlserver`, `mssql` | `dbo` |

Batch generation only considers **base tables**. Views are not included automatically.

---

# Quick Start

## Generate CRUD from a Model name

```bash
php bin/hyperf.php crud:generate User
```

In this case, the table name is inferred from the Model name:

```text
User -> users
OrderItem -> order_items
```

By default, the current configuration generates **all supported components**.

---

## Specify a table explicitly

```bash
php bin/hyperf.php crud:generate User --table=erp_users
```

The Model will be `User`, while the metadata will be read from `erp_users`.

You can also specify the schema and table together:

```bash
php bin/hyperf.php crud:generate User --table=public.users
```

---

## Infer the Model directly from the table

Use the `crud:generate-table` alias:

```bash
php bin/hyperf.php crud:generate-table users
```

Inference examples:

```text
users       -> User
order_items -> OrderItem
products    -> Product
```

It can also be used with the generator's other options:

```bash
php bin/hyperf.php crud:generate-table users \
  --connection=default \
  --schema=public \
  --dry-run
```

---

# Generate an Entire Database / Schema

There are two equivalent ways.

```bash
php bin/hyperf.php crud:generate --database
```

or:

```bash
php bin/hyperf.php crud:generate-database
```

The generator lists the tables in the selected schema, inspects their metadata, and creates the components for each valid table.

By default, the `migrations` table is excluded from batch generation.

---

## Generate only selected tables

```bash
php bin/hyperf.php crud:generate-database \
  --tables=users,teams,products
```

If any table specified in `--tables` does not exist, the command fails before generation begins.

---

## Exclude tables

```bash
php bin/hyperf.php crud:generate-database \
  --exclude=migrations,audit_logs,temp_data
```

CLI exclusions are combined with `crud_generator.exclude_tables`.

---

## Skip tables without a primary key

During batch generation:

```bash
php bin/hyperf.php crud:generate-database --skip-unsupported
```

This option reports and skips tables that do not have a primary key.

Composite primary keys are supported. Routes receive one parameter per primary-key column, in schema order, such as:

```text
/{key1}/{key2}
```

Without this option, the first unsupported table stops generation.

---

# Connection and Schema Selection

## Use another connection

```bash
php bin/hyperf.php crud:generate-table users --connection=reporting
```

Short form:

```bash
php bin/hyperf.php crud:generate-table users -c reporting
```

The connection must exist in:

```text
config/autoload/databases.php
```

## Use another schema

PostgreSQL:

```bash
php bin/hyperf.php crud:generate-table users \
  --connection=default \
  --schema=backoffice
```

SQL Server:

```bash
php bin/hyperf.php crud:generate-table users \
  --connection=sqlserver \
  --schema=erp
```

MySQL and MariaDB use the database name as the catalog schema.

---

# Available Components

The generator currently recognizes the following components:

```text
model
dto
resource
store_request
update_request
repository_interface
repository
binding
service
controller
factory
seeder
policy
routes
openapi
swagger
test
integration_test
```

To generate only selected components:

```bash
php bin/hyperf.php crud:generate User \
  --components=model,repository,service,controller,routes
```

Dependencies are included automatically.

For example:

```bash
php bin/hyperf.php crud:generate User --components=controller
```

This does not generate only the Controller. The engine also includes the components required for it to work, such as the Model, DTO, Resource, Requests, Repository, binding, Service, and Policy.

## Generate all components

```bash
php bin/hyperf.php crud:generate User --all
```

In the current distributed configuration, all components are already enabled by default. `--all` remains useful when `crud_generator.components` has been customized by the application.

---

# Generated Structure

A full generation for `User` may produce:

```text
app/
├── Contract/
│   └── UserRepositoryInterface.php
├── Controller/
│   └── UserController.php
├── DTO/
│   └── UserData.php
├── Factory/
│   └── UserFactory.php
├── Model/
│   └── User.php
├── Policy/
│   └── UserPolicy.php
├── Repository/
│   └── UserRepository.php
├── Request/
│   └── User/
│       ├── StoreUserRequest.php
│       └── UpdateUserRequest.php
├── Resource/
│   └── UserResource.php
├── Seeder/
│   └── UserSeeder.php
└── Service/
    └── UserService.php

config/
├── crud-generator/
│   ├── User.php
│   └── manifest.json
└── routes.php

docs/
└── openapi/
    └── user.json

test/
└── Cases/
    ├── UserControllerTest.php
    ├── UserServiceTest.php
    └── UserRepositoryIntegrationTest.php
```

The file at `config/crud-generator/User.php` automatically registers:

```php
UserRepositoryInterface::class => UserRepository::class
```

`ConfigProvider` loads the files in that directory as container bindings.

---

# Generated Routes

For a `users` table, the HTTP resource will be:

```text
/users
```

For a table such as `order_items`, the resource will be:

```text
/order-items
```

Generated routes:

| Method | Endpoint | Action |
| --- | --- | --- |
| `GET` | `/users` | paginated listing |
| `POST` | `/users` | create |
| `GET` | `/users/{id}` | retrieve by ID |
| `PUT` | `/users/{id}` | update |
| `PATCH` | `/users/{id}` | partial update |
| `DELETE` | `/users/{id}` | delete |

A composite primary key generates one route segment per column, for example:

```text
/memberships/{key1}/{key2}
```

Each OpenAPI parameter identifies its corresponding database column through `x-database-column`.

Generated routes receive the following middleware:

```php
GustavoQueiroz\HyperfCrudGenerator\Http\ValidationMiddleware::class
Hyperf\Validation\Middleware\ValidationMiddleware::class
```

The first middleware normalizes validation failures into the API's JSON response contract.

Classes configured in `crud_generator.route_middlewares` are added after these two middleware.

---

# Listing, Pagination, Sorting, Filtering, and Search

The generated Repository supports:

```text
page
per_page
sort
direction
filter
search
```

Example:

```http
GET /users?page=2&per_page=20&sort=name&direction=asc&search=gustavo
```

Filters are sent as an object / `deepObject` query:

```http
GET /users?filter[active]=1&filter[team_id]=10
```

### Current rules

- Minimum `page`: `1`
- Minimum `per_page`: `1`
- Maximum `per_page`: `100`
- `direction`: `asc` or `desc`
- Only columns allowed by the schema can be used in `sort` and `filter`
- `json` and binary fields are not used for sorting
- Fields configured as hidden are not used for sorting or filtering
- `search` uses `LIKE` on visible textual columns
- When sorting is not performed by the primary key, the primary key is added as a second criterion to stabilize pagination

List response:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 0,
    "last_page": 1
  }
}
```

---

# Schema Introspection

`SchemaInspector` reads the real database before generation.

The following information is currently collected:

- table names;
- column names and order;
- database type;
- nullability;
- catalog-provided default value;
- maximum length;
- numeric precision and scale;
- identity / auto increment;
- generated / computed columns;
- primary key;
- `UNIQUE` indexes;
- foreign keys;
- `ON UPDATE` and `ON DELETE` actions for foreign keys;
- `CHECK` constraints;
- native enums when available.

---

# Generated Model

The Model is configured automatically from the schema:

```php
protected ?string $connection;
protected ?string $table;
protected string $primaryKey;
protected string $keyType;
protected array $fillable;
protected array $casts;
protected array $hidden;
```

The following are also inferred:

- `$timestamps`;
- `CREATED_AT`;
- `UPDATED_AT`;
- `$incrementing`;
- `SoftDeletes` when a nullable `deleted_at` column exists;
- SQL Server identity handling;
- inferable relationships.

Generated columns, identity columns, and conventional timestamp columns are excluded from `$fillable`.

The primary key is also removed from fields allowed during updates.

---

# Type Mapping

The package normalizes database types into categories used by the Model, validation, and OpenAPI layers.

Examples:

| Database Type | Normalized Category |
| --- | --- |
| `bool`, `boolean`, `bit` | boolean |
| `smallint`, `int`, `integer`, `serial` | integer |
| `bigint`, `bigserial` | bigint |
| `decimal`, `numeric`, `money` | decimal |
| `float`, `real`, `double` | number |
| `json`, `jsonb` | json |
| `uuid`, `uniqueidentifier` | uuid |
| `date` | date |
| timestamps / datetimes | datetime |
| `time` | time |
| blob / binary / bytea / rowversion | binary |
| types not specifically recognized | string |

Some generated casts:

```text
integer  -> integer
bigint   -> string
boolean  -> boolean
decimal  -> decimal:<scale>
number   -> float
json     -> json
date     -> date
datetime -> datetime
```

`bigint` is represented as a string in the representation/OpenAPI layer to reduce the risk of precision loss in JSON clients.

---

# Automatic Validation

`StoreRequest` and `UpdateRequest` are derived from the real database columns.

The generator can produce rules for:

- `required`;
- `sometimes`;
- `nullable`;
- `integer`;
- `numeric`;
- `boolean`;
- `string`;
- `array` for JSON;
- `uuid`;
- `date`;
- `date_format`;
- `max:<length>`;
- `min:0` for unsigned values;
- decimal precision/scale through regex;
- enums with `Rule::in(...)`;
- simple and composite `UNIQUE` with `Rule::unique(...)`;
- simple and composite foreign keys with `Rule::exists(...)`;
- `min`, `max`, `between`, and `Rule::in(...)` for common `CHECK` formats.

On Update, the `unique` rule excludes the row identified by the current primary key, including composite primary keys.

For composite constraints, `required_with` prevents only part of the key from being validated.

Fields that cannot be written — such as identity columns, generated columns, and the primary key during Update — receive:

```php
['prohibited']
```

This prevents extra values from being silently accepted by the FormRequest.

---

# DTO and Attribute Protection

The generated DTO applies an allowlist before sending data to the Service / Repository:

```php
UserData::fromArray($request->validated())
```

Even if an array contains extra attributes, only fields allowed by the schema are retained.

During updates, the DTO does not allow the primary key to be changed.

---

# Sensitive Fields

The default configuration contains:

```php
'hidden' => [
    'password',
    'password_hash',
    'remember_token',
    'api_token',
    'secret',
],
```

These fields:

- can still be written when they are writable columns;
- are omitted from the `Resource` / response;
- are not available in filters;
- are not available in sorting;
- are not used in search;
- appear as `writeOnly` in OpenAPI write schemas.

Binary fields are also excluded from the default response.

---

# Automatic Relationships

The generator can create ORM relationships for **single-column foreign keys**.

## `BelongsTo`

When the current table has a known foreign key and the target table exists in `model_map`, a `BelongsTo` relationship can be generated.

Conceptual example:

```text
users.team_id -> teams.id
```

## `HasOne` and `HasMany`

During batch generation, the engine analyzes the other selected tables and may generate the inverse relationship.

When the foreign key on the related table is also `UNIQUE` / PK, the inverse relationship is treated as `HasOne`; otherwise, it is treated as `HasMany`.

Method names are generated conservatively to avoid collisions with existing attributes.

---

# `model_map`

Use `model_map` to explicitly define the Model associated with a qualified table:

```php
'model_map' => [
    'public.users' => 'User',
    'public.teams' => 'Team',
    'erp.customers' => 'Customer',
],
```

This also allows relationships to be created with existing Models that are not being generated by the current command.

During batch generation, Models not configured in the map are inferred from the table name.

If two tables resolve to the same Model or HTTP resource, the engine stops before writing any files.

---

# Authorization and Policies

Each generated Controller depends on a Policy specific to the resource.

Abilities used:

```text
viewAny
view
create
update
delete
```

The default implementation is:

```text
GustavoQueiroz\HyperfCrudGenerator\Authorization\ConfigAuthorization
```

It reads:

```php
'authorization' => [
    'default' => 'allow',
    'rules' => [],
],
```

## Important Security Note

The currently distributed configuration uses:

```php
'default' => 'allow'
```

Therefore, **access is allowed when no specific rule exists**.

For private APIs, it is recommended to change it to:

```php
'authorization' => [
    'default' => 'deny',
    'rules' => [],
],
```

Rules can be callables indexed by Model and ability:

```php
use Hyperf\HttpServer\Contract\RequestInterface;

'authorization' => [
    'default' => 'deny',
    'rules' => [
        'User' => [
            'viewAny' => static function (RequestInterface $request, mixed $subject): bool {
                return true;
            },
            'view' => static function (RequestInterface $request, mixed $subject): bool {
                return true;
            },
        ],
    ],
],
```

You can also replace the binding for:

```text
GustavoQueiroz\HyperfCrudGenerator\Authorization\AuthorizationInterface
```

with a custom adapter for RBAC, ACL, or another application-specific authorization mechanism.

The package does not choose a login/JWT/OAuth mechanism because that depends on the application.

Configure the authentication middleware already installed in your project through `route_middlewares`; they will be added to all generated CRUD route groups.

---

# HTTP Responses

The generated Controller standardizes some responses and errors:

| Status | Situation |
| --- | --- |
| `201` | resource created |
| `204` | resource deleted |
| `403` | authorization denied |
| `404` | Model not found |
| `409` | database constraint conflict (`SQLSTATE` class `23`) |
| `422` | validation failure |

Validation format:

```json
{
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email field is required."
    ]
  }
}
```

---

# OpenAPI and Swagger UI

The `openapi` component generates an **OpenAPI 3.0.3** document for each resource:

```text
docs/openapi/user.json
```

The document includes:

- CRUD routes;
- pagination parameters;
- sorting;
- search;
- filters;
- `Response`, `Store`, and `Update` schemas;
- types derived from database columns;
- `required`;
- nullable;
- enums;
- maximum length;
- read-only fields;
- write-only fields;
- PK, UNIQUE, foreign-key, referential-action, and `CHECK` metadata under `x-database`;
- `403`, `404`, `409`, and `422` responses when applicable.

The `swagger` component adds the following routes:

```text
GET /docs
GET /docs/openapi/{name}.json
```

The `/docs` page uses Swagger UI and lists the JSON files available in `crud_generator.openapi_path`.

> The current Swagger UI HTML loads `swagger-ui-dist` assets through a CDN. Isolated environments can customize this layer.

---

# Dry Run

Validate the entire generation process without creating files:

```bash
php bin/hyperf.php crud:generate User --dry-run
```

The planning stage checks:

- components;
- Model / resource collisions;
- destination conflicts;
- PHP syntax of planned files;
- destination-directory permissions;
- route-block integrity;
- existing files that would be overwritten.

No directory or file is created during `dry-run`.

---

# Diff

Display a unified diff without modifying files:

```bash
php bin/hyperf.php crud:generate User --diff
```

It also works in batch mode:

```bash
php bin/hyperf.php crud:generate-database --diff
```

Because `--diff` works in preview mode, planned files are not written.

---

# Safe Regeneration

The package maintains a manifest at:

```text
config/crud-generator/manifest.json
```

To regenerate code after a schema change:

```bash
php bin/hyperf.php crud:generate User --regenerate
```

The engine compares hashes of the previously generated content.

Files that still match their previously generated version can be updated automatically.

## Custom blocks

Some stubs contain:

```text
// <crud-custom>
// </crud-custom>
```

Content placed between these markers is preserved during regeneration.

These blocks currently exist in:

- Model;
- Repository;
- Service;
- Controller;
- Policy.

Example:

```php
// <crud-custom>
public function customMethod(): string
{
    return 'preserved';
}
// </crud-custom>
```

Manual changes **outside** protected areas cause regeneration to refuse replacement unless `--force` is used.

---

# Force

To overwrite existing files and generated route blocks:

```bash
php bin/hyperf.php crud:generate User --force
```

Short form:

```bash
php bin/hyperf.php crud:generate User -f
```

Use with care: `--force` allows existing content to be replaced.

The engine performs a preflight check on all targets before writing, preventing half of a CRUD from being generated before a conflict appears on the final file.

---

# Routes and Markers

Routes are inserted inside identified blocks:

```php
// <hyperf-crud-generator:User>
// ...
// </hyperf-crud-generator:User>
```

Swagger routes use their own block:

```php
// <hyperf-crud-generator:_swagger>
// ...
// </hyperf-crud-generator:_swagger>
```

The engine detects:

- duplicate markers;
- incomplete markers;
- reversed markers;
- manual changes to generated blocks.

The routes file should not end with `?>` when new blocks need to be appended.

---

# Configuration

Current distributed configuration:

```php
<?php

declare(strict_types=1);

return [
    'namespace' => 'App',
    'base_path' => BASE_PATH . '/app',
    'routes_file' => BASE_PATH . '/config/routes.php',
    'openapi_path' => BASE_PATH . '/docs/openapi',
    'test_path' => BASE_PATH . '/test/Cases',
    'test_namespace' => 'HyperfTest\\Cases',
    'binding_path' => BASE_PATH . '/config/crud-generator',

    'connection' => 'default',
    'schema' => null,

    'exclude_tables' => [
        'migrations',
    ],

    'model_map' => [],

    'stub_path' => null,

    'authorization' => [
        'default' => 'allow',
        'rules' => [],
    ],

    'route_middlewares' => [
        // \App\Middleware\JwtAuthMiddleware::class,
    ],

    'hidden' => [
        'password',
        'password_hash',
        'remember_token',
        'api_token',
        'secret',
    ],

    'force' => false,

    'components' => \GustavoQueiroz\HyperfCrudGenerator\Generator\CrudGenerator::COMPONENTS,
];
```

## Options

| Key | Purpose |
| --- | --- |
| `namespace` | base namespace for generated artifacts |
| `base_path` | main code directory |
| `routes_file` | file that receives generated route blocks |
| `openapi_path` | directory containing OpenAPI JSON files |
| `test_path` | directory for generated tests |
| `test_namespace` | test namespace |
| `binding_path` | generator bindings and manifest directory |
| `connection` | default database connection |
| `schema` | default schema/database used for introspection |
| `exclude_tables` | automatic exclusions during batch generation |
| `model_map` | `schema.table => Model` mapping |
| `stub_path` | optional directory for custom stubs |
| `authorization` | rules used by the default authorization adapter |
| `route_middlewares` | application middleware added to CRUD route groups |
| `hidden` | writable fields that should not appear in responses/filters |
| `force` | enables global overwrite through configuration |
| `components` | components generated when the CLI does not specify `--components` |

---

# Custom Stubs

You can replace individual stubs by configuring:

```php
'stub_path' => BASE_PATH . '/stubs/crud',
```

The generator first looks in this directory for:

```text
model.stub
dto.stub
resource.stub
store_request.stub
update_request.stub
repository_interface.stub
repository.stub
binding.stub
service.stub
controller.stub
factory.stub
seeder.stub
policy.stub
routes.stub
swagger.stub
test.stub
service_test.stub
repository_test.stub
```

If a custom stub does not exist, the package automatically uses its built-in counterpart.

---

# Factory and Seeder

The Factory generates example values from detected database types.

Example:

```php
$user = UserFactory::create([
    'email' => 'user@example.com',
]);
```

Required foreign-key values must be supplied through overrides when they cannot be derived safely:

```php
$user = UserFactory::create([
    'team_id' => $team->id,
]);
```

The Seeder receives a list of rows and performs creation inside a transaction:

```php
$seeder = new UserSeeder();

$seeder->run([
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com'],
]);
```

---

# Generated Tests

The `test` component generates:

```text
UserControllerTest.php
UserServiceTest.php
```

The tests cover scenarios such as:

- authorization;
- `404`;
- `201` creation;
- updates;
- `204` deletion;
- protection against unexpected fields;
- correct propagation of pagination/filters;
- primary-key immutability in the update DTO.

The `integration_test` component generates:

```text
UserRepositoryIntegrationTest.php
```

This test is disabled by default and requires:

```text
CRUD_INTEGRATION_TESTS=1
```

It also requires a real Hyperf application bootstrap and a disposable database containing the target schema.

When a table contains required foreign keys, overrides can be supplied through a Model-specific environment variable:

```text
CRUD_TEST_User_FIXTURE
```

Its value should be JSON containing valid existing foreign-key values.

---

# Package Tests

## Unit tests

```bash
composer test
```

Equivalent to:

```bash
vendor/bin/phpunit
```

Run only the unit suite:

```bash
vendor/bin/phpunit --testsuite unit
```

## Catalog tests with real databases

The repository includes `compose.test.yaml` with:

- MySQL 8.4;
- MariaDB 11.4;
- PostgreSQL 16;
- SQL Server 2022.

One way to run the suite in containers is:

```bash
docker compose -f compose.test.yaml up \
  --build \
  --abort-on-container-exit \
  --exit-code-from runner \
  runner
```

The integration suite can also be run directly when the `CRUD_TEST_*` variables are configured:

```bash
composer test:integration
```

---

# CLI Options

Main generation options:

| Option | Description |
| --- | --- |
| `--table=<table>` | table used for generation |
| `--database` | generate all tables in the schema |
| `--tables=a,b,c` | allowlist for database generation |
| `--exclude=a,b,c` | exclude tables |
| `--connection=<name>` / `-c` | database connection |
| `--schema=<schema>` | schema or database used for introspection |
| `--components=a,b,c` | select components |
| `--all` | select all components |
| `--dry-run` | validate without writing |
| `--diff` | print a diff without writing |
| `--regenerate` | regenerate files controlled by the manifest |
| `--skip-unsupported` | skip tables without primary keys in database mode |
| `--force` / `-f` | force replacement |

`--tables` and `--skip-unsupported` can only be used in database generation mode.

`--database` cannot be combined with a Model or `--table`.

---

# Internal Architecture

The main package components are:

```text
Command
 └── GenerateCrudCommand
     ├── GenerateTableCommand
     └── GenerateDatabaseCommand

Schema
 ├── SchemaInspector
 ├── MySqlCatalog
 ├── PostgresCatalog
 ├── SqlServerCatalog
 ├── Table
 └── Column

Generator
 ├── CrudGenerator
 ├── GeneratorContext
 ├── SchemaVariables
 └── OpenApiGenerator

Support
 ├── FileWriter
 ├── Manifest
 ├── StubRenderer
 ├── Diff
 ├── Name
 ├── Uuid
 └── SqlServerIdentity

Authorization
 ├── AuthorizationInterface
 └── ConfigAuthorization

Http
 ├── ValidationMiddleware
 └── DocumentationController
```

### Simplified flow

```text
CLI
 ↓
Connection config
 ↓
SchemaInspector
 ↓
Table + Column metadata
 ↓
GeneratorContext
 ↓
SchemaVariables
 ↓
CrudGenerator
 ↓
Stubs / OpenApiGenerator
 ↓
Preflight
 ↓
Manifest
 ↓
Generated files
```

---

# Current Limitations

The package is schema-aware, but not every possible relational database structure can be automatically converted into a Hyperf CRUD resource.

## Primary Keys

A primary key is required. Both simple and composite primary keys are supported:

```sql
PRIMARY KEY (column_a, column_b)
```

For composite primary keys, the Controller and OpenAPI use:

```text
/{key1}/{key2}
```

The Service / Repository use an associative array such as:

```php
['column_a' => $key1, 'column_b' => $key2]
```

The generated Repository queries, updates, and deletes the row using all primary-key columns, without depending on `Model::find()` for composite keys.

Tables without primary keys still do not provide a safe identifier for CRUD operations.

In batch mode, use:

```text
--skip-unsupported
```

to skip them.

## Composite Constraints

Composite `UNIQUE` indexes and composite foreign keys generate scoped rules using the other columns in the constraint. Their individual parts also receive `required_with`.

ORM relationships such as `BelongsTo`, `HasOne`, and `HasMany` are still generated only for single-column foreign keys because native Hyperf/Eloquent relationships do not support composite keys.

The database remains the final authority for concurrency between validation and persistence; constraint violations are still converted to HTTP `409`.

## Views

Batch generation lists base tables only. Views and materialized views are not automatically treated as CRUD resources.

## Functional / Partial Indexes

In PostgreSQL, functional and partial indexes are not converted into simple validation rules.

In SQL Server, filtered indexes are ignored by the `UNIQUE` introspection used by the generator.

## Database-Specific Types

Known types are normalized.

Specialized types that are not explicitly mapped fall back to the `string` category.

Review generated output for geospatial types, ranges, arrays, and user-defined types.

## `CHECK` Constraints

The catalog preserves every expression under `x-database.checks`.

The generator automatically converts safe scalar patterns involving:

- `BETWEEN`
- `>=`
- `<=`
- `IN`
- `CHAR_LENGTH`
- `CHARACTER_LENGTH`
- `LENGTH`
- `LEN`

Expressions using database-specific functions, column-to-column comparisons, SQL regex, complex subexpressions, or session-dependent logic remain the database's responsibility.

## Foreign-Key Actions

Rules such as the following are read and published under `x-database.foreignKeys`:

```text
ON DELETE CASCADE
ON DELETE SET NULL
ON UPDATE CASCADE
```

The generator does not duplicate cascades in the Service / Repository.

The database executes referential actions atomically.

## Authentication

The package provides Policy / authorization support and adds middleware configured in `route_middlewares` to generated routes.

Because the schema does not indicate which identity library the application uses, login, JWT, and OAuth middleware installation and configuration remain the responsibility of the consuming project.

---

# Recommendations for Large Databases

When using the generator with ERPs or databases containing hundreds of tables, start with a preview:

```bash
php bin/hyperf.php crud:generate-database \
  --dry-run \
  --skip-unsupported
```

Then reduce the scope with:

```text
--tables=...
```

or:

```text
--exclude=...
```

You may also want to customize `crud_generator.components` so internal tables that should not be exposed as APIs do not receive Controllers or routes.

---

# Recommended Workflow

### 1. Verify the connection

```bash
php bin/hyperf.php crud:generate-table users --dry-run
```

### 2. Review the diff

```bash
php bin/hyperf.php crud:generate-table users --diff
```

### 3. Generate

```bash
php bin/hyperf.php crud:generate-table users
```

### 4. Review authorization

For private APIs:

```php
'authorization' => [
    'default' => 'deny',
    'rules' => [
        // ...
    ],
],
```

### 5. Configure route authentication for your application

```php
'route_middlewares' => [
    \App\Middleware\JwtAuthMiddleware::class,
],
```

### 6. Run the tests

```bash
composer test
```

### 7. After changing the schema

```bash
php bin/hyperf.php crud:generate-table users --diff --regenerate
```

If the preview looks correct:

```bash
php bin/hyperf.php crud:generate-table users --regenerate
```

---

# License

MIT.
