# Trilha de auditoria

O módulo usa o padrão **Audit Log append-only** combinado com **Eloquent Observer**.

- `AuditableObserver` registra automaticamente criação, edição e exclusão dos models de negócio.
- `AuditLogger` registra ações explícitas que não correspondem diretamente a um evento Eloquent, como login, logout, exportação, download, vínculos e processamento por IA.
- `AuditEventCategory` mantém o catálogo tipado de eventos e seus rótulos.
- `audit_logs` não possui endpoints de alteração ou exclusão.

Cada evento armazena:

- categoria tipada;
- usuário responsável e uma cópia de nome/e-mail;
- tipo, ID e identificação legível do registro afetado;
- valores anteriores e novos dos campos alterados;
- metadados da ação;
- endereço IP, user agent e data/hora.

Senhas, tokens e chaves são substituídos por `[REDACTED]` antes da persistência.

## Acesso

Os endpoints exigem autenticação, usuário ativo, capacidade administrativa e a permissão `audit_logs.view`:

```text
GET /api/v1/audit-logs
GET /api/v1/audit-logs/options
```

Filtros disponíveis em `GET /api/v1/audit-logs`:

```text
event
user_id
date_from
date_to
page
per_page
```

O seeder atribui `audit_logs.view` ao grupo de sistema `Administrador` por meio da sincronização de todas as permissões.

## Deploy

Depois de publicar o código:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```
