# Hyperf CRUD Generator

Gerador de APIs CRUD **schema-aware** para aplicações **Hyperf 3.1 e 3.2**, criado para transformar tabelas existentes de um banco de dados em uma estrutura de API organizada, validada e documentada.

O pacote inspeciona o schema real do banco e pode gerar automaticamente Model, DTO, Resource, Requests, Repository, Service, Controller, Policy, rotas, Factory, Seeder, OpenAPI/Swagger e testes.

> Versão atual do código: **v1.1.1**

## Principais recursos

- Geração a partir de tabelas existentes.
- Geração de uma tabela específica ou de um banco/schema inteiro.
- Suporte a **MySQL**, **MariaDB**, **PostgreSQL** e **SQL Server**.
- Inferência automática do nome do Model a partir da tabela.
- Leitura de colunas, chave primária, índices `UNIQUE` e chaves estrangeiras.
- `$fillable`, `$casts`, `$hidden`, timestamps e `SoftDeletes` derivados do schema.
- Validações geradas a partir de tipos, nulabilidade, tamanho, enum, `UNIQUE` simples e FK simples.
- Relacionamentos `BelongsTo`, `HasOne` e `HasMany` quando inferíveis.
- Repository Interface + implementação e binding automático no container.
- DTO para limitar os campos recebidos pela camada de serviço.
- Resource para controlar os campos retornados pela API.
- Paginação, ordenação, filtros por igualdade e busca textual.
- Policies com adaptador de autorização configurável.
- Factory e Seeder.
- OpenAPI 3.0.3 em JSON.
- Swagger UI em `/docs`.
- Testes de Controller e Service.
- Teste de integração do Repository.
- `--dry-run` e `--diff` antes de escrever arquivos.
- Geração em lote com allowlist e exclusões.
- Manifesto de geração para regeneração segura.
- Preservação de código customizado dentro de blocos `<crud-custom>`.
- Preflight de todos os arquivos antes da escrita para evitar geração parcial em caso de conflito.

---

## Requisitos

- PHP **8.2+**
- Hyperf **3.1 ou 3.2**
- Uma conexão de banco configurada em `config/autoload/databases.php`

Dependências principais do pacote:

```text
hyperf/command
doctrine/inflector
hyperf/contract
hyperf/db-connection
hyperf/http-server
hyperf/paginator
hyperf/validation
```

Para PostgreSQL, a aplicação de destino deve possuir o driver PostgreSQL compatível com a versão do Hyperf.

Para SQL Server, a aplicação deve possuir o driver `hyperf/database-sqlserver` compatível com sua instalação do Hyperf.

> O gerador não aceita conexões com `prefix` de tabela configurado. Para geração por introspecção, utilize uma conexão sem prefixo.

---

## Instalação

### Composer

Se o pacote estiver disponível no repositório Composer utilizado pelo projeto:

```bash
composer require gustavoqueiroz/hyperf-crud-generator:^1.1
```

Publique o arquivo de configuração:

```bash
php bin/hyperf.php vendor:publish gustavoqueiroz/hyperf-crud-generator
```

O arquivo será criado em:

```text
config/autoload/crud_generator.php
```

### Desenvolvimento local com repository `path`

No `composer.json` da aplicação Hyperf:

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

Depois execute:

```bash
composer require gustavoqueiroz/hyperf-crud-generator:@dev
php bin/hyperf.php vendor:publish gustavoqueiroz/hyperf-crud-generator
```

---

# Bancos suportados

| Banco | Driver reconhecido | Schema padrão |
|---|---|---|
| MySQL | `mysql` | nome do banco configurado |
| MariaDB | `mariadb` | nome do banco configurado |
| PostgreSQL | `pgsql`, `postgres`, `postgresql` | `public` |
| SQL Server | `sqlsrv`, `sqlserver`, `mssql` | `dbo` |

A listagem em lote considera somente **tabelas base**. Views não são incluídas automaticamente.

---

# Uso rápido

## Gerar CRUD informando o Model

