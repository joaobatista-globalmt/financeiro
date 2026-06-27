#!/bin/bash
# ============================================================
# Backup diário: banco financeiro
# Localização: /home/sistema/financeiro/database/backup_diario.sh
# Cron: 03:00 diário
# ============================================================

set -e

DATA=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/sistema/backups/financeiro"
ARQUIVO="$BACKUP_DIR/financeiro_${DATA}.sql.gz"
RETER=14  # dias

mkdir -p "$BACKUP_DIR"

# Carga credenciais do .env (NÃO commitar)
ENV_FILE="/home/sistema/.financeiro_backup.env"
if [ -f "$ENV_FILE" ]; then
    source "$ENV_FILE"
else
    echo "[ERRO] Arquivo $ENV_FILE não encontrado."
    exit 1
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando backup do banco 'financeiro'..."

mysqldump \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --host=127.0.0.1 \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --quick \
    --lock-tables=false \
    financeiro | gzip > "$ARQUIVO"

TAMANHO=$(du -h "$ARQUIVO" | cut -f1)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backup concluído: $ARQUIVO ($TAMANHO)"

# Limpar backups antigos
find "$BACKUP_DIR" -name "financeiro_*.sql.gz" -mtime +$RETER -delete
RESTANTES=$(ls "$BACKUP_DIR" | wc -l)
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Backups restantes: $RESTANTES (retidos últimos $RETER dias)"