#!/usr/bin/env bash
#
# Deploy do "entregas" (gestao.hortadamaria.com).
# Corre no SERVIDOR, na pasta da aplicacao, DEPOIS de o Plesk puxar o codigo.
#
#   bash deploy.sh                # deploy completo (com backup da BD)
#   bash deploy.sh --sem-backup   # salta o backup (mais rapido, so em releases sem migracoes)
#   bash deploy.sh --sem-build    # salta o npm (so quando nao mexeste em vistas/CSS)
#
# Faz, por esta ordem: backup da BD -> modo manutencao -> composer -> npm build
# -> migracoes -> caches -> volta a ligar o site.

set -euo pipefail

cd "$(dirname "$0")"

VERDE='\033[0;32m'; AMARELO='\033[1;33m'; VERMELHO='\033[0;31m'; FIM='\033[0m'
passo()  { echo -e "\n${VERDE}==> $1${FIM}"; }
aviso()  { echo -e "${AMARELO}    $1${FIM}"; }
erro()   { echo -e "${VERMELHO}!! $1${FIM}"; }

COM_BACKUP=1
COM_BUILD=1

for arg in "$@"; do
    case "$arg" in
        --sem-backup) COM_BACKUP=0 ;;
        --sem-build)  COM_BUILD=0 ;;
        *) erro "Opcao desconhecida: $arg"; exit 1 ;;
    esac
done

# ── PHP: no Plesk o php do sistema pode ser antigo; usa o mais recente disponivel
PHP_BIN="${PHP_BIN:-}"

if [ -z "$PHP_BIN" ]; then
    for candidato in /opt/plesk/php/8.4/bin/php /opt/plesk/php/8.3/bin/php /opt/plesk/php/8.2/bin/php php; do
        if command -v "$candidato" >/dev/null 2>&1; then PHP_BIN="$candidato"; break; fi
    done
fi

[ -z "$PHP_BIN" ] && { erro "Nao encontrei o PHP. Define PHP_BIN=/caminho/para/php e volta a correr."; exit 1; }

echo "PHP: $PHP_BIN ($($PHP_BIN -r 'echo PHP_VERSION;'))"
[ -f artisan ] || { erro "Nao estou na pasta da aplicacao (nao vejo o artisan)."; exit 1; }
[ -f .env ] || { erro "Falta o ficheiro .env."; exit 1; }

# ── 1. Backup da base de dados ────────────────────────────────────────────
if [ "$COM_BACKUP" -eq 1 ]; then
    passo "1/7 Backup da base de dados"

    ler_env() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs || true; }

    DB_DATABASE=$(ler_env DB_DATABASE)
    DB_USERNAME=$(ler_env DB_USERNAME)
    DB_PASSWORD=$(ler_env DB_PASSWORD)
    DB_HOST=$(ler_env DB_HOST); DB_HOST=${DB_HOST:-127.0.0.1}

    if command -v mysqldump >/dev/null 2>&1 && [ -n "$DB_DATABASE" ]; then
        mkdir -p storage/backups
        FICHEIRO="storage/backups/$(date +%Y-%m-%d_%H%M%S)_${DB_DATABASE}.sql.gz"
        MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --quick \
            -h "$DB_HOST" -u "$DB_USERNAME" "$DB_DATABASE" | gzip > "$FICHEIRO"
        echo "    Guardado em $FICHEIRO ($(du -h "$FICHEIRO" | cut -f1))"
        ls -1t storage/backups/*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
        aviso "Mantidos os 10 backups mais recentes."
    else
        aviso "Sem mysqldump ou sem DB_DATABASE no .env — backup NAO feito."
        aviso "Se esta release tem migracoes de dados, para aqui e faz a copia a mao."
        read -r -p "    Continuar mesmo assim? [s/N] " resposta
        [ "${resposta,,}" = "s" ] || exit 1
    fi
else
    passo "1/7 Backup ignorado (--sem-backup)"
fi

# ── 2. Modo manutencao ────────────────────────────────────────────────────
passo "2/7 Modo manutencao"
$PHP_BIN artisan down --retry=60 2>/dev/null || aviso "Nao consegui ligar o modo manutencao; sigo na mesma."

repor_site() { $PHP_BIN artisan up >/dev/null 2>&1 || true; }
trap 'erro "Falhou. A repor o site."; repor_site' ERR

# ── 3. Dependencias PHP ───────────────────────────────────────────────────
passo "3/7 Dependencias PHP"
if command -v composer >/dev/null 2>&1; then
    $PHP_BIN "$(command -v composer)" install --no-dev --optimize-autoloader --no-interaction
else
    aviso "Composer nao encontrado — a saltar (a pasta vendor tem de vir no repositorio)."
fi

# ── 4. Assets (Tailwind/Vite) ─────────────────────────────────────────────
if [ "$COM_BUILD" -eq 1 ]; then
    passo "4/7 Build dos assets"
    if command -v npm >/dev/null 2>&1; then
        if [ -f package-lock.json ]; then npm ci --no-audit --no-fund; else npm install --no-audit --no-fund; fi
        npm run build
    else
        aviso "npm nao encontrado no servidor."
        aviso "IMPORTANTE: faz 'npm run build' no teu PC e envia a pasta public/build,"
        aviso "senao as paginas novas aparecem sem estilo."
    fi
else
    passo "4/7 Build ignorado (--sem-build)"
fi

# ── 5. Migracoes ──────────────────────────────────────────────────────────
passo "5/7 Migracoes"
$PHP_BIN artisan migrate --force

# ── 6. Caches ─────────────────────────────────────────────────────────────
passo "6/7 Caches"
$PHP_BIN artisan config:clear
$PHP_BIN artisan cache:clear || true
$PHP_BIN artisan view:clear
$PHP_BIN artisan route:clear   # sem route:cache: ha rotas com closures que nao serializam
$PHP_BIN artisan config:cache
$PHP_BIN artisan view:cache
[ -L public/storage ] || $PHP_BIN artisan storage:link || true

# ── 7. Site no ar ─────────────────────────────────────────────────────────
trap - ERR
passo "7/7 Site no ar"
repor_site

echo ""
echo -e "${VERDE}Deploy concluido.${FIM}"
echo ""
echo "A confirmar depois deste deploy:"
echo "  - Gestao > Fruta da epoca: definir o fruto do mes (e um registo na BD, nao vem no codigo)."
echo "  - Gestao > Definicoes Moloni: artigo dos portes (HM2222) e a serie da guia de transporte."
echo "  - Fichas das empresas: valor acordado por ciclo e custo de envio."
echo "  - Se correu a migracao do kiwi, confirmar as pecas numa empresa conhecida."