```bash
php bin/hyperf.php crud:generate User
```

Neste caso, a tabela é inferida a partir do nome do Model:

```text
User -> users
OrderItem -> order_items
```

Por padrão, a configuração atual gera **todos os componentes suportados**.

---

## Informar uma tabela específica

```bash
php bin/hyperf.php crud:generate User --table=erp_users
```

O Model será `User`, mas os dados serão lidos de `erp_users`.

Também é possível informar schema e tabela juntos:

```bash
php bin/hyperf.php crud:generate User --table=public.users
```

---

## Inferir o Model diretamente da tabela

Use o alias `crud:generate-table`:

```bash
php bin/hyperf.php crud:generate-table users
```

Exemplos de inferência:

```text
users       -> User
order_items -> OrderItem
products    -> Product
```

Também pode ser utilizado com as demais opções do gerador:

```bash
php bin/hyperf.php crud:generate-table users \
  --connection=default \
  --schema=public \
  --dry-run
```

---

# Gerar um banco/schema inteiro

Existem duas formas equivalentes.

```bash
php bin/hyperf.php crud:generate --database
```

ou:

```bash
php bin/hyperf.php crud:generate-database
```

O gerador lista as tabelas do schema selecionado, inspeciona os metadados e cria os componentes para cada tabela válida.

Por padrão, a tabela `migrations` é excluída da geração em lote.

---

## Gerar somente algumas tabelas

```bash
php bin/hyperf.php crud:generate-database \
  --tables=users,teams,products
```

Se alguma tabela informada em `--tables` não existir, o comando falha antes da geração.

---

## Excluir tabelas

```bash
php bin/hyperf.php crud:generate-database \
  --exclude=migrations,audit_logs,temp_data
```

As exclusões informadas pela CLI são combinadas com `crud_generator.exclude_tables`.

---

## Ignorar tabelas sem PK simples

Em geração em lote:

```bash
php bin/hyperf.php crud:generate-database --skip-unsupported
```

Essa opção faz o gerador reportar e ignorar tabelas que:

- não possuem chave primária; ou
- possuem chave primária composta.

Sem essa opção, a primeira tabela não suportada interrompe a geração.

---

# Seleção da conexão e schema

## Outra conexão

```bash
php bin/hyperf.php crud:generate-table users --connection=reporting
```

Forma curta:

```bash
php bin/hyperf.php crud:generate-table users -c reporting
```

A conexão deve existir em:

```text
config/autoload/databases.php
```

## Outro schema

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

MySQL/MariaDB usam o nome do banco como schema de catálogo.

---

# Componentes disponíveis

O gerador atualmente reconhece os seguintes componentes:

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

Para selecionar apenas alguns:

```bash
php bin/hyperf.php crud:generate User \
  --components=model,repository,service,controller,routes
```

As dependências são incluídas automaticamente.

Por exemplo:

```bash
php bin/hyperf.php crud:generate User --components=controller
```

não gera somente o Controller. O motor também adiciona os componentes necessários para que ele funcione, como Model, DTO, Resource, Requests, Repository, binding, Service e Policy.

## Gerar todos os componentes

```bash
php bin/hyperf.php crud:generate User --all
```

Na configuração distribuída atualmente, todos os componentes já são o padrão. `--all` continua útil caso `crud_generator.components` seja customizado na aplicação.

---

# Estrutura gerada

Uma geração completa de `User` pode produzir:

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

O arquivo em `config/crud-generator/User.php` registra automaticamente:

```php
UserRepositoryInterface::class => UserRepository::class
```

O `ConfigProvider` carrega os arquivos desse diretório como bindings do container.

---

# Rotas geradas

Para uma tabela `users`, o recurso HTTP será `/users`.

Para uma tabela como `order_items`, o recurso será:

```text
/order-items
```

As rotas geradas são:

| Método | Endpoint | Ação |
|---|---|---|
| `GET` | `/users` | listagem paginada |
| `POST` | `/users` | criação |
| `GET` | `/users/{id}` | consulta por ID |
| `PUT` | `/users/{id}` | atualização |
| `PATCH` | `/users/{id}` | atualização parcial |
| `DELETE` | `/users/{id}` | exclusão |

