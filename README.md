# Sistema Financeiro 💰

> Sistema integrado de **Contas a Pagar + Receber + Bancos** com gestão multi-empresa.
>
> **Versão:** 1.0 | **Data:** 2026-06-27

Sistema web em PHP 8.2 + MariaDB que unifica:

- 💸 **Contas a Pagar** — fluxo `pendente → aprovada → paga` com parcelamento e recorrência
- 💰 **Contas a Receber** — espelho das contas a pagar, para clientes
- 🏦 **Contas Bancárias** — cadastro de contas (corrente, poupança, caixa, cartão, investimento) com extrato consolidado
- 📊 **Dashboard unificado** — visão consolidada: saldo bancário + a receber − a pagar = saldo previsto
- 📈 **Relatórios** — 6 tipos com exportação CSV/PDF
- 🔐 **Multi-tenant** — múltiplas empresas, 5 perfis de permissão por empresa

---

## 🏗️ Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.2 (sem framework) |
| Banco | MariaDB 10.11 |
| Frontend | HTML5 + CSS3 + Vanilla JS |
| PDF | wkhtmltopdf |
| Servidor | Linux Debian 12 |

---

## 🚀 Acesso

- **URL:** `http://192.168.70.45/financeiro/`
- **Usuários seed:** `joao.batista@globalmt.com.br` / `senha123`
  - ⚠️ **Troque a senha no primeiro acesso**

---

## 📁 Estrutura

```
financeiro/
├── database/
│   ├── schema.sql            # 16 tabelas
│   ├── seed.sql              # 1 empresa + 5 usuários + dados exemplo
│   ├── gerar-hashes.php      # Auxiliar para gerar hashes bcrypt
│   └── backup_diario.sh      # Script de backup
├── docs/
│   ├── nginx-financeiro.conf
│   ├── php-fpm-financeiro.conf
│   └── MANUAL.md             # Manual de uso completo
├── public/                   # Document root do Nginx
│   ├── index.php             # Dashboard
│   ├── login.php
│   ├── logout.php
│   ├── conta_*.php           # CRUDs de Contas a Pagar/Receber
│   ├── conta_bancaria_*.php  # CRUDs de Contas Bancárias
│   ├── movimentacao_*.php    # Extrato/lançamentos
│   ├── recorrencia_*.php     # Templates recorrentes
│   ├── *.php                 # CRUDs cadastros
│   ├── relatorio_*.php       # Relatórios
│   ├── anexo_*.php           # Upload/download/excluir
│   ├── assets/               # CSS/JS
│   └── bootstrap.php         # Carrega libs + autoload + sessão
└── src/
    ├── config/
    │   └── database.php      # Configuração PDO
    ├── controllers/          # 14 controllers
    │   ├── DashboardController.php
    │   ├── Auth/             # (em lib/Auth.php)
    │   ├── ContasPagarController.php
    │   ├── ContasReceberController.php
    │   ├── ContasBancariasController.php
    │   ├── MovimentacoesController.php
    │   ├── RecorrenciaPagarController.php
    │   ├── RecorrenciaReceberController.php
    │   ├── RelatorioController.php
    │   ├── AnexoController.php
    │   ├── EmpresaController.php
    │   ├── UsuarioController.php
    │   ├── FornecedorController.php
    │   ├── ClientesController.php
    │   └── CategoriaController.php
    ├── lib/                  # Bibliotecas
    │   ├── Database.php
    │   ├── Auth.php
    │   ├── Permissao.php
    │   ├── Validator.php
    │   ├── Flash.php
    │   ├── CsvExporter.php
    │   ├── Uploader.php
    │   └── Helper.php
    └── views/                # Templates (separados por módulo)
        ├── auth/login.php
        ├── layout/           # header, navbar, footer
        ├── dashboard/index.php
        ├── contas_pagar/
        ├── contas_receber/
        ├── contas_bancarias/
        ├── movimentacoes/
        ├── recorrencias/
        ├── relatorios/
        ├── empresas/
        ├── usuarios/
        ├── fornecedores/
        ├── clientes/
        └── categorias/
```

---

## ⚙️ Instalação

### 1. Banco de dados

```bash
# Criar banco e usuário
sudo mysql -u root -p
```

