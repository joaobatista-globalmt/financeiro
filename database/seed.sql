-- ============================================================
-- Seed: financeiro (MariaDB 10.11)
-- Data: 2026-06-27
-- Versao: 1.0
-- ============================================================
-- Dados de exemplo: 1 empresa + 5 usuários + 5 categorias +
-- 2 contas bancárias + 5 fornecedores + 3 clientes + 20 contas a pagar
-- Senha de todos os usuários: senha123 (deve ser trocada após primeiro login!)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE log_operacoes;
TRUNCATE TABLE anexos;
TRUNCATE TABLE contas_receber_recorrencia;
TRUNCATE TABLE contas_receber_parcelas;
TRUNCATE TABLE contas_receber;
TRUNCATE TABLE contas_pagar_recorrencia;
TRUNCATE TABLE contas_pagar_parcelas;
TRUNCATE TABLE contas_pagar;
TRUNCATE TABLE movimentacoes_bancarias;
TRUNCATE TABLE contas_bancarias;
TRUNCATE TABLE categorias;
TRUNCATE TABLE clientes;
TRUNCATE TABLE fornecedores;
TRUNCATE TABLE usuarios_empresas;
TRUNCATE TABLE usuarios;
TRUNCATE TABLE empresas;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- EMPRESA
-- ============================================================
INSERT INTO empresas (id, razao_social, nome_fantasia, cnpj, inscricao_estadual, endereco, cidade, uf, cep, telefone, email, ativo)
VALUES
(1, 'Globalmt Comercio e Servicos Ltda', 'Globalmt', '11.222.333/0001-44', '123.456.789.012',
 'Av. Principal, 1000 - Centro', 'Cuiaba', 'MT', '78000-000', '(65) 3333-4444', 'contato@globalmt.com.br', 1);

-- ============================================================
-- USUÁRIOS (senha: senha123)
-- ============================================================
-- Bcrypt hash de "senha123" com cost 10
INSERT INTO usuarios (id, nome, email, senha_hash, perfil_padrao, ativo) VALUES
(1, 'Joao Batista',       'joao.batista@globalmt.com.br', '$2y$10$WPPapbGgcg0wKKOTENjGROQT7HE8YlHBhxxbKmVZjxuJArsryDRNi', 'admin', 1),
(2, 'Eduardo Silva',      'eduardo@globalmt.com.br',     '$2y$10$WPPapbGgcg0wKKOTENjGROQT7HE8YlHBhxxbKmVZjxuJArsryDRNi', 'operador', 1),
(3, 'Thainara Oliveira',  'thainara@globalmt.com.br',    '$2y$10$WPPapbGgcg0wKKOTENjGROQT7HE8YlHBhxxbKmVZjxuJArsryDRNi', 'aprovador', 1),
(4, 'Carlos Mendes',      'carlos@globalmt.com.br',      '$2y$10$WPPapbGgcg0wKKOTENjGROQT7HE8YlHBhxxbKmVZjxuJArsryDRNi', 'pagador', 1),
(5, 'Maria Souza',        'maria@globalmt.com.br',       '$2y$10$WPPapbGgcg0wKKOTENjGROQT7HE8YlHBhxxbKmVZjxuJArsryDRNi', 'visualizador', 1);

-- NOTA: Os hashes acima são placeholders. Devem ser regenerados via PHP:
--   php -r "echo password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 10]), PHP_EOL;"
-- Execute o script auxiliar em database/gerar-hashes.php para gerar hashes reais.

INSERT INTO usuarios_empresas (usuario_id, empresa_id, perfil_na_empresa, ativo) VALUES
(1, 1, 'admin', 1),
(2, 1, 'operador', 1),
(3, 1, 'aprovador', 1),
(4, 1, 'pagador', 1),
(5, 1, 'visualizador', 1);