As rotas recebem os middlewares:

```php
GustavoQueiroz\HyperfCrudGenerator\Http\ValidationMiddleware::class
Hyperf\Validation\Middleware\ValidationMiddleware::class
```

O primeiro normaliza falhas de validação para o contrato JSON da API.

---

# Listagem, paginação, ordenação, filtros e busca

O Repository gerado suporta:

```text
page
per_page
sort
direction
filter
search
```

Exemplo:

```text
GET /users?page=2&per_page=20&sort=name&direction=asc&search=gustavo
```

Filtros são enviados como objeto/query `deepObject`:

```text
GET /users?filter[active]=1&filter[team_id]=10
```

### Regras atuais

- `page` mínimo: `1`
- `per_page` mínimo: `1`
- `per_page` máximo: `100`
- `direction`: `asc` ou `desc`
- somente colunas permitidas pelo schema podem ser utilizadas em `sort` e `filter`
- campos `json` e binários não são usados para ordenação
- campos configurados como ocultos não são usados para ordenação ou filtro
- `search` usa `LIKE` nas colunas textuais visíveis
- quando a ordenação não é pela PK, a PK é adicionada como segundo critério para estabilizar a paginação

Resposta de listagem:

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

# Introspecção do schema

O `SchemaInspector` lê o banco de dados real antes da geração.

Atualmente são coletados:

- nome das tabelas;
- nome e ordem das colunas;
- tipo do banco;
- nulabilidade;
- valor/default informado pelo catálogo;
- tamanho máximo;
- precisão e escala numérica;
- identidade/auto incremento;
- colunas geradas/computadas;
- chave primária;
- índices `UNIQUE`;
- foreign keys;
- enums nativos quando disponíveis.

---

# Model gerado

O Model é configurado automaticamente com base no schema:

```php
protected ?string $connection;
protected ?string $table;
protected string $primaryKey;
protected string $keyType;
protected array $fillable;
protected array $casts;
protected array $hidden;
```

Também são inferidos:

- `$timestamps`;
- `CREATED_AT`;
- `UPDATED_AT`;
- `$incrementing`;
- `SoftDeletes` quando existe `deleted_at` nullable;
- tratamento de identity do SQL Server;
- relacionamentos inferíveis.

Colunas geradas, identity e timestamps convencionais não entram em `$fillable`.

A chave primária também é removida dos campos permitidos durante atualização.

---

# Mapeamento de tipos

O pacote normaliza os tipos do banco em categorias usadas pelo Model, validação e OpenAPI.

Exemplos:

| Banco | Categoria usada |
|---|---|
| `bool`, `boolean`, `bit` | boolean |
| `smallint`, `int`, `integer`, `serial` | integer |
| `bigint`, `bigserial` | bigint |
| `decimal`, `numeric`, `money` | decimal |
| `float`, `real`, `double` | number |
| `json`, `jsonb` | json |
| `uuid`, `uniqueidentifier` | uuid |
| `date` | date |
| timestamps/datetimes | datetime |
| `time` | time |
| blob/binary/bytea/rowversion | binary |
| tipos não reconhecidos especificamente | string |

Alguns casts gerados:

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

`bigint` é tratado como string na camada de representação/OpenAPI para reduzir risco de perda de precisão em clientes JSON.

---

# Validações automáticas

Os `StoreRequest` e `UpdateRequest` são derivados das colunas reais.

O gerador pode produzir regras para:

- `required`;
- `sometimes`;
- `nullable`;
- `integer`;
- `numeric`;
- `boolean`;
- `string`;
- `array` para JSON;
- `uuid`;
- `date`;
- `date_format`;
- `max:<length>`;
- `min:0` para valores unsigned;
- precisão/escala decimal por regex;
- enum com `Rule::in(...)`;
- `Rule::unique(...)` para `UNIQUE` de uma única coluna;
- `Rule::exists(...)` para foreign key de uma única coluna.

