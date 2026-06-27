
## Checkpoint 2026-06-27 — Sistema Financeiro completo

**Local:** /home/sistema/backups/checkpoint-2026-06-27/
**Tamanho:** 304KB em 7 arquivos

### Conteúdo
- banco-financeiro.sql.gz (15K) — dump completo do banco inanceiro
- financeiro-codigo.tar.gz (243K) — código-fonte completo sem uploads/git pack
- nginx-financeiro.conf (799) — config do vhost
- php-fpm-www.conf (22K) — config PHP-FPM com env vars DB_FIN_*
- backup-diario-financeiro.sh (1.1K) — script de backup
- crontab.txt (462) — agendamentos
- CHECKPOINT.md (1.7K) — manifesto com estatísticas

### Estatísticas do banco no checkpoint
- 3 empresas
- 5 usuarios
- 7 vínculos usuario-empresa
- 35 fornecedores
- 78 clientes
- 22 categorias
- 40 contas_pagar
- 109 contas_receber
- 3 contas_bancarias
- 0 movimentacoes (a serem criadas conforme uso)

### Como restaurar
`ash
# 1. Restaurar codigo
cd /home/sistema/
tar -xzf /home/sistema/backups/checkpoint-2026-06-27/financeiro-codigo.tar.gz

# 2. Restaurar banco
gunzip -c /home/sistema/backups/checkpoint-2026-06-27/banco-financeiro.sql.gz | \\
    mysql -u root -p financeiro

# 3. Restaurar configs
sudo cp /home/sistema/backups/checkpoint-2026-06-27/nginx-financeiro.conf \\
    /etc/nginx/snippets/financeiro.conf
sudo cp /home/sistema/backups/checkpoint-2026-06-27/php-fpm-www.conf \\
    /etc/php/8.2/fpm/pool.d/www.conf
sudo systemctl reload nginx php8.2-fpm
`
