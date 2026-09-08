# hyperf-crud-generator

Extensible CRUD generator for Hyperf 3.1/3.2.

## Install locally while developing

In the Hyperf application's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../hyperf-crud-generator",
      "options": { "symlink": true }
    }
  ]
}
```

Then:

```bash
composer require gustavoqueiroz/hyperf-crud-generator:@dev
php bin/hyperf.php vendor:publish gustavoqueiroz/hyperf-crud-generator
```

## Usage

Generate the default CRUD:

```bash
php bin/hyperf.php crud:generate User
```

Custom table:

```bash
php bin/hyperf.php crud:generate User --table=erp_users
```

Generate every supported artifact:

```bash
php bin/hyperf.php crud:generate User --all
```

Choose components:

```bash
php bin/hyperf.php crud:generate User \
  --components=model,store_request,update_request,repository,service,controller,routes,openapi,test
```

Overwrite generated files:

```bash
php bin/hyperf.php crud:generate User --force
```

## Default output

```text
app/
├── Controller/UserController.php
├── Model/User.php
├── Repository/UserRepository.php
├── Request/User/StoreUserRequest.php
├── Request/User/UpdateUserRequest.php
└── Service/UserService.php

config/routes.php          # marked route block inserted/updated
```

With `--all` it also creates:

```text
docs/openapi/user.yaml
test/Cases/UserControllerTest.php
```

## Architecture

- `GenerateCrudCommand`: CLI boundary only.
- `CrudGenerator`: orchestration engine.
- `GeneratorContext`: normalized names and destination paths.
- `StubRenderer`: renders templates.
- `FileWriter`: safe creation and marker-based route updates.
- `stubs/`: generated application code templates.

## Next engine milestones

1. SchemaInspector for MySQL/PostgreSQL.
2. Column metadata and DB type -> PHP type mapping.
3. Automatic `$fillable` and `$casts`.
4. Validation rules from nullable/type/length/unique/foreign keys.
5. Relation detection from foreign keys.
6. Pagination/filter/sort/search generation.
7. Repository interface + implementation option.
8. Policy/authorization adapter.
9. Factory/Seeder adapter.
10. OpenAPI schemas from actual columns.
11. Integration tests generated from schema.
12. `crud:generate-table` and `crud:generate-database` batch modes.
13. Dry-run/diff mode before writing files.
14. Idempotent manifest so regeneration can preserve custom code.

## Important

The current MVP is intentionally not schema-aware. Request rules and model fillable/casts are placeholders until the schema inspection layer is implemented.