-- ============================================================
-- CATEGORIAS
-- ============================================================
INSERT INTO categorias (empresa_id, nome, tipo, cor, descricao, ativo) VALUES
(1, 'Aluguel',          'despesa', '#dc2626', 'Aluguel de imóvel comercial', 1),
(1, 'Energia',          'despesa', '#f59e0b', 'Conta de energia elétrica', 1),
(1, 'Internet',         'despesa', '#3b82f6', 'Internet e telefonia', 1),
(1, 'Material de Escritório', 'despesa', '#8b5cf6', 'Papel, caneta, etc', 1),
(1, 'Salários',         'despesa', '#ef4444', 'Folha de pagamento', 1),
(1, 'Vendas',           'receita', '#16a34a', 'Receita com vendas', 1),
(1, 'Serviços',         'receita', '#0891b2', 'Receita com prestação de serviços', 1),
(1, 'Outros',           'ambos',   '#6b7280', 'Diversos', 1);

-- ============================================================
-- CONTAS BANCÁRIAS
-- ============================================================
INSERT INTO contas_bancarias (empresa_id, descricao, tipo, banco, agencia, numero_conta, digito, titular, cpf_cnpj_titular, saldo_inicial, data_saldo_inicial, ativo) VALUES
(1, 'Banco do Brasil - CC',  'conta_corrente', 'Banco do Brasil', '1234-5', '67890', '1', 'Globalmt Comercio e Servicos Ltda', '11.222.333/0001-44', 25000.00, '2026-01-01', 1),
(1, 'Itaú - Poupança',       'poupanca',       'Itaú',            '9876',   '12345', '6', 'Globalmt Comercio e Servicos Ltda', '11.222.333/0001-44', 10000.00, '2026-01-01', 1),
(1, 'Caixa Física',          'caixa_fisico',   NULL,              NULL,     NULL,    NULL, 'Caixa escritório',                    NULL,                   2000.00, '2026-01-01', 1);

-- ============================================================
-- FORNECEDORES
-- ============================================================
INSERT INTO fornecedores (empresa_id, razao_social, nome_fantasia, cnpj, telefone, email, contato, ativo) VALUES
(1, 'Imobiliária Central Ltda',    'Imobiliária Central',    '01.234.567/0001-11', '(65) 3333-1111', 'contato@imobcentral.com.br', 'Roberto', 1),
(1, 'Energisa Mato Grosso',        'Energisa',               '02.345.678/0001-22', '(65) 3333-2222', 'cliente@energisa.com.br',    'Atendimento', 1),
(1, 'Vivo Fibra',                  'Vivo',                   '03.456.789/0001-33', '0800-123-4567', 'empresas@vivo.com.br',       'Atendimento', 1),
(1, 'Kalunga Comercio',            'Kalunga',                '04.567.890/0001-44', '(65) 3333-4444', 'kalunga@kalunga.com.br',     'Atendimento', 1),
(1, 'Contábil Soma Ltda',          'Contábil Soma',          '05.678.901/0001-55', '(65) 3333-5555', 'contato@contabsoma.com.br',  'Sandra', 1);

-- ============================================================
-- CLIENTES
-- ============================================================
INSERT INTO clientes (empresa_id, razao_social, nome_fantasia, cpf_cnpj, tipo_pessoa, telefone, email, contato, ativo) VALUES
(1, 'Indústria Norte S/A',         'Indústria Norte',        '10.111.222/0001-11', 'J', '(65) 3555-1111', 'compras@indnorte.com.br',  'Patricia', 1),
(1, 'Comércio Varejista Sul Ltda', 'Varejista Sul',          '11.222.333/0001-22', 'J', '(65) 3555-2222', 'financeiro@varejosul.com.br', 'Felipe', 1),
(1, 'Marcos Antônio da Silva',     'Marcos Silva',           '123.456.789-00',     'F', '(65) 99999-1111', 'marcos@email.com',         'Marcos', 1);

-- ============================================================
-- CONTAS A PAGAR (exemplos variados)
-- ============================================================
INSERT INTO contas_pagar (empresa_id, fornecedor_id, categoria_id, descricao, numero_documento, valor, data_emissao, data_vencimento, forma_pagamento, status, parcelas, parcela_atual, usuario_criacao_id) VALUES
(1, 1, 1, 'Aluguel junho/2026',     'ALU-2026-06', 3500.00, '2026-06-01', '2026-06-10', 'boleto', 'aprovada', 1, 1, 1),
(1, 2, 2, 'Energia maio/2026',      'ENG-2026-05', 1245.67, '2026-05-15', '2026-06-15', 'boleto', 'pendente', 1, 1, 1),
(1, 3, 3, 'Internet mensal',         'VIVO-2026-06', 199.90, '2026-06-01', '2026-06-20', 'boleto', 'aprovada', 1, 1, 1),
(1, 4, 4, 'Material escritório',     'NF-45678',     567.80, '2026-06-10', '2026-06-25', 'pix',    'pendente', 1, 1, 1),
(1, 5, 5, 'Honorários contábeis',    'CONT-2026-06', 1500.00, '2026-06-01', '2026-06-30', 'transferencia', 'aprovada', 1, 1, 1);