No Update, a regra `unique` utiliza a chave primária atual no `ignore(...)`.

Campos que não podem ser escritos — como identity, colunas geradas e PK no Update — recebem regra:

```php
['prohibited']
```

Isso evita que valores extras sejam aceitos silenciosamente pelo FormRequest.

---

# DTO e proteção de atributos

O DTO gerado aplica uma allowlist antes de enviar os dados para o Service/Repository:

```php
UserData::fromArray($request->validated())
```

Mesmo que um array contenha atributos extras, somente os campos permitidos pelo schema são mantidos.

Durante update, a chave primária não pode ser alterada pelo DTO.

---

# Campos sensíveis

A configuração padrão contém:

```php
'hidden' => [
    'password',
    'password_hash',
    'remember_token',
    'api_token',
    'secret',
],
```

Esses campos:

- continuam podendo ser gravados quando forem colunas graváveis;
- são omitidos do `Resource`/response;
- não entram em filtros;
- não entram em ordenação;
- não entram na busca;
- aparecem como `writeOnly` nos schemas de escrita do OpenAPI.

Campos binários também são excluídos da resposta padrão.

---

# Relacionamentos automáticos

O gerador consegue criar relacionamentos para foreign keys de **uma coluna**.

## `BelongsTo`

Quando a tabela atual possui uma FK conhecida e a tabela de destino está no `model_map`, pode ser gerado um `BelongsTo`.

Exemplo conceitual:

```text
users.team_id -> teams.id
```

## `HasOne` e `HasMany`

Durante geração em lote, o motor analisa as outras tabelas selecionadas e pode gerar a relação inversa.

Quando a FK da tabela relacionada também é `UNIQUE`/PK, a relação inversa é tratada como `HasOne`; caso contrário, `HasMany`.

Os nomes dos métodos são gerados de forma conservadora para evitar colisões com atributos existentes.

---

# `model_map`

Use `model_map` para definir explicitamente o Model associado a uma tabela qualificada:

```php
'model_map' => [
    'public.users' => 'User',
    'public.teams' => 'Team',
    'erp.customers' => 'Customer',
],
```

Isso também permite criar relações com Models existentes que não estejam sendo gerados naquele comando.

Em geração em lote, Models não configurados no mapa são inferidos a partir do nome da tabela.

Se duas tabelas resultarem no mesmo Model ou no mesmo recurso HTTP, o motor interrompe a geração antes de escrever arquivos.

---

# Autorização e Policies

Cada Controller gerado depende de uma Policy específica do recurso.

Abilities utilizadas:

```text
viewAny
view
create
update
delete
```

A implementação padrão é:

```text
GustavoQueiroz\HyperfCrudGenerator\Authorization\ConfigAuthorization
```

Ela lê:

```php
'authorization' => [
    'default' => 'allow',
    'rules' => [],
],
```

## Importante sobre segurança

O código distribuído atualmente utiliza:

```php
'default' => 'allow'
```

Portanto, **na ausência de uma regra específica, o acesso é permitido**.

Para APIs privadas, é recomendado alterar para:

```php
'authorization' => [
    'default' => 'deny',
    'rules' => [],
],
```

As regras podem ser callables indexados por Model e ability:

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

Também é possível substituir o binding de:

```text
GustavoQueiroz\HyperfCrudGenerator\Authorization\AuthorizationInterface
```

por um adaptador próprio para RBAC, ACL ou outro mecanismo da aplicação.

> A autenticação da aplicação continua sendo responsabilidade do projeto consumidor. O pacote não adiciona automaticamente middleware de login/JWT/OAuth às rotas geradas.

---

# Respostas HTTP

O Controller gerado padroniza alguns erros:

| Status | Situação |
|---|---|
| `201` | recurso criado |
| `204` | recurso removido |
| `403` | autorização negada |
| `404` | Model não encontrado |
| `409` | conflito de constraint do banco (`SQLSTATE` classe `23`) |
| `422` | falha de validação |

