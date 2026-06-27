# Manual de Uso — Sistema Financeiro

> **Versão:** 1.0
> **Data:** 2026-06-27
> **Sistema:** Gestão integrada de Contas a Pagar + Receber + Bancos

---

## 📖 Índice

1. [Visão Geral](#1-visão-geral)
2. [Acesso ao Sistema](#2-acesso-ao-sistema)
3. [Dashboard](#3-dashboard)
4. [Cadastros Base](#4-cadastros-base)
5. [Contas Bancárias](#5-contas-bancárias)
6. [Contas a Pagar](#6-contas-a-pagar)
7. [Contas a Receber](#7-contas-a-receber)
8. [Recorrências](#8-recorrências)
9. [Anexos PDF](#9-anexos-pdf)
10. [Relatórios](#10-relatórios)
11. [Gestão de Usuários e Empresas](#11-gestão-de-usuários-e-empresas)
12. [Perfis de Permissão](#12-perfis-de-permissão)
13. [FAQ](#13-faq)

---

## 1. Visão Geral

O **Sistema Financeiro** é uma plataforma web multi-empresa para gestão financeira completa. Permite:

- ✅ Cadastrar **empresas, usuários, fornecedores, clientes, categorias**
- ✅ **Contas a Pagar** com parcelamento, recorrência e fluxo de aprovação
- ✅ **Contas a Receber** (espelho das contas a pagar)
- ✅ **Contas Bancárias** com extrato consolidado e saldo em tempo real
- ✅ Integração automática: **pagar → saída na conta / receber → entrada na conta**
- ✅ Dashboard unificado com **saldo previsto** (bancário + a receber − a pagar)
- ✅ 6 tipos de relatórios com exportação CSV/PDF
- ✅ Multi-empresa com troca via dropdown no header

### 1.1 Stack Técnica

- **Backend:** PHP 8.2 + MariaDB 10.11 + PHP-FPM
- **Frontend:** HTML5 + CSS3 + Vanilla JS
- **Servidor:** Linux Debian 12 (192.168.70.45)
- **Geração de PDF:** wkhtmltopdf

### 1.2 Glossário

| Termo | Significado |
|---|---|
| **Conta a pagar** | Boleto/fatura que a empresa precisa pagar |
| **Conta a receber** | Valor que a empresa precisa receber de cliente |
| **Fornecedor** | Quem a empresa paga (locadora, fornecedor de energia, etc) |
| **Cliente** | Quem paga a empresa |
| **Conta bancária** | CC, poupança, caixa físico, cartão, investimento |
| **Movimentação** | Lançamento no extrato (entrada ou saída) |
| **Recorrência** | Conta que se repete todo mês (aluguel, internet, contador) |
| **Parcelamento** | Compra dividida em N vezes com vencimentos mensais |
| **Saldo previsto** | Saldo bancário + total a receber − total a pagar |

---

## 2. Acesso ao Sistema

### 2.1 URL

```
http://192.168.70.45/financeiro/
```

### 2.2 Primeiro Login

1. Abra a URL
2. Digite **e-mail** e **senha** (`senha123` no seed)
3. Clique **Entrar**

> ⚠️ **TROQUE A SENHA** no primeiro acesso (em Breve: tela de perfil).

### 2.3 Trocar de Empresa

Se você tem acesso a mais de uma empresa, use o **dropdown no canto superior direito**.

A troca é automática — todas as listas, dashboard e relatórios passam a mostrar dados da nova empresa.

### 2.4 Logout

Botão **Sair** no canto superior direito.

---

## 3. Dashboard

Ao logar, você vê o **Dashboard** com visão consolidada:

### 3.1 Saldos das Contas Bancárias
Cada conta aparece em um card individual com o saldo atual calculado em tempo real.

### 3.2 Cards de Contas a Pagar
- **Atrasadas** (vermelho)
- **Próx. 7 dias** (amarelo)
- **Total Pendente** (cinza)
- **Pago no Mês** (verde)

### 3.3 Cards de Contas a Receber
Mesma estrutura.

### 3.4 Saldo Previsto
```
Saldo Bancário + A Receber − A Pagar = Saldo Previsto
```

### 3.5 Últimas Movimentações
Lista as 10 últimas entradas/saídas com origem (manual / conta_pagar / conta_receber).

---

## 4. Cadastros Base

### 4.1 Empresas
- **Acesso:** Admin > Empresas
- Cadastrar Razão Social, CNPJ, endereço, etc.
- Status: ativa/inativa

### 4.2 Usuários
- **Acesso:** Admin > Usuários
- Nome, e-mail, senha
- **Perfil padrão** + **Vínculos por empresa** (cada empresa pode ter perfil diferente)

### 4.3 Categorias
- **Acesso:** Cadastros > Categorias
- Tipo: **despesa** (só pagar) / **receita** (só receber) / **ambos**
- Cor (para gráficos e badges)

### 4.4 Fornecedores
- **Acesso:** Cadastros > Fornecedores
- CNPJ, contato, endereço, observações

### 4.5 Clientes
- **Acesso:** Cadastros > Clientes
- Similar a fornecedores, mas para Contas a Receber
- Tipo: Física (CPF) ou Jurídica (CNPJ)

---

## 5. Contas Bancárias

### 5.1 Cadastrar
**Bancos > Contas Bancárias > + Nova Conta**

Campos:
- **Descrição:** Nome de exibição (ex: "Banco do Brasil - CC 12345-6")
- **Tipo:** conta_corrente, poupança, caixa_fisico, cartão, investimento
- **Banco, Agência, Conta, Dígito, Titular**
- **Saldo Inicial + Data de Referência** (a partir desta data as movimentações são consideradas)

### 5.2 Saldo em Tempo Real

O saldo é calculado dinamicamente:
```
saldo_atual = saldo_inicial
            + SUM(entradas na data >= data_saldo_inicial)
            - SUM(saídas na data >= data_saldo_inicial)
```

### 5.3 Extrato
**Bancos > [clique na conta] > Extrato**

Mostra todas as movimentações com:
- Data, descrição, tipo (entrada/saída)
- Origem: manual / conta_pagar / conta_receber / transferencia
- Usuário que lançou

Filtros: período, tipo, origem.

### 5.4 Lançamento Manual
Útil para tarifas bancárias, transferências entre contas, juros recebidos, etc.

**Origem = manual** → pode editar/excluir.
**Origem = automática** → somente leitura (gerada pelo sistema).

### 5.5 Movimentações Automáticas

| Ao pagar uma conta a pagar | → gera **saída** na conta bancária selecionada |
|---|---|
| Ao receber uma conta a receber | → gera **entrada** na conta bancária selecionada |
| Verificação de saldo | → sistema bloqueia pagamento se saldo insuficiente |

---

## 6. Contas a Pagar

### 6.1 CRUD Básico
**Contas > Contas a Pagar**

### 6.2 Criar Conta
Campos:
- Descrição, fornecedor, categoria
- Valor, data emissão, vencimento
- Parcelas (1 = à vista)
- Forma de pagamento
- Observações

### 6.3 Fluxo de Aprovação
```
PENDENTE → APROVADA → PAGA
                  ↓
              CANCELADA
```

- **Pendente:** recém-criada
- **Aprovada:** usuário com perfil `aprovador` ou `admin` aprovou
- **Paga:** usuário com perfil `pagador` ou `admin` registrou o pagamento (informa **conta bancária** + **data** + **valor**)
- **Cancelada:** conta cancelada (não pode mais ser paga)

### 6.4 Pagamento
1. Abra a conta a pagar
2. Clique em **💰 Pagar**
3. Informe:
   - Conta bancária (de onde saiu o dinheiro)
   - Data do pagamento
   - Valor pago (pode diferir do original em caso de juros/multa)
4. Sistema:
   - Verifica saldo disponível na conta
   - Cria movimentação de SAÍDA no extrato
   - Marca a conta como PAGA

### 6.5 Parcelamento
Ao criar uma conta com `parcelas > 1`:
- Cria 1 conta-pai + N filhas
- Cada parcela tem vencimento mensal (parcela 1 = data original, parcela 2 = +1 mês, etc.)
- Valor dividido (parcela 1 pode ter ajuste de centavos)

### 6.6 Recorrência
Ver seção 8.

### 6.7 Detalhes da Conta
Página completa com:
- Status atual
- Dados completos (fornecedor, categoria, datas)
- Histórico de aprovação/pagamento (quem/quando)
- Parcelas relacionadas (se aplicável)
- Anexos PDF

---

## 7. Contas a Receber

**Espelho das contas a pagar**, com:

- **Clientes** no lugar de fornecedores
- Status: **recebida** no lugar de **paga**
- Forma de recebimento: boleto/pix/transferência/dinheiro/cartão/cheque/depósito/outros
- Categoria tipo "receita" ou "ambos"

### 7.1 Recebimento
1. Abra a conta a receber
2. Clique em **💰 Receber**
3. Informe:
   - Conta bancária (onde entrou o dinheiro)
   - Data do recebimento
   - Valor recebido
4. Sistema gera **entrada** automática no extrato

---

## 8. Recorrências

Para despesas/receitas mensais fixas (aluguel, internet, contador, mensalidades).

### 8.1 Criar
**Contas > Recorrências > + Nova**

Campos:
- Descrição, fornecedor/cliente, categoria, valor
- **Dia de vencimento** (1-31)
- Data início, data fim (opcional)
- Forma de pagamento/recebimento

### 8.2 Geração
- Sistema calcula **próxima geração** = primeiro dia >= hoje com o dia de vencimento escolhido
- **Botão "⚙ Gerar Contas do Mês"** cria as contas correspondentes
- **Anti-duplicação:** verifica se já existe conta para a mesma descrição no mês, antes de criar

### 8.3 Sugestão de Automação
Criar cron job diário que chama `recorrencia_pagar_gerar.php` automaticamente (em breve).

---

## 9. Anexos PDF

Em uma conta a pagar ou receber, você pode anexar PDFs (notas fiscais, recibos, comprovantes).

**Validações:**
- Apenas PDF (MIME + magic bytes)
- Máximo 10MB
- Salvos em `/home/sistema/financeiro/uploads/`

**Download:** clique no nome do arquivo na página de detalhes.

---

## 10. Relatórios

**Menu Relatórios** → escolha um dos 6 tipos:

| Tipo | Descrição |
|---|---|
| **Por Período** | Todas as contas (pagar+receber) em intervalo de datas |
| **Por Categoria** | Totalizadores por categoria (Pagar + Receber) |
| **Por Fornecedor** | Ranking de fornecedores por volume de compras |
| **Por Cliente** | Ranking de clientes por volume de recebimentos |
| **Fluxo de Caixa** | Entradas/saídas por dia + saldo acumulado |
| **Atrasadas** | Vencidas e não pagas/recebidas, ordenadas por atraso |

**Exportação:** Botões 📥 CSV (BOM UTF-8 + separador `;`) e 📄 PDF (wkhtmltopdf).

---

## 11. Gestão de Usuários e Empresas

### 11.1 Criar Usuário
1. Admin > Usuários > + Novo
2. Informe nome, e-mail, senha, perfil padrão
3. **Para cada empresa:** selecione o perfil ou "Sem acesso"

### 11.2 Vincular/Desvincular
Na edição do usuário, altere o dropdown "Perfil na Empresa":
- "Sem acesso" = remove o vínculo
- "admin/operador/..." = ativa com esse perfil

---

## 12. Perfis de Permissão

| Capability | admin | aprovador | pagador | operador | visualizador |
|---|:-:|:-:|:-:|:-:|:-:|
| Ver dados | ✓ | ✓ | ✓ | ✓ | ✓ |
| Criar registros | ✓ | ✓ | ✓ | ✓ | — |
| Editar registros | ✓ | ✓ | ✓ | ✓ | — |
| Excluir | ✓ | — | — | — | — |
| Aprovar | ✓ | ✓ | — | — | — |
| **Pagar** (conta a pagar) | ✓ | — | ✓ | — | — |
| **Receber** (conta a receber) | ✓ | — | ✓ | — | — |
| Gerenciar cadastros | ✓ | ✓ | — | — | — |
| Gerenciar empresas | ✓ | — | — | — | — |
| Gerenciar usuários | ✓ | — | — | — | — |

---

## 13. FAQ

**P: O saldo da conta bancária pode ficar negativo?**
R: Sim (cheque especial), mas o sistema AVISA ao pagar uma conta se o saldo for insuficiente.

**P: Como excluo uma conta a pagar já paga?**
R: Não é possível. Crie uma conta de "estorno" com valor positivo, ou ajuste manualmente no banco de dados.

**P: Como funciona o parcelamento com valor ímpar (R$ 100,03 em 3x)?**
R: Sistema divide em 3 parcelas. Parcela 1 fica com 33,34 + 0,01 (ajuste), demais com 33,34. Total = R$ 100,03.

**P: Posso editar uma movimentação automática?**
R: Não. Movimentações geradas por pagamento/recebimento são imutáveis. Cancele a conta original e refaça se necessário.

**P: Como faço backup?**
R: Automático via cron (03:00 diário). Ver `/home/sistema/backups/financeiro/`.

**P: Onde fica o código-fonte?**
R: `/home/sistema/financeiro/` no servidor + workspace local em `C:\Users\joaob\.openclaw\workspace\financeiro\`.

**P: Como restauro um backup?**
R: `gunzip -c backup_file.sql.gz | mysql -u root -p financeiro`

---

**Dúvidas?** João Batista — Globalmt