```sql
CREATE DATABASE financeiro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'financeiro_app'@'localhost' IDENTIFIED BY '<senha-forte>';
GRANT ALL PRIVILEGES ON financeiro.* TO 'financeiro_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Schema + seed

```bash
cd /home/sistema/financeiro/database
mysql -u root -p financeiro < schema.sql
php gerar-hashes.php  # atualiza seed.sql com hashes reais
mysql -u root -p financeiro < seed.sql
```

### 3. Variáveis de ambiente PHP-FPM

Edite `/etc/php/8.2/fpm/pool.d/www.conf` (ou crie pool dedicado conforme `docs/php-fpm-financeiro.conf`):

```ini
env[DB_FIN_HOST] = 127.0.0.1
env[DB_FIN_PORT] = 3306
env[DB_FIN_DB]   = financeiro
env[DB_FIN_USER] = financeiro_app
env[DB_FIN_PASS] = <senha-forte>
```

### 4. Nginx

```bash
sudo cp docs/nginx-financeiro.conf /etc/nginx/snippets/financeiro.conf
# Adicione `include snippets/financeiro.conf;` no vhost principal
sudo nginx -t && sudo systemctl reload nginx
```

### 5. Permissões

```bash
sudo chown -R sistema:www-data /home/sistema/financeiro
sudo chmod -R 755 /home/sistema/financeiro
sudo chmod -R 775 /home/sistema/financeiro/uploads
```

### 6. Acesso

http://192.168.70.45/financeiro/

Login: `joao.batista@globalmt.com.br` / `senha123`

**⚠️ Troque a senha imediatamente!**

---

## 🔄 Workflow de mudança

1. Editar arquivo em `C:\Users\joaob\.openclaw\workspace\financeiro\` (workspace local)
2. Upload: `cd C:/tmp/sshtools && node upload-win.js "<windows>" /home/sistema/financeiro/<arquivo>`
3. PHP-FPM não precisa restart (auto-reload com `pm.ondemand`)
4. Testar: `cd C:/tmp/sshtools && node run.js "curl -sS http://127.0.0.1/financeiro/"`
5. Commit + push (deploy key SSH dedicada: `contas-pagar-financeiro`)

---

## 📊 Funcionalidades por módulo

### 💸 Contas a Pagar
- CRUD completo
- Status: pendente → aprovada → paga → cancelada
- Parcelamento (gera N filhas)
- Recorrência mensal (com anti-duplicação)
- Anexos PDF de notas fiscais
- 5 perfis de permissão (admin/aprovador/pagador/operador/visualizador)
- **Pagamento gera automaticamente saída na conta bancária**

### 💰 Contas a Receber
- Espelho das contas a pagar
- Status: pendente → aprovada → recebida → cancelada
- Parcelamento e recorrência
- Anexos PDF de recibos
- **Recebimento gera automaticamente entrada na conta bancária**

### 🏦 Contas Bancárias
- Tipos: conta corrente, poupança, caixa físico, cartão, investimento
- Saldo inicial + data de referência
- **Saldo calculado em tempo real** (soma movimentações >= data_saldo_inicial)
- Extrato com filtros (período, tipo, origem)
- Lançamentos manuais (tarifas, transferências, juros)
- Movimentações automáticas (vínculo com conta_pagar/conta_receber)
- Cálculo na hora do pagamento: verifica saldo disponível

### 📊 Dashboard
- Cards: a pagar (atrasadas, próx. 7 dias, total, pago no mês)
- Cards: a receber (idem)
- Saldos individuais por conta bancária
- Saldo consolidado
- **Saldo previsto** = saldo bancário + a receber − a pagar
- Últimas 10 movimentações

### 📈 Relatórios
1. Por período (todas as contas em intervalo de datas)
2. Por categoria (consolidado Pagar + Receber)
3. Por fornecedor (ranking)
4. Por cliente (ranking)
5. Fluxo de caixa (entradas/saídas por dia, com saldo acumulado)
6. Atrasadas (pagar + receber vencidas)

Exportação: **CSV** (BOM UTF-8 + `;` para Excel BR) ou **PDF** (wkhtmltopdf, orientação landscape).

---

## 🔐 Segurança

- Senhas com bcrypt custo 10
- Sessões PHP com cookies HttpOnly
- Validação de MIME + magic bytes em uploads
- Limite de 10MB por anexo
- SQL preparado em 100% das queries (PDO)
- CSRF: usar SameSite=Strict (a configurar)
- Permissões granulares por perfil + empresa

---

## 🔮 Próximos passos

- [ ] 2FA (TOTP)
- [ ] Conciliação bancária (importar OFX)
- [ ] API REST para integrações
- [ ] App mobile (PWA)
- [ ] Notificações por e-mail (contas a vencer)

---

## 📞 Suporte

João Batista — Globalmt
Servidor: 192.168.70.45
Repositório: https://github.com/joaobatista-globalmt/financeiro

## Backup Automático (banco `financeiro`)

Backup diário do banco via `mysqldump` + `gzip`, executado via cron às **2h da manhã**.

- **Script:** `scripts/backup_diario.sh` (mysqldump + gzip + rotação 30 dias)
- **Diretório:** `backups/diario/financeiro_YYYYMMDD_HHMMSS.sql.gz`
- **Log:** `backups/backup_diario.log` (criado pelo script)
- **Rotação:** mantém últimos 30 backups (1 mês); remove os mais antigos automaticamente
- **Cron:** `0 2 * * * /home/sistema/financeiro/scripts/backup_diario.sh >> /home/sistema/financeiro/backups/cron.log 2>&1`

### Como restaurar de um backup

```bash
# 1. Descompactar
gunzip -k backups/diario/financeiro_20260720_020000.sql.gz

# 2. Restaurar (CUIDADO: sobrescreve o banco atual!)
mysql --host=127.0.0.1 --user=financeiro_app --password=financeiro_app_2026 financeiro < backups/diario/financeiro_20260720_020000.sql

# 3. Recompactar
gzip backups/diario/financeiro_20260720_020000.sql
```

### Como testar manualmente

```bash
bash /home/sistema/financeiro/scripts/backup_diario.sh
cat /home/sistema/financeiro/backups/backup_diario.log
ls -la /home/sistema/financeiro/backups/diario/
```

### Como adicionar ao cron (caso precise refazer)

```bash
# Adiciona a linha ao crontab do usuario sistema
(crontab -l 2>/dev/null; echo "0 2 * * * /home/sistema/financeiro/scripts/backup_diario.sh >> /home/sistema/financeiro/backups/cron.log 2>&1") | crontab -
```