Formato de validação:

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

# OpenAPI e Swagger UI

O componente `openapi` gera um documento **OpenAPI 3.0.3** para cada recurso:

```text
docs/openapi/user.json
```

O documento inclui:

- rotas CRUD;
- parâmetros de paginação;
- ordenação;
- busca;
- filtros;
- schemas de `Response`, `Store` e `Update`;
- tipos derivados das colunas;
- `required`;
- nullable;
- enums;
- tamanho máximo;
- campos read-only;
- campos write-only;
- metadata de PK, UNIQUE e FKs em `x-database`;
- respostas `403`, `404`, `409` e `422` quando aplicável.

O componente `swagger` adiciona ao arquivo de rotas:

```text
GET /docs
GET /docs/openapi/{name}.json
```

A página `/docs` usa Swagger UI e lista os arquivos JSON existentes em `crud_generator.openapi_path`.

> O HTML atual do Swagger UI carrega os assets de `swagger-ui-dist` por CDN. Ambientes isolados podem optar por customizar essa camada.

---

# Dry run

Valida a geração inteira sem criar arquivos:

```bash
php bin/hyperf.php crud:generate User --dry-run
```

O planejamento executa verificações de:

- componentes;
- colisões de Model/recurso;
- conflitos de destino;
- sintaxe PHP dos arquivos planejados;
- permissões dos diretórios de destino;
- integridade dos blocos de rota;
- existência de arquivos que seriam sobrescritos.

Nenhum diretório ou arquivo é criado durante o `dry-run`.

---

# Diff

Exibe um diff unificado sem alterar os arquivos:

```bash
php bin/hyperf.php crud:generate User --diff
```

Também funciona em lote:

```bash
php bin/hyperf.php crud:generate-database --diff
```

Como `--diff` trabalha em modo de preview, ele não grava os arquivos planejados.

---

# Regeneração segura

O pacote mantém um manifesto em:

```text
config/crud-generator/manifest.json
```

Para regenerar código depois de uma alteração no schema:

```bash
php bin/hyperf.php crud:generate User --regenerate
```

O motor compara hashes do conteúdo anteriormente gerado.

Arquivos que ainda correspondem à versão gerada podem ser atualizados automaticamente.

## Blocos customizados

Alguns stubs possuem:

```php
// <crud-custom>
// </crud-custom>
```

O conteúdo colocado dentro desses marcadores é preservado durante a regeneração.

Atualmente esses blocos existem nos componentes:

- Model;
- Repository;
- Service;
- Controller;
- Policy.

Exemplo:

```php
// <crud-custom>
public function customMethod(): string
{
    return 'preserved';
}
// </crud-custom>
```

Alterações manuais **fora** das áreas protegidas fazem a regeneração recusar a substituição, a menos que `--force` seja utilizado.

---

# Force

Para sobrescrever arquivos existentes e blocos de rota gerados:

```bash
php bin/hyperf.php crud:generate User --force
```

Forma curta:

```bash
php bin/hyperf.php crud:generate User -f
```

Use com cuidado: `--force` permite substituir conteúdo existente.

O motor executa um preflight de todos os destinos antes da escrita para evitar gerar metade de um CRUD e falhar somente no último arquivo.

---

# Rotas e marcadores

Rotas são inseridas em blocos identificados:

```php
// <hyperf-crud-generator:User>
// ...
// </hyperf-crud-generator:User>
```

As rotas do Swagger utilizam um bloco próprio:

```php
// <hyperf-crud-generator:_swagger>
// ...
// </hyperf-crud-generator:_swagger>
```

O motor detecta:

- marcadores duplicados;
- marcadores incompletos;
- marcadores invertidos;
- alteração manual do bloco gerado.

O arquivo de rotas não deve possuir `?>` no final quando novos blocos precisarem ser adicionados.

---

# Configuração

Configuração distribuída atualmente:

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

## Opções

