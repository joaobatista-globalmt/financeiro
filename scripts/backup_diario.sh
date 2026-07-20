#!/bin/bash
#
# backup_diario.sh - Backup diario do banco `financeiro`
#
# Roda via cron todo dia as 2h da manha (0 2 * * *).
# Mantem os ultimos 30 backups (1 mes).
#
# Credenciais vem do /etc/php/8.2/fpm/pool.d/www.conf (env vars do PHP-FPM).
# Aqui usamos as mesmas credenciais do app PHP.
#
set -e

# --- CONFIG ---
DB_NAME="financeiro"
DB_USER="financeiro_app"
DB_PASS="financeiro_app_2026"
DB_HOST="127.0.0.1"
BACKUP_DIR="/home/sistema/financeiro/backups/diario"
LOG_FILE="/home/sistema/financeiro/backups/backup_diario.log"
RETENTION_DAYS=30

# --- TIMESTAMP ---
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/financeiro_${TIMESTAMP}.sql.gz"

# --- FUNCAO LOG ---
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# --- INIT ---
log "=== INICIO backup_diario.sh ==="
mkdir -p "$BACKUP_DIR"

# --- DUMP + GZIP ---
log "mysqldump $DB_NAME -> $BACKUP_FILE"
if mysqldump \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --quick \
    --lock-tables=false \
    --routines \
    --triggers \
    --events \
    --default-character-set=utf8mb4 \
    "$DB_NAME" 2>> "$LOG_FILE" | gzip > "$BACKUP_FILE"; then

    FILE_SIZE=$(stat -c%s "$BACKUP_FILE" 2>/dev/null || echo 0)
    FILE_SIZE_MB=$(awk "BEGIN {printf \"%.2f\", $FILE_SIZE/1024/1024}")
    log "OK: backup criado ($FILE_SIZE bytes / ${FILE_SIZE_MB} MB)"
else
    log "ERRO: mysqldump falhou (veja logs acima)"
    [ -f "$BACKUP_FILE" ] && rm -f "$BACKUP_FILE"
    exit 1
fi

# --- VALIDACAO BASICA ---
if gzip -t "$BACKUP_FILE" 2>> "$LOG_FILE"; then
    log "OK: gzip -t (integridade verificada)"
else
    log "ERRO: gzip -t falhou - arquivo corrompido"
    exit 1
fi

# --- ROTACAO ---
DELETED=$(find "$BACKUP_DIR" -name "financeiro_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete -print 2>/dev/null | wc -l)
log "Rotacao: $DELETED backup(s) antigo(s) removido(s) (>$RETENTION_DAYS dias)"

# --- RESUMO ---
TOTAL=$(find "$BACKUP_DIR" -name "financeiro_*.sql.gz" -type f 2>/dev/null | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" 2>/dev/null | awk '{print $1}')
log "Total de backups: $TOTAL arquivos ($TOTAL_SIZE)"

log "=== FIM backup_diario.sh (OK) ==="
exit 0
