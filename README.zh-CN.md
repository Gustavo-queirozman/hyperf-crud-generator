# Hyperf CRUD Generator

[English](README.md) | [Português](README.pt-BR.md) | **简体中文**

面向 **Hyperf 3.1 和 3.2** 应用的 **schema-aware（模式感知）CRUD API 生成器**，用于将现有数据库表转换为结构清晰、经过验证并带有文档的 API 架构。

该包会检查数据库的真实 Schema，并可自动生成 Model、DTO、Resource、Requests、Repository、Service、Controller、Policy、路由、Factory、Seeder、OpenAPI/Swagger 以及测试。

> 当前版本：**v1.2.0**

## 主要功能

- 基于现有数据库表生成代码。
- 支持生成单个表，或整个数据库 / Schema。
- 支持 **MySQL**、**MariaDB**、**PostgreSQL** 和 **SQL Server**。
- 根据表名自动推断 Model 名称。
- 读取字段、单主键 / 复合主键、`UNIQUE` 索引、外键、引用动作以及 `CHECK` 约束。
- 根据 Schema 自动生成 `$fillable`、`$casts`、`$hidden`、timestamps 和 `SoftDeletes`。
- 根据类型、可空性、长度、enum、单列 / 复合 `UNIQUE`、单列 / 复合 FK 以及常见 `CHECK` 表达式生成验证规则。
- 在可推断时生成 `BelongsTo`、`HasOne` 和 `HasMany` 关系。
- 生成 Repository Interface、实现类以及容器自动绑定。
- 生成 DTO，用于限制 Service 层接收的字段。
- 生成 Resource，用于控制 API 返回的字段。
- 支持分页、排序、等值过滤和文本搜索。
- 生成 Policy，并支持可配置的授权适配器。
- 生成的路由支持可配置的认证 / 授权 Middleware。
- 支持 Factory 和 Seeder。
- 生成 OpenAPI 3.0.3 JSON。
- 在 `/docs` 提供 Swagger UI。
- 生成 Controller 和 Service 测试。
- 生成 Repository 集成测试。
- 写入文件前支持 `--dry-run` 和 `--diff`。
- 支持 allowlist 和排除列表的批量生成。
- 使用生成清单（manifest）支持安全重新生成。
- 在 `<crud-custom>` 块中保留自定义代码。
- 写入前对全部文件进行 Preflight 检查，避免发生冲突时只生成部分文件。

---

## 要求

- PHP **8.2+**
- Hyperf **3.1 或 3.2**
- 在 `config/autoload/databases.php` 中配置数据库连接

主要依赖：

```text
hyperf/command
doctrine/inflector
hyperf/contract
hyperf/db-connection
hyperf/http-server
hyperf/paginator
hyperf/validation
```

对于 PostgreSQL，目标应用必须安装与 Hyperf 版本兼容的 PostgreSQL 驱动。

对于 SQL Server，应用必须安装与当前 Hyperf 环境兼容的 `hyperf/database-sqlserver` 驱动。

> 生成器不支持配置了表前缀 `prefix` 的连接。若需要通过 introspection 生成代码，请使用无表前缀的数据库连接。

---

## 安装

### Composer

如果项目所使用的 Composer 仓库中已经包含该包：

```bash
composer require gustavoqueiroz/hyperf-crud-generator:^1.2
```

发布配置文件：

```bash
php bin/hyperf.php vendor:publish gustavoqueiroz/hyperf-crud-generator
```

将创建：

```text
config/autoload/crud_generator.php
```

### 使用 `path` repository 进行本地开发

在 Hyperf 应用的 `composer.json` 中：

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

然后执行：

```bash
composer require gustavoqueiroz/hyperf-crud-generator:@dev
php bin/hyperf.php vendor:publish gustavoqueiroz/hyperf-crud-generator
```

---

# 支持的数据库

| 数据库 | 识别的 Driver | 默认 Schema |
| --- | --- | --- |
| MySQL | `mysql` | 配置的数据库名称 |
| MariaDB | `mariadb` | 配置的数据库名称 |
| PostgreSQL | `pgsql`, `postgres`, `postgresql` | `public` |
| SQL Server | `sqlsrv`, `sqlserver`, `mssql` | `dbo` |