| Chave | Função |
|---|---|
| `namespace` | namespace base dos artefatos gerados |
| `base_path` | diretório principal de código |
| `routes_file` | arquivo que recebe os blocos de rota |
| `openapi_path` | diretório dos JSON OpenAPI |
| `test_path` | diretório dos testes gerados |
| `test_namespace` | namespace dos testes |
| `binding_path` | bindings e manifesto do gerador |
| `connection` | conexão padrão de banco |
| `schema` | schema/banco padrão para introspecção |
| `exclude_tables` | exclusões automáticas em geração em lote |
| `model_map` | mapeamento `schema.table => Model` |
| `stub_path` | diretório opcional de stubs customizados |
| `authorization` | regras do adaptador de autorização padrão |
| `hidden` | campos graváveis que não devem aparecer nas respostas/filtros |
| `force` | permite sobrescrita global via configuração |
| `components` | componentes gerados quando a CLI não especifica `--components` |

---

# Stubs customizados

É possível substituir stubs individuais configurando:

```php
'stub_path' => BASE_PATH . '/stubs/crud',
```

O gerador procura primeiro, nesse diretório:

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

Se um stub customizado não existir, o pacote utiliza automaticamente o stub interno correspondente.

---

# Factory e Seeder

A Factory gera valores de exemplo com base nos tipos detectados.

Exemplo de uso:

```php
$user = UserFactory::create([
    'email' => 'user@example.com',
]);
```

Valores de foreign keys obrigatórias devem ser fornecidos por override quando não puderem ser derivados com segurança:

```php
$user = UserFactory::create([
    'team_id' => $team->id,
]);
```

O Seeder recebe uma lista de linhas e executa a criação dentro de transação:

```php
$seeder = new UserSeeder();

$seeder->run([
    ['name' => 'User 1', 'email' => 'user1@example.com'],
    ['name' => 'User 2', 'email' => 'user2@example.com'],
]);
```

---

# Testes gerados

O componente `test` gera:

```text
UserControllerTest.php
UserServiceTest.php
```

Os testes verificam cenários como:

- autorização;
- `404`;
- criação `201`;
- atualização;
- exclusão `204`;
- proteção de campos inesperados;
- passagem correta de paginação/filtros;
- imutabilidade da chave primária no DTO de update.

O componente `integration_test` gera:

```text
UserRepositoryIntegrationTest.php
```

Esse teste é desativado por padrão e requer:

```bash
CRUD_INTEGRATION_TESTS=1
```

além de um bootstrap real da aplicação Hyperf e um banco descartável com o schema existente.

Quando uma tabela possui foreign keys obrigatórias, overrides podem ser fornecidos por variável de ambiente específica do Model:

```text
CRUD_TEST_User_FIXTURE
```

com JSON contendo valores de FK existentes.

---

# Testes do próprio pacote

## Testes unitários

```bash
composer test
```

Equivalente a:

```bash
vendor/bin/phpunit
```

Somente a suíte unitária:

```bash
vendor/bin/phpunit --testsuite unit
```

## Testes de catálogo com bancos reais

O repositório inclui `compose.test.yaml` com:

- MySQL 8.4;
- MariaDB 11.4;
- PostgreSQL 16;
- SQL Server 2022.

Uma forma de executar a suíte em containers é:

```bash
docker compose -f compose.test.yaml up \
  --build \
  --abort-on-container-exit \
  --exit-code-from runner \
  runner
```

A suíte de integração também pode ser executada diretamente quando as variáveis `CRUD_TEST_*` estiverem configuradas:

```bash
composer test:integration
```

---

# Opções da CLI

Principais opções disponíveis nos comandos de geração:

| Opção | Descrição |
|---|---|
| `--table=<table>` | tabela utilizada para geração |
| `--database` | gera todas as tabelas do schema |
| `--tables=a,b,c` | allowlist em geração de database |
| `--exclude=a,b,c` | exclui tabelas |
| `--connection=<name>` / `-c` | conexão de banco |
| `--schema=<schema>` | schema ou banco para introspecção |
| `--components=a,b,c` | seleciona componentes |
| `--all` | seleciona todos os componentes |
| `--dry-run` | valida sem gravar |
| `--diff` | imprime diff sem gravar |
| `--regenerate` | regenera arquivos controlados pelo manifesto |
| `--skip-unsupported` | ignora PK ausente/composta em modo database |
| `--force` / `-f` | força substituição |

