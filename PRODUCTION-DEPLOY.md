# Deploy em producao

Guia rapido para publicar o projeto em `gestao.hortadamaria.com` num servidor Plesk.

## 1. Antes de fazer deploy

No servidor, entrar na pasta do site:

```bash
cd /var/www/vhosts/gestao.hortadamaria.com/httpdocs
```

Confirmar o utilizador correto da subscricao Plesk:

```bash
OWNER=$(stat -c '%U' .)
echo "Owner do site: $OWNER"
```

Se o deploy falhar com erros como `unable to unlink old ... Permission denied`, corrigir o dono e permissoes antes de voltar a enviar ficheiros:

```bash
chattr -R -i app bootstrap config database resources routes 2>/dev/null || true
chown -R "$OWNER":psacln app bootstrap config database resources routes composer.json composer.lock package.json package-lock.json artisan vite.config.js
find app bootstrap config database resources routes -type d -exec chmod 755 {} \;
find app bootstrap config database resources routes -type f -exec chmod 644 {} \;
```

## 2. Deploy dos ficheiros

Enviar os ficheiros do projeto para:

```text
/var/www/vhosts/gestao.hortadamaria.com/httpdocs
```

Depois do envio, executar:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

Se houver frontend compilado no servidor:

```bash
npm ci
npm run build
```

Se o `public/build` ja for enviado do ambiente local, este passo de Node pode ser dispensado.

## 3. Permissoes obrigatorias para uploads

Mesmo com as fotos a serem servidas por rota Laravel, o servidor continua a precisar de escrita em `storage` e `bootstrap/cache`.

```bash
chown -R "$OWNER":psacln storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Confirmar que o PHP consegue criar ficheiros temporarios. No Plesk, verificar tambem:

```text
upload_max_filesize = 20M
post_max_size = 50M
max_file_uploads = 20
```

O projeto inclui estes limites em `public/.user.ini` e `public/.htaccess`, mas o Plesk pode sobrepor valores na configuracao PHP do dominio.

## 4. Fotos e erro 403

O erro 403 ao abrir fotos em `/storage/...` normalmente vem do Plesk bloquear o symlink ou permissoes do diretoria publico.

Este projeto evita depender desse acesso direto:

- Fotos da app: `GET /ficheiros/{path}` com utilizador autenticado.
- Fotos enviadas para IA/Ollama: `GET /ai-job-images/{job}` com URL assinada temporaria.

Por isso, depois do deploy e limpeza de cache, as views devem gerar links por estas rotas e nao por `/storage/...`.

Validar no servidor:

```bash
php artisan route:list --name=public-files
php artisan route:list --name=ai-jobs
```

Devem aparecer:

```text
public-files.show
ai-jobs.image
```

## 5. Variaveis `.env` necessarias

Confirmar no `.env` de producao:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://gestao.hortadamaria.com
FILESYSTEM_DISK=local
NAS_AI_API_KEY=colocar_a_mesma_chave_usada_no_LXC_101
```

Nao colocar chaves reais no repositorio.

## 6. API da IA local no LXC 101

O servidor publico apenas expoe jobs pendentes. O LXC 101 e que faz pedidos de saida para a internet:

```text
GET  https://gestao.hortadamaria.com/api/ai/pending-jobs
POST https://gestao.hortadamaria.com/api/ai/job-result
Header: X-API-Key: valor_de_NAS_AI_API_KEY
```

Nao e necessario abrir portas do NAS/LXC para o exterior.

## 7. Cronjobs e processos obrigatorios

### Scheduler Laravel

No Plesk/cron do servidor publico deve existir um cron a correr todos os minutos:

```bash
cd /var/www/vhosts/gestao.hortadamaria.com/httpdocs && php artisan schedule:run >> /dev/null 2>&1
```

Dentro da app, o scheduler corre:

```text
orders:sync a cada 15 minutos
queue:work a cada minuto para processar faturas OCR
site:health-check a cada 5 minutos
site:backup todos os dias as 02:15
site:security-scan todas as segundas as 03:30
site:update-check todas as segundas as 04:00
```

Validar:

```bash
php artisan schedule:list
```

### Queue das faturas OCR

As faturas OCR usam jobs Laravel (`ProcessInvoiceUpload`). Se a queue nao estiver a correr, o upload fica criado mas a extracao nao avanca.

Agora isto fica definido dentro do site em `routes/console.php`:

```bash
php artisan queue:work --stop-when-empty --tries=2 --timeout=180
```

Validar:

```bash
php artisan queue:failed
```

### IA/Ollama no LXC 101

No LXC 101 deve existir um cron a cada minuto para procurar jobs pendentes no servidor publico, processar com Ollama/LLaVA e devolver o resultado:

```text
* * * * * script_do_lxc_101_que_chama_pending_jobs_e_job_result
```

Validar no servidor publico:

```bash
php artisan route:list --path=api/ai
```

Validar no LXC 101:

```bash
curl -H "X-API-Key: $NAS_AI_API_KEY" https://gestao.hortadamaria.com/api/ai/pending-jobs
```

### Backups para NAS a partir do site

O backup fica definido dentro da app pelo comando:

```bash
php artisan site:backup
```

Configurar no `.env` de producao:

```dotenv
OPERATIONS_BACKUP_PATH=/var/www/vhosts/gestao.hortadamaria.com/httpdocs/storage/app/backups
OPERATIONS_BACKUP_KEEP_DAYS=14
OPERATIONS_NAS_RSYNC_TARGET=utilizador@IP_DO_NAS:/volume1/backups/gestao/
```

O comando cria backup da base de dados, `.env`, locks, `storage/app/public` e `public/build`. Se `OPERATIONS_NAS_RSYNC_TARGET` estiver preenchido, envia para o NAS por `rsync`.

### Scans de seguranca

Os scans ficam definidos dentro da app:

```bash
php artisan site:security-scan
php artisan site:update-check
```

Resultados:

```text
storage/app/operations/security-scan.json
storage/app/operations/updates-scan.json
```

### Validacao se o site esta online

O healthcheck fica definido dentro da app:

```bash
php artisan site:health-check
```

Configurar no `.env`:

```dotenv
OPERATIONS_HEALTH_URL=https://gestao.hortadamaria.com/up
```

Resultado:

```text
storage/app/operations/health.json
```

### Binarios necessarios para OCR

Para faturas PDF/imagem, confirmar no servidor:

```bash
which tesseract
which pdftotext
which ocrmypdf
which convert
```

Se algum faltar, a extracao OCR pode falhar ou ficar incompleta.

## 8. Checklist depois do deploy

```bash
php artisan about
php artisan migrate:status
php artisan route:list --name=public-files
php artisan route:list --name=ai-jobs
php artisan schedule:list
php artisan queue:failed
```

Testar no browser:

1. Fazer login.
2. Abrir uma entrega com fotos.
3. Tirar ou escolher uma foto.
4. Guardar.
5. Abrir a foto pela propria pagina.

Se ainda aparecer `fotos.0 failed to upload`, o problema ja nao e o link da foto: verificar limites PHP, espaco em disco, permissoes de `storage`, permissoes do temporario PHP e logs do Laravel em `storage/logs/laravel.log`.