批量生成只考虑**基础表（base tables）**。View 不会被自动包含。

---

# 快速使用

## 根据 Model 生成 CRUD

```bash
php bin/hyperf.php crud:generate User
```

此时表名会根据 Model 名称自动推断：

```text
User -> users
OrderItem -> order_items
```

默认情况下，当前配置会生成**所有受支持的组件**。

---

## 指定具体表

```bash
php bin/hyperf.php crud:generate User --table=erp_users
```

Model 仍然是 `User`，但数据会从 `erp_users` 表读取。

也可以同时指定 Schema 和表：

```bash
php bin/hyperf.php crud:generate User --table=public.users
```

---

## 直接从表名推断 Model

使用别名命令 `crud:generate-table`：

```bash
php bin/hyperf.php crud:generate-table users
```

推断示例：

```text
users       -> User
order_items -> OrderItem
products    -> Product
```

也可以配合生成器的其他选项：

```bash
php bin/hyperf.php crud:generate-table users \
  --connection=default \
  --schema=public \
  --dry-run
```

---

# 生成整个数据库 / Schema

有两种等价方式。

```bash
php bin/hyperf.php crud:generate --database
```

或者：

```bash
php bin/hyperf.php crud:generate-database
```

生成器会列出所选 Schema 中的表，检查元数据，并为每个有效表生成对应组件。

默认情况下，批量生成会排除 `migrations` 表。

---

## 只生成部分表

```bash
php bin/hyperf.php crud:generate-database \
  --tables=users,teams,products
```

如果 `--tables` 中指定的任意表不存在，命令会在正式生成之前失败。

---

## 排除表

```bash
php bin/hyperf.php crud:generate-database \
  --exclude=migrations,audit_logs,temp_data
```

CLI 中指定的排除项会与 `crud_generator.exclude_tables` 合并。

---

## 忽略没有主键的表

批量生成时：

```bash
php bin/hyperf.php crud:generate-database --skip-unsupported
```

该选项会报告并跳过没有主键的表。

支持复合主键。路由会按照 Schema 中的字段顺序，为每个主键列生成一个参数，例如：

```text
/{key1}/{key2}
```

不使用该选项时，遇到第一个不支持的表就会中断生成。

---

# 连接和 Schema 选择

## 使用其他连接

```bash
php bin/hyperf.php crud:generate-table users --connection=reporting
```

短写形式：

```bash
php bin/hyperf.php crud:generate-table users -c reporting
```

连接必须存在于：

```text
config/autoload/databases.php
```

## 使用其他 Schema

PostgreSQL：

```bash
php bin/hyperf.php crud:generate-table users \
  --connection=default \
  --schema=backoffice
```

SQL Server：

```bash
php bin/hyperf.php crud:generate-table users \
  --connection=sqlserver \
  --schema=erp
```

MySQL / MariaDB 使用数据库名称作为 catalog Schema。

---

# 可用组件

生成器目前支持：

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

如果只想生成部分组件：

```bash
php bin/hyperf.php crud:generate User \
  --components=model,repository,service,controller,routes
```

依赖组件会被自动添加。

例如：

```bash
php bin/hyperf.php crud:generate User --components=controller
```

这并不意味着只生成 Controller。引擎还会自动添加 Controller 正常工作所需要的 Model、DTO、Resource、Requests、Repository、binding、Service 和 Policy。

## 生成全部组件

```bash
php bin/hyperf.php crud:generate User --all
```

当前默认配置已经会生成所有组件。若应用自定义了 `crud_generator.components`，`--all` 仍然有用。

---

# 生成的目录结构

完整生成 `User` 时可能得到：

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

`config/crud-generator/User.php` 会自动注册：

```php
UserRepositoryInterface::class => UserRepository::class
```

`ConfigProvider` 会将该目录中的文件加载为容器 binding。

---

# 生成的路由