-- Uma conta PAGA (exemplo de como fica após pagamento)
INSERT INTO contas_pagar (empresa_id, fornecedor_id, categoria_id, descricao, numero_documento, valor, data_emissao, data_vencimento, data_pagamento, valor_pago, forma_pagamento, conta_bancaria_id, status, parcelas, parcela_atual, usuario_criacao_id, usuario_aprovacao_id, usuario_pagamento_id, data_aprovacao)
VALUES
(1, 1, 1, 'Aluguel maio/2026',      'ALU-2026-05', 3500.00, '2026-05-01', '2026-05-10', '2026-05-09', 3500.00, 'transferencia', 1, 'paga', 1, 1, 1, 3, 4, '2026-05-02');

-- ============================================================
-- CONTAS A RECEBER
-- ============================================================
INSERT INTO contas_receber (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor, data_emissao, data_vencimento, forma_recebimento, status, parcelas, parcela_atual, usuario_criacao_id) VALUES
(1, 1, 6, 'Venda pedido #1234',     'PED-1234',    8500.00, '2026-06-01', '2026-06-20', 'boleto', 'aprovada', 1, 1, 1),
(1, 2, 7, 'Consultoria TI',          'CONS-2026-06', 4500.00, '2026-06-05', '2026-06-30', 'transferencia', 'pendente', 1, 1, 1),
(1, 3, 6, 'Venda balcão',            'NF-7890',      250.00, '2026-06-15', '2026-06-25', 'pix', 'aprovada', 1, 1, 1);

-- Uma conta RECEBIDA
INSERT INTO contas_receber (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor, data_emissao, data_vencimento, data_recebimento, valor_recebido, forma_recebimento, conta_bancaria_id, status, parcelas, parcela_atual, usuario_criacao_id, usuario_aprovacao_id, usuario_recebimento_id, data_aprovacao)
VALUES
(1, 1, 6, 'Venda pedido #1100',     'PED-1100',    12000.00, '2026-05-10', '2026-05-25', '2026-05-24', 12000.00, 'transferencia', 1, 'recebida', 1, 1, 1, 3, 4, '2026-05-12');

-- ============================================================
-- MOVIMENTAÇÕES BANCÁRIAS (reflexo dos pagamentos/recebimentos acima)
-- ============================================================
INSERT INTO movimentacoes_bancarias (empresa_id, conta_bancaria_id, data_movimento, tipo, origem, valor, descricao, conta_pagar_id, conta_receber_id, usuario_id) VALUES
(1, 1, '2026-05-09', 'saida',   'conta_pagar',   3500.00, 'Pagamento: Aluguel maio/2026', 6, NULL, 4),
(1, 1, '2026-05-24', 'entrada', 'conta_receber', 12000.00, 'Recebimento: Venda pedido #1100', NULL, 4, 4);

-- Movimentação manual de exemplo (tarifa bancária)
INSERT INTO movimentacoes_bancarias (empresa_id, conta_bancaria_id, data_movimento, tipo, origem, valor, descricao, usuario_id) VALUES
(1, 1, '2026-05-31', 'saida', 'manual', 25.90, 'Tarifa bancária mensal', 4);

-- ============================================================
-- FIM DO SEED
-- ============================================================
--
-- DEPOIS DE EXECUTAR ESTE SEED:
-- 1. Execute: php database/gerar-hashes.php
--    (isso vai substituir os hashes placeholder pelos reais)
-- 2. Faça login com qualquer usuário e senha 'senha123'
-- 3. TROQUE TODAS AS SENHAS no primeiro acesso
--
-- ============================================================