# Changelog

Todas as mudanças relevantes deste pacote são documentadas neste arquivo.

## [1.2.0] - 2026-09-09

### Adicionado

- CRUD completo para chaves primárias compostas, incluindo rotas, Controller, Service, Repository, OpenAPI e testes gerados.
- Validação com escopo para índices `UNIQUE` e foreign keys compostos.
- Leitura de `CHECK` constraints com conversão dos padrões escalares seguros em regras de validação.
- Leitura das ações `ON UPDATE`/`ON DELETE` e publicação desses metadados no OpenAPI.
- Configuração `route_middlewares` para autenticação e outros middlewares da aplicação.

### Corrigido

- Controller gerado passa a usar `MessageBag::getMessages()` ao serializar erros de validação.
- Swagger UI serializa URLs das especificações sem barras escapadas.

## [1.1.1] - 2026-09-09

### Corrigido

- Respostas JSON de validação agora usam a API pública `MessageBag::getMessages()` do Hyperf 3.1/3.2.
- URLs das especificações no Swagger UI são serializadas sem barras escapadas.
- Testes unitários, código gerado e catálogos reais foram validados em MySQL, MariaDB, PostgreSQL e SQL Server.

## [1.1.0] - 2026-09-09

### Adicionado

- Introspecção de schema para MySQL, MariaDB, PostgreSQL e SQL Server.
- Geração de Model, DTO, Resource, Requests, Repository, Service, Controller, Policy, Factory e Seeder.
- Geração de rotas, OpenAPI 3.0.3, Swagger UI e testes unitários/de integração.
- Paginação, filtros, ordenação, busca, relacionamentos e regras de validação derivadas do schema.
- Comandos para tabela e database, com `--dry-run`, `--diff`, `--regenerate` e `--force`.
- Manifesto de geração e preservação de blocos customizados.

## [1.0.0]

- Primeira versão estável do gerador baseado em stubs.

[1.2.0]: https://github.com/Gustavo-queirozman/hyperf-crud-generator/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/Gustavo-queirozman/hyperf-crud-generator/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/Gustavo-queirozman/hyperf-crud-generator/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Gustavo-queirozman/hyperf-crud-generator/releases/tag/v1.0.0