对于 `users` 表，对应 HTTP 资源为：

```text
/users
```

对于 `order_items`：

```text
/order-items
```

默认生成：

| 方法 | Endpoint | 操作 |
| --- | --- | --- |
| `GET` | `/users` | 分页列表 |
| `POST` | `/users` | 创建 |
| `GET` | `/users/{id}` | 按 ID 查询 |
| `PUT` | `/users/{id}` | 更新 |
| `PATCH` | `/users/{id}` | 部分更新 |
| `DELETE` | `/users/{id}` | 删除 |

复合主键会为每个主键列生成一个路径段，例如：

```text
/memberships/{key1}/{key2}
```

每个 OpenAPI 参数都会通过 `x-database-column` 指明对应的数据库列。

生成的路由包含以下 Middleware：

```php
GustavoQueiroz\HyperfCrudGenerator\Http\ValidationMiddleware::class
Hyperf\Validation\Middleware\ValidationMiddleware::class
```

第一个 Middleware 用于将验证失败统一转换为 API 的 JSON 响应格式。

在 `crud_generator.route_middlewares` 中配置的类会在这两个 Middleware 之后添加。

---

# 列表、分页、排序、过滤与搜索

生成的 Repository 支持：

```text
page
per_page
sort
direction
filter
search
```

示例：

```http
GET /users?page=2&per_page=20&sort=name&direction=asc&search=gustavo
```

过滤器以对象 / `deepObject` query 形式发送：

```http
GET /users?filter[active]=1&filter[team_id]=10
```

### 当前规则

- `page` 最小值：`1`
- `per_page` 最小值：`1`
- `per_page` 最大值：`100`
- `direction`：`asc` 或 `desc`
- `sort` 和 `filter` 只能使用 Schema 允许的字段
- `json` 和二进制字段不会用于排序
- 配置为 hidden 的字段不会用于排序或过滤
- `search` 会在可见文本字段上使用 `LIKE`
- 当排序字段不是主键时，会追加主键作为第二排序条件，以保证分页稳定性

列表响应：

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

`SchemaInspector` 会在生成之前读取真实数据库。

当前会采集：

- 表名；
- 字段名和字段顺序；
- 数据库类型；
- 是否可空；
- catalog 提供的默认值；
- 最大长度；
- 数值精度和小数位；
- identity / auto increment；
- generated / computed 字段；
- 主键；
- `UNIQUE` 索引；
- foreign keys；
- foreign key 的 `ON UPDATE` 和 `ON DELETE` 动作；
- `CHECK` constraints；
- 支持时读取数据库原生 enum。

---

# 自动生成的 Model

Model 会根据 Schema 自动配置：

```php
protected ?string $connection;
protected ?string $table;
protected string $primaryKey;
protected string $keyType;
protected array $fillable;
protected array $casts;
protected array $hidden;
```

还会推断：

- `$timestamps`；
- `CREATED_AT`；
- `UPDATED_AT`；
- `$incrementing`；
- 当存在可空的 `deleted_at` 时使用 `SoftDeletes`；
- SQL Server identity 处理；
- 可推断的关系。

Generated 字段、identity 字段和标准 timestamp 字段不会加入 `$fillable`。

在 Update 时，主键也会从允许修改的字段中移除。

---

# 类型映射

该包会把数据库类型标准化为 Model、验证和 OpenAPI 使用的类型类别。

示例：

| 数据库类型 | 标准类型 |
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
| 未明确识别的类型 | string |

部分自动生成的 casts：

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

`bigint` 在表示层 / OpenAPI 中会作为字符串处理，以降低 JSON 客户端出现精度丢失的风险。

---

# 自动验证规则

`StoreRequest` 和 `UpdateRequest` 会根据真实字段生成。

生成器可生成：

