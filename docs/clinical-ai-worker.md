# Processamento clínico assíncrono por IA

O áudio é processado fora da requisição HTTP pela fila `clinical-ai`:

1. a API cria a avaliação ou evolução com status `pending`;
2. o FFmpeg divide o arquivo em blocos de cinco minutos, preferencialmente sem recodificar;
3. cada bloco é enviado inline ao Gemini por um job independente;
4. sete workers permitem transcrever até sete blocos em paralelo;
5. falhas temporárias usam o modelo alternativo e depois o retry do próprio job;
6. as transcrições são consolidadas na ordem original;
7. uma chamada separada extrai os campos clínicos estruturados;
8. o registro passa para `in_review` e os arquivos temporários são removidos.

## Configuração recomendada

```dotenv
GEMINI_TRANSCRIPTION_MODEL=gemini-3.1-flash-lite
GEMINI_TRANSCRIPTION_FALLBACK_MODEL=gemini-3.5-flash-lite
GEMINI_EXTRACTION_MODEL=gemini-3.5-flash-lite
GEMINI_EXTRACTION_FALLBACK_MODEL=gemini-3.6-flash
GEMINI_REQUEST_TIMEOUT=60
GEMINI_TRANSCRIPTION_MAX_OUTPUT_TOKENS=4096
GEMINI_INLINE_MAX_BYTES=14680064
FFMPEG_BINARY=ffmpeg
FFMPEG_TIMEOUT=120
CLINICAL_AI_CHUNK_SECONDS=300
CLINICAL_AI_WORKER_PROCESSES=7
DB_QUEUE_RETRY_AFTER=240
```

`DB_QUEUE_RETRY_AFTER` precisa ser maior que o timeout dos jobs para impedir que um mesmo item seja reservado por dois workers.

## Pré-requisitos

```bash
ffmpeg -version
php artisan migrate --force
php artisan config:cache
```

## Supervisor

Copie `deploy/supervisor/fisio1-clinical-ai.conf.example` para a configuração do Supervisor, ajuste diretório e usuário e execute:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status fisio1-clinical-ai:*
```

O `numprocs=7` é o que torna os blocos paralelos. Um único `queue:work` continuará processando-os sequencialmente.

Após cada deploy:

```bash
php artisan queue:restart
```

Para desenvolvimento, abra sete workers equivalentes ou reduza temporariamente a quantidade de blocos. Um worker manual pode ser iniciado com:

```bash
php artisan queue:work database --queue=clinical-ai --sleep=1 --tries=4 --timeout=150
```

## Falhas e retomada

O log registra modelo, status HTTP, duração, tokens, tamanho do bloco e identificador da requisição, sem registrar o áudio ou a transcrição clínica.

Falhas definitivas ficam em `clinical_ai_processes` e `clinical_ai_chunks`. O endpoint `POST /api/v1/clinical-ai/processes/{id}/retry` reenfileira apenas os blocos ainda não concluídos. O áudio original e os blocos são apagados somente após a conclusão bem-sucedida.