`--tables` e `--skip-unsupported` só podem ser utilizados no modo de geração de database.

`--database` não pode ser combinado com um Model ou `--table`.

---

# Arquitetura interna

Os principais componentes do pacote são:

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

### Fluxo simplificado

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
Arquivos gerados
```

---

# Limitações atuais

O pacote é schema-aware, mas nem toda estrutura possível de um banco relacional pode ser transformada automaticamente em CRUD Hyperf.

## Chave primária

É exigida exatamente **uma coluna de chave primária**.

Ainda não há suporte de CRUD ORM para:

```text
PRIMARY KEY (column_a, column_b)
```

ou tabelas sem PK.

Em lote, use:

```bash
--skip-unsupported
```

para ignorá-las.

## `UNIQUE` composto

O catálogo detecta índices `UNIQUE` compostos, mas a regra de validação automática `Rule::unique()` é criada somente para constraints de **uma coluna**.

Constraints compostas continuam sendo aplicadas pelo próprio banco de dados.

## Foreign keys compostas

Foreign keys compostas são lidas pelo catálogo, porém a geração automática de:

- `Rule::exists()`;
- `BelongsTo`;
- `HasOne`;
- `HasMany`;

é realizada somente para FKs de uma coluna.

## Views

A geração em lote lista apenas tabelas base. Views e materialized views não são tratadas como recursos CRUD automaticamente.

## Índices funcionais/parciais

No PostgreSQL, índices funcionais e parciais não são convertidos em regras simples de validação.

No SQL Server, índices filtrados são ignorados pela introspecção de `UNIQUE` usada pelo gerador.

## Tipos específicos do banco

Tipos conhecidos são normalizados. Tipos especializados não mapeados explicitamente caem na categoria `string`.

Revise o resultado para tipos como geoespaciais, ranges, arrays e tipos definidos pelo usuário.

## `CHECK` constraints

`CHECK` constraints ainda não são convertidas automaticamente em regras Hyperf Validation.

## Ações de FK

Regras como:

```text
ON DELETE CASCADE
ON DELETE SET NULL
ON UPDATE CASCADE
```

não são atualmente utilizadas para geração de comportamento na aplicação.

## Autenticação

O pacote possui Policy/autorização, mas não instala middleware de autenticação automaticamente.

Integre as rotas com o mecanismo de autenticação da aplicação antes de expor APIs privadas.

---

# Recomendações para bancos grandes

Ao utilizar o gerador em ERPs ou bancos com centenas de tabelas, prefira começar com preview:

```bash
php bin/hyperf.php crud:generate-database \
  --dry-run \
  --skip-unsupported
```

Depois reduza o escopo com:

```bash
--tables=...
```

ou:

```bash
--exclude=...
```

Também pode ser útil customizar `crud_generator.components` para não gerar Controller/rotas para tabelas internas que não devem ser expostas como API.

---

# Exemplo de fluxo recomendado

### 1. Conferir conexão

```bash
php bin/hyperf.php crud:generate-table users --dry-run
```

### 2. Ver o diff

```bash
php bin/hyperf.php crud:generate-table users --diff
```

### 3. Gerar

```bash
php bin/hyperf.php crud:generate-table users
```

### 4. Revisar autorização

Para APIs privadas:

```php
'authorization' => [
    'default' => 'deny',
    'rules' => [
        // ...
    ],
],
```

### 5. Adicionar autenticação às rotas conforme a aplicação

### 6. Executar os testes

```bash
composer test
```

### 7. Depois de alterar o schema

```bash
php bin/hyperf.php crud:generate-table users --diff --regenerate
```

Se o preview estiver correto:

```bash
php bin/hyperf.php crud:generate-table users --regenerate
```

---

# Licença

MIT.