- `required`
- `sometimes`
- `nullable`
- `integer`
- `numeric`
- `boolean`
- `string`
- JSON 使用 `array`
- `uuid`
- `date`
- `date_format`
- `max:<length>`
- unsigned 数值使用 `min:0`
- 通过 regex 处理 decimal 精度 / scale
- enum 使用 `Rule::in(...)`
- 单列和复合 `UNIQUE` 使用 `Rule::unique(...)`
- 单列和复合 FK 使用 `Rule::exists(...)`
- 常见 `CHECK` 形式生成 `min`、`max`、`between` 和 `Rule::in(...)`

在 Update 中，`unique` 会排除当前主键标识的记录，包括复合主键。

对于复合约束，`required_with` 用于避免只验证其中一部分字段。

不可写字段——例如 identity、generated 字段，以及 Update 中的主键——会得到：

```php
['prohibited']
```

这样可以避免 FormRequest 静默接受额外字段。

---

# DTO 与属性保护

自动生成的 DTO 会在把数据传递给 Service / Repository 之前执行 allowlist：

```php
UserData::fromArray($request->validated())
```

即使数组中包含额外属性，也只会保留 Schema 允许的字段。

Update 时，DTO 不允许修改主键。

---

# 敏感字段

默认配置包含：

```php
'hidden' => [
    'password',
    'password_hash',
    'remember_token',
    'api_token',
    'secret',
],
```

这些字段：

- 只要字段可写，仍然可以写入；
- 不会出现在 `Resource` / response 中；
- 不参与过滤；
- 不参与排序；
- 不参与搜索；
- 在 OpenAPI 写入 Schema 中标记为 `writeOnly`。

二进制字段也会从默认响应中排除。

---

# 自动关系

生成器可以为**单字段外键**生成 ORM 关系。

## `BelongsTo`

当当前表存在已知 FK，并且目标表存在于 `model_map` 中时，可以生成 `BelongsTo`。

概念示例：

```text
users.team_id -> teams.id
```

## `HasOne` 和 `HasMany`

批量生成时，引擎会分析其他被选中的表，并尝试生成反向关系。

如果相关表的 FK 同时为 `UNIQUE` / PK，则反向关系会处理为 `HasOne`；否则处理为 `HasMany`。

方法名会采用保守策略生成，以避免与已有属性名称冲突。

---

# `model_map`

通过 `model_map` 可以明确指定 qualified table 对应的 Model：

```php
'model_map' => [
    'public.users' => 'User',
    'public.teams' => 'Team',
    'erp.customers' => 'Customer',
],
```

这样也可以与本次命令没有生成、但项目中已经存在的 Model 建立关系。

批量生成时，没有在 map 中配置的 Model 会根据表名自动推断。

如果两个表最终得到相同的 Model 名称或相同 HTTP resource，引擎会在写入任何文件之前终止。

---

# 授权与 Policy

每个生成的 Controller 都依赖对应资源的 Policy。

使用的 abilities：

```text
viewAny
view
create
update
delete
```

默认实现：

```text
GustavoQueiroz\HyperfCrudGenerator\Authorization\ConfigAuthorization
```

它读取：

```php
'authorization' => [
    'default' => 'allow',
    'rules' => [],
],
```

## 重要安全说明

当前发布的默认代码使用：

```php
'default' => 'allow'
```

因此，**如果不存在特定规则，则默认允许访问**。

对于私有 API，建议修改为：

```php
'authorization' => [
    'default' => 'deny',
    'rules' => [],
],
```

规则可以按 Model 和 ability 使用 callable：

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

也可以替换以下 binding：

```text
GustavoQueiroz\HyperfCrudGenerator\Authorization\AuthorizationInterface
```

并接入项目自己的 RBAC、ACL 或其他授权机制。

该包不会强制选择登录 / JWT / OAuth 方案，因为这取决于具体应用。

请把项目已经安装的认证 Middleware 配置到 `route_middlewares`，它们会自动添加到所有生成的 CRUD 路由组。

---

# HTTP 响应

生成的 Controller 会标准化部分错误：

| Status | 场景 |
| --- | --- |
| `201` | 资源已创建 |
| `204` | 资源已删除 |
| `403` | 授权失败 |
| `404` | Model 未找到 |
| `409` | 数据库 constraint 冲突（`SQLSTATE` class `23`） |
| `422` | 验证失败 |

