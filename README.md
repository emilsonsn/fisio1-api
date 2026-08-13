# Fisio1 API

API Laravel 13 para a gestão clínica do protótipo Fisio1. A IA de áudio/transcrição não faz parte desta versão: o frontend envia somente o registro clínico já revisado pelo profissional.

## Executar localmente

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

O ambiente padrão usa SQLite em `database/database.sqlite`. O usuário inicial é `andre@fisio1.com.br` com senha `andre`; altere-o em qualquer ambiente não local.

## Contrato para o frontend

Base URL: `/api/v1`. Autentique em `POST /auth/login` e envie o token retornado como `Authorization: Bearer <token>`.

- `GET /dashboard`: indicadores e últimos quatro registros.
- `GET|POST|GET/{id}|PATCH/{id}|DELETE /patients`: pacientes; listagem aceita `search` e `per_page`.
- `GET|POST|GET/{id}|PATCH/{id}|DELETE /clinical-records`: avaliações/evoluções; listagem aceita `patient_id`, `type`, `search` e `per_page`.
- `GET /patients/{id}/history.pdf`: PDF do histórico clínico.
- `GET /attachments/{id}/download`: download autenticado de anexo.
- `GET /permissions`: catálogo de permissões que pode ser atribuído a grupos.
- `GET|POST|GET/{id}|PATCH/{id}|DELETE /groups`: grupos de acesso; `POST`/`PATCH` recebem `permission_ids`.
- `GET|POST|GET/{id}|PATCH/{id} /users`: gerenciamento de usuários; `POST`/`PATCH` recebem `access_group_ids`.
- `POST /auth/forgot-password` e `POST /auth/reset-password`: recuperação de senha pelo mecanismo nativo do Laravel.

Os valores de `type` são `initial_assessment` e `evolution`; o Angular pode apenas apresentá-los como “Avaliação inicial” e “Evolução”. Campos do registro seguem `snake_case`, incluindo `pain_level`, `functional_limitations`, `treatment_objective`, `physical_assessment` e `next_steps`. O login e `GET /auth/me` retornam os grupos e a lista achatada de `permissions`; o frontend deve usar essa lista para visibilidade de telas e ações, mas o backend continua sendo a autoridade.

Anexos devem ser enviados em `multipart/form-data` no campo `attachments[]`, com até 10 arquivos de 10 MB cada (PDF, DOC, DOCX, PNG e JPEG). Eles ficam em disco privado e nunca recebem URL pública.

## Qualidade e segurança

Use Sanctum para tokens, grupos e permissões granulares, desativação de usuário, validação de entrada, soft delete de pacientes e permissões de edição/remoção de prontuário por profissional responsável ou quem possuir `clinical_records.manage_all`. O seeder cria o grupo de sistema **Administrador**, atribui todas as permissões e vincula o usuário inicial a ele. Antes de produção, configure banco, mailer para recuperação de senha, `APP_URL`, CORS para a origem Angular e um storage persistente para anexos.
