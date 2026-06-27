#!/bin/bash
# Backup completo do checkpoint - Sistema Financeiro 2026-06-27
set -e

BACKUP=/home/sistema/backups/checkpoint-2026-06-27
mkdir -p "$BACKUP"

echo "=== 1. Backup do codigo-fonte ==="
tar -czf "$BACKUP/financeiro-codigo.tar.gz" \
    --exclude='uploads/*' \
    --exclude='node_modules' \
    --exclude='.git/objects/pack/*.pack' \
    /home/sistema/financeiro/

echo "=== 2. Backup do banco (dump SQL) ==="
mysqldump --user=financeiro_app --password=financeiro_app_2026 --host=127.0.0.1 \
    --single-transaction --routines --triggers --events \
    --quick --lock-tables=false \
    financeiro | gzip > "$BACKUP/banco-financeiro.sql.gz"

echo "=== 3. Backup dos configs ==="
cp /etc/nginx/snippets/financeiro.conf "$BACKUP/nginx-financeiro.conf"
cp /etc/php/8.2/fpm/pool.d/www.conf "$BACKUP/php-fpm-www.conf"

echo "=== 4. Backup do crontab ==="
crontab -l > "$BACKUP/crontab.txt" 2>/dev/null || true

echo "=== 5. Backup dos scripts de backup ==="
cp /usr/local/bin/backup-diario-financeiro "$BACKUP/backup-diario-financeiro.sh"

echo "=== 6. Gerando manifesto ==="
cat > "$BACKUP/CHECKPOINT.md" <<'EOF'
# Checkpoint Sistema Financeiro - 27/06/2026

## Localização
- Servidor: 192.168.70.45 (Debian 12)
- Banco: financeiro (MariaDB 10.11)
- Aplicacao: /home/sistema/financeiro/
- URL: http://192.168.70.45/financeiro/

## Conteúdo do checkpoint

EOF
ls -lh "$BACKUP/" >> "$BACKUP/CHECKPOINT.md"

cat >> "$BACKUP/CHECKPOINT.md" <<'EOF'

## Tags Git

EOF
(cd /home/sistema/financeiro && sudo -u sistema git tag -l) >> "$BACKUP/CHECKPOINT.md" 2>/dev/null

cat >> "$BACKUP/CHECKPOINT.md" <<'EOF'

## Estatísticas do banco

EOF
mysql -u financeiro_app -pfinanceiro_app_2026 -h 127.0.0.1 -D financeiro -e "
SELECT 'empresas' AS tabela, COUNT(*) AS total FROM empresas
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'fornecedores', COUNT(*) FROM fornecedores
UNION ALL SELECT 'clientes', COUNT(*) FROM clientes
UNION ALL SELECT 'categorias', COUNT(*) FROM categorias
UNION ALL SELECT 'contas_pagar', COUNT(*) FROM contas_pagar
UNION ALL SELECT 'contas_receber', COUNT(*) FROM contas_receber
UNION ALL SELECT 'contas_bancarias', COUNT(*) FROM contas_bancarias
UNION ALL SELECT 'movimentacoes_bancarias', COUNT(*) FROM movimentacoes_bancarias
UNION ALL SELECT 'usuarios_empresas', COUNT(*) FROM usuarios_empresas;
" >> "$BACKUP/CHECKPOINT.md"

cat >> "$BACKUP/CHECKPOINT.md" <<'EOF'

## Resumo do que foi feito

- Sistema Financeiro v1.0 do zero (Pagar + Receber + Bancos integrado)
- 117 arquivos PHP, 16 tabelas, 5 controllers, 31 views
- Multi-tenant com 3 empresas + 5 usuários
- Login funcionando (joao.batista usa d06m06)
- Importados 78 clientes + 109 contas a receber do TopsApp CSV
- Backup diario automatizado (cron 03:30)
- Tags: v1.0.0 a v1.0.4

## Credenciais

| E-mail | Senha | Perfil |
|---|---|---|
| joao.batista@globalmt.com.br | d06m06 | admin |
| eduardo@globalmt.com.br | senha123 | admin |
| thainara@globalmt.com.br | senha123 | admin |
| carlos@globalmt.com.br | senha123 | admin |
| maria@globalmt.com.br | senha123 | visualizador |
EOF

echo ""
echo "=== CHECKPOINT CRIADO ==="
ls -lh "$BACKUP/"
echo ""
echo "Tamanho total:"
du -sh "$BACKUP/"