验证响应：

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

# OpenAPI 和 Swagger UI

`openapi` 组件会为每个资源生成 **OpenAPI 3.0.3** 文档：

```text
docs/openapi/user.json
```

文档包括：

- CRUD 路由；
- 分页参数；
- 排序；
- 搜索；
- 过滤；
- `Response`、`Store` 和 `Update` Schema；
- 根据字段推导出的类型；
- `required`；
- nullable；
- enums；
- 最大长度；
- read-only 字段；
- write-only 字段；
- PK、UNIQUE、FK、引用动作和 `CHECK` 的 `x-database` metadata；
- 适用时包含 `403`、`404`、`409` 和 `422` 响应。

`swagger` 组件会向路由文件添加：

```text
GET /docs
GET /docs/openapi/{name}.json
```

`/docs` 页面使用 Swagger UI，并列出 `crud_generator.openapi_path` 中已有的 JSON 文件。

> 当前 Swagger UI HTML 会通过 CDN 加载 `swagger-ui-dist` assets。隔离环境可以自定义这一层。

---

# Dry Run

在不创建任何文件的情况下验证整个生成过程：

```bash
php bin/hyperf.php crud:generate User --dry-run
```

规划阶段会检查：

- 组件；
- Model / resource 名称冲突；
- 目标文件冲突；
- 计划生成 PHP 文件的语法；
- 目标目录权限；
- 路由块完整性；
- 是否存在会被覆盖的文件。

`dry-run` 期间不会创建任何目录或文件。

---

# Diff

在不修改文件的情况下显示 unified diff：

```bash
php bin/hyperf.php crud:generate User --diff
```

也支持批量：

```bash
php bin/hyperf.php crud:generate-database --diff
```

由于 `--diff` 运行在 preview 模式，因此不会写入计划文件。

---

# 安全重新生成

该包会维护一个 manifest：

```text
config/crud-generator/manifest.json
```

Schema 发生变化后，可以执行：

```bash
php bin/hyperf.php crud:generate User --regenerate
```

引擎会比较之前生成内容的 hash。

仍然与原始生成版本一致的文件可以自动更新。

## 自定义代码块

部分 stub 包含：

```text
// <crud-custom>
// </crud-custom>
```

这些标记中的代码会在重新生成时保留。

当前支持这些自定义块的组件：

- Model
- Repository
- Service
- Controller
- Policy

示例：

```php
// <crud-custom>
public function customMethod(): string
{
    return 'preserved';
}
// </crud-custom>
```

如果手工修改发生在受保护区域**之外**，重新生成会拒绝覆盖，除非使用 `--force`。

---

# Force

强制覆盖已有文件和生成的路由块：

```bash
php bin/hyperf.php crud:generate User --force
```

短写：

```bash
php bin/hyperf.php crud:generate User -f
```

谨慎使用：`--force` 允许替换现有内容。

引擎会在写入前对全部目标执行 Preflight，以避免只生成一半 CRUD 后才在最后一个文件失败。

---

# 路由与标记

路由会插入到带标识的块中：

```php
// <hyperf-crud-generator:User>
// ...
// </hyperf-crud-generator:User>
```

Swagger 路由使用独立块：

```php
// <hyperf-crud-generator:_swagger>
// ...
// </hyperf-crud-generator:_swagger>
```

引擎会检测：

- 重复标记；
- 不完整标记；
- 反向标记；
- 对已生成路由块的手工修改。

如果需要添加新块，路由文件末尾不应包含 `?>`。

---

# 配置

当前默认配置：

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

## 配置项

