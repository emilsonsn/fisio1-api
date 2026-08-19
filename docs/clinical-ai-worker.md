# Worker de processamento clínico por IA

O servidor precisa ter o FFmpeg disponível e um worker dedicado à fila clinical-ai.

## Pré-requisitos

    ffmpeg -version
    php artisan migrate --force
    php artisan config:cache

Se ffmpeg -version falhar, instale ou corrija as bibliotecas do pacote antes de iniciar o worker. O caminho pode ser configurado em FFMPEG_BINARY.

## Worker

Use Supervisor, systemd ou outro gerenciador de processos para manter este comando ativo:

    php artisan queue:work database --queue=clinical-ai,default --sleep=2 --tries=4 --timeout=900 --max-time=3600

Após cada deploy:

    php artisan queue:restart

O DB_QUEUE_RETRY_AFTER deve permanecer maior que o timeout; o valor recomendado é 1200.

## Retomada

Falhas definitivas ficam registradas em clinical_ai_processes e clinical_ai_chunks. O endpoint POST /api/v1/clinical-ai/processes/{id}/retry reenfileira somente os blocos ainda não concluídos. O áudio original e os blocos são apagados depois da conclusão bem-sucedida.