| Key | 作用 |
| --- | --- |
| `namespace` | 生成 artifact 的基础 namespace |
| `base_path` | 主要代码目录 |
| `routes_file` | 接收路由块的文件 |
| `openapi_path` | OpenAPI JSON 目录 |
| `test_path` | 自动生成测试目录 |
| `test_namespace` | 测试 namespace |
| `binding_path` | 生成器 binding 和 manifest 目录 |
| `connection` | 默认数据库连接 |
| `schema` | 默认 introspection Schema / 数据库 |
| `exclude_tables` | 批量生成时自动排除的表 |
| `model_map` | `schema.table => Model` 映射 |
| `stub_path` | 可选的自定义 stub 目录 |
| `authorization` | 默认授权适配器规则 |
| `route_middlewares` | 添加到 CRUD 路由组的应用 Middleware |
| `hidden` | 可写但不应出现在 response / filter 中的字段 |
| `force` | 通过配置启用全局覆盖 |
| `components` | CLI 未指定 `--components` 时生成的组件 |

---

# 自定义 Stubs

可通过以下配置替换单个 stub：

```php
'stub_path' => BASE_PATH . '/stubs/crud',
```

生成器会优先在该目录查找：

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

如果某个自定义 stub 不存在，则自动使用包内置的对应 stub。

---

# Factory 和 Seeder

Factory 会根据识别出的类型生成示例值。

示例：

```php
$user = UserFactory::create([
    'email' => 'user@example.com',
]);
```

当必须的外键无法安全推导时，需要通过 override 提供：

```php
$user = UserFactory::create([
    'team_id' => $team->id,
]);
```

Seeder 接收多行数据，并在事务中创建：

```php
$seeder = new UserSeeder();

$seeder->run([
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com'],
]);
```

---

# 自动生成的测试

`test` 组件会生成：

```text
UserControllerTest.php
UserServiceTest.php
```

测试覆盖：

- 授权；
- `404`；
- 创建 `201`；
- 更新；
- 删除 `204`；
- 防止意外字段；
- 正确传递分页 / filter；
- Update DTO 中主键不可变。

`integration_test` 组件会生成：

```text
UserRepositoryIntegrationTest.php
```

默认情况下该测试被禁用，需要：

```text
CRUD_INTEGRATION_TESTS=1
```

同时还需要真实 Hyperf 应用 bootstrap，以及包含目标 Schema 的可销毁测试数据库。

如果表存在必填 FK，可以通过 Model 专用环境变量提供 override：

```text
CRUD_TEST_User_FIXTURE
```

其值为包含有效 FK 数据的 JSON。

---

# 包自身的测试

## 单元测试

```bash
composer test
```

等价于：

```bash
vendor/bin/phpunit
```

仅运行 unit suite：

```bash
vendor/bin/phpunit --testsuite unit
```

## 使用真实数据库的 Catalog 测试

仓库包含 `compose.test.yaml`，其中包含：

- MySQL 8.4
- MariaDB 11.4
- PostgreSQL 16
- SQL Server 2022

可以通过 containers 执行：

```bash
docker compose -f compose.test.yaml up \
  --build \
  --abort-on-container-exit \
  --exit-code-from runner \
  runner
```

当 `CRUD_TEST_*` 环境变量配置完成后，也可以直接运行集成测试：

```bash
composer test:integration
```

---

# CLI 选项

主要生成选项：

| 选项 | 说明 |
| --- | --- |
| `--table=<table>` | 用于生成的表 |
| `--database` | 生成 Schema 中的所有表 |
| `--tables=a,b,c` | database 模式下的 allowlist |
| `--exclude=a,b,c` | 排除表 |
| `--connection=<name>` / `-c` | 数据库连接 |
| `--schema=<schema>` | introspection 使用的 Schema / 数据库 |
| `--components=a,b,c` | 选择组件 |
| `--all` | 选择全部组件 |
| `--dry-run` | 验证但不写入 |
| `--diff` | 打印 diff 但不写入 |
| `--regenerate` | 根据 manifest 重新生成受控文件 |
| `--skip-unsupported` | database 模式下跳过无主键表 |
| `--force` / `-f` | 强制替换 |

`--tables` 和 `--skip-unsupported` 只能在 database 生成模式中使用。

`--database` 不能与 Model 或 `--table` 同时使用。

---

# 内部架构

主要组件：

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

### 简化流程

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
生成的文件
```

---

# 当前限制

该包是 schema-aware 的，但并非所有关系数据库结构都能自动转换为 Hyperf CRUD。

## 主键

必须存在主键。支持单主键和复合主键：

```sql
PRIMARY KEY (column_a, column_b)
```

对于复合主键，Controller 和 OpenAPI 使用：

```text
/{key1}/{key2}
```

Service / Repository 使用关联数组：

```php
['column_a' => $key1, 'column_b' => $key2]
```

生成的 Repository 会通过主键的所有字段进行查询、更新和删除，不依赖 `Model::find()` 来处理复合主键。

没有主键的表仍然缺少安全的 CRUD 唯一标识。

批量生成时可以使用：

```text
--skip-unsupported
```

将其跳过。

## 复合 Constraints

复合 `UNIQUE` 索引和复合 foreign key 会生成带其他 constraint 字段 scope 的规则，各部分也会收到 `required_with`。

ORM 的 `BelongsTo`、`HasOne`、`HasMany` 仍然只为单字段 FK 生成，因为 Hyperf / Eloquent 原生关系不支持复合键。

在 validation 与写入之间存在并发情况时，数据库仍然是最终权威；constraint violation 会转换为 HTTP `409`。

## Views

批量生成只列出基础表。View 和 materialized view 不会自动视为 CRUD resource。

## 函数 / 部分索引

PostgreSQL 中的 functional index 和 partial index 不会被转换为简单验证规则。

SQL Server 中的 filtered index 会被生成器用于 `UNIQUE` introspection 的逻辑忽略。

## 数据库专用类型

已知类型会被标准化。

未明确映射的专用类型会退回到 `string`。

对于 geospatial、range、array 和 user-defined type，请人工检查生成结果。

## `CHECK` Constraints

Catalog 会在 `x-database.checks` 中保留全部表达式。

生成器会自动转换以下安全标量模式：

- `BETWEEN`
- `>=`
- `<=`
- `IN`
- `CHAR_LENGTH`
- `CHARACTER_LENGTH`
- `LENGTH`
- `LEN`

包含数据库专用函数、字段间比较、SQL regex、复杂子表达式或依赖 session 的逻辑仍由数据库负责。

## FK 动作

以下规则会被读取并发布到 `x-database.foreignKeys`：

```text
ON DELETE CASCADE
ON DELETE SET NULL
ON UPDATE CASCADE
```

生成器不会在 Service / Repository 中重复这些 cascade。

引用动作会由数据库原子执行。

## 认证

该包提供 Policy / 授权能力，并会把 `route_middlewares` 中配置的 Middleware 添加到自动生成的路由。

由于 Schema 无法得知应用使用哪一种身份认证库，因此登录、JWT、OAuth 的安装和配置仍由消费该包的项目负责。

---

# 大型数据库建议

在 ERP 或拥有数百张表的数据库中使用生成器时，建议先 preview：

```bash
php bin/hyperf.php crud:generate-database \
  --dry-run \
  --skip-unsupported
```

然后通过：

```text
--tables=...
```

或：

```text
--exclude=...
```

缩小生成范围。

对于不应暴露为 API 的内部表，也可以自定义 `crud_generator.components`，避免生成 Controller / routes。

---

# 推荐使用流程

### 1. 检查数据库连接

```bash
php bin/hyperf.php crud:generate-table users --dry-run
```

### 2. 查看 Diff

```bash
php bin/hyperf.php crud:generate-table users --diff
```

### 3. 正式生成

```bash
php bin/hyperf.php crud:generate-table users
```

### 4. 检查授权

对于私有 API：

```php
'authorization' => [
    'default' => 'deny',
    'rules' => [
        // ...
    ],
],
```

### 5. 根据应用配置路由认证

```php
'route_middlewares' => [
    \App\Middleware\JwtAuthMiddleware::class,
],
```

### 6. 执行测试

```bash
composer test
```

### 7. Schema 修改之后

```bash
php bin/hyperf.php crud:generate-table users --diff --regenerate
```

如果 preview 正确：

```bash
php bin/hyperf.php crud:generate-table users --regenerate
```

---

# License

MIT.
