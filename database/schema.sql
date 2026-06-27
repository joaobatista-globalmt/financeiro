-- ============================================================
-- Schema: financeiro (MariaDB 10.11)
-- Data: 2026-06-27
-- Versao: 1.0
-- ============================================================
-- Sistema financeiro integrado: Contas a Pagar + Receber + Banco
-- Multi-tenant via empresa_id em todas as tabelas filhas
-- Baseado no schema original de contas_pagar (2026-06-26)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS log_operacoes;
DROP TABLE IF EXISTS anexos;
DROP TABLE IF EXISTS contas_receber_recorrencia;
DROP TABLE IF EXISTS contas_receber_parcelas;
DROP TABLE IF EXISTS contas_receber;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS contas_pagar_recorrencia;
DROP TABLE IF EXISTS contas_pagar_parcelas;
DROP TABLE IF EXISTS contas_pagar;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS fornecedores;
DROP TABLE IF EXISTS movimentacoes_bancarias;
DROP TABLE IF EXISTS contas_bancarias;
DROP TABLE IF EXISTS usuarios_empresas;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS empresas;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. EMPRESAS (multi-tenant)
-- ============================================================
CREATE TABLE empresas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(100),
    cnpj VARCHAR(20),
    inscricao_estadual VARCHAR(20),
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    uf CHAR(2),
    cep VARCHAR(10),
    telefone VARCHAR(20),
    email VARCHAR(150),
    ativo TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cnpj (cnpj),
    INDEX idx_razao_social (razao_social),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. USUARIOS (global - 1 user pode acessar N empresas)
-- ============================================================
CREATE TABLE usuarios (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil_padrao ENUM('admin','operador','aprovador','pagador','visualizador') DEFAULT 'operador',
    ativo TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso DATETIME,
    PRIMARY KEY (id),
    UNIQUE KEY uk_email (email),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. USUARIOS_EMPRESAS (N:N, com perfil por empresa)
-- ============================================================
CREATE TABLE usuarios_empresas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    usuario_id INT(11) NOT NULL,
    empresa_id INT(11) NOT NULL,
    perfil_na_empresa ENUM('admin','operador','aprovador','pagador','visualizador') NOT NULL,
    ativo TINYINT(1) DEFAULT 1,
    data_vinculo TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_empresa (usuario_id, empresa_id),
    INDEX idx_empresa (empresa_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. FORNECEDORES (por empresa)
-- ============================================================
CREATE TABLE fornecedores (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(100),
    cnpj VARCHAR(20),
    inscricao_estadual VARCHAR(20),
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    uf CHAR(2),
    cep VARCHAR(10),
    telefone VARCHAR(20),
    email VARCHAR(150),
    contato VARCHAR(100),
    observacoes TEXT,
    ativo TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_razao_social (razao_social),
    INDEX idx_cnpj (cnpj),
    INDEX idx_ativo (ativo),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. CLIENTES (por empresa) - NOVO
-- ============================================================
CREATE TABLE clientes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    razao_social VARCHAR(200) NOT NULL,
    nome_fantasia VARCHAR(100),
    cpf_cnpj VARCHAR(20),
    tipo_pessoa ENUM('F','J') DEFAULT 'J' COMMENT 'F=Fisica, J=Juridica',
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    uf CHAR(2),
    cep VARCHAR(10),
    telefone VARCHAR(20),
    email VARCHAR(150),
    contato VARCHAR(100),
    observacoes TEXT,
    ativo TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_razao_social (razao_social),
    INDEX idx_cpf_cnpj (cpf_cnpj),
    INDEX idx_ativo (ativo),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. CATEGORIAS (por empresa) - compartilhada Pagar/Receber
-- ============================================================
CREATE TABLE categorias (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    nome VARCHAR(100) NOT NULL,
    tipo ENUM('despesa','receita','ambos') DEFAULT 'despesa',
    cor VARCHAR(7) DEFAULT '#6c757d' COMMENT 'Cor em hex (#RRGGBB)',
    descricao VARCHAR(255),
    ativo TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_nome (nome),
    INDEX idx_tipo (tipo),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. CONTAS BANCARIAS (por empresa) - NOVO
-- ============================================================
CREATE TABLE contas_bancarias (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    descricao VARCHAR(100) NOT NULL COMMENT 'Nome de exibicao (ex: Banco do Brasil - CC 1234)',
    tipo ENUM('conta_corrente','poupanca','caixa_fisico','cartao','investimento') NOT NULL DEFAULT 'conta_corrente',
    banco VARCHAR(100) COMMENT 'Nome do banco (BB, Itau, Bradesco, Caixa...)',
    agencia VARCHAR(20),
    numero_conta VARCHAR(30),
    digito VARCHAR(5),
    titular VARCHAR(200),
    cpf_cnpj_titular VARCHAR(20),
    saldo_inicial DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo na data de criacao da conta',
    data_saldo_inicial DATE NOT NULL COMMENT 'Data de referencia do saldo inicial',
    observacoes TEXT,
    ativo TINYINT(1) DEFAULT 1,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. MOVIMENTACOES BANCARIAS (extrato) - NOVO
-- ============================================================
-- Cada movimentacao altera o saldo da conta.
-- Saldo atual = saldo_inicial + SUM(entradas) - SUM(saidas) [da data_saldo_inicial em diante]
-- Tipos:
--   'manual' - lancamento manual do usuario
--   'conta_pagar' - gerado automaticamente ao pagar uma conta
--   'conta_receber' - gerado automaticamente ao receber uma conta
--   'transferencia' - transferencia entre contas (gera 2: saida em uma + entrada em outra)
-- ============================================================
CREATE TABLE movimentacoes_bancarias (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    conta_bancaria_id INT(11) NOT NULL,
    data_movimento DATE NOT NULL,
    tipo ENUM('entrada','saida') NOT NULL,
    origem ENUM('manual','conta_pagar','conta_receber','transferencia') NOT NULL DEFAULT 'manual',
    valor DECIMAL(15,2) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    conta_pagar_id INT(11) NULL COMMENT 'Vinculo com conta a paga (se origem=conta_pagar)',
    conta_receber_id INT(11) NULL COMMENT 'Vinculo com conta a receber (se origem=conta_receber)',
    transferencia_id VARCHAR(36) NULL COMMENT 'UUID compartilhado entre as 2 pernas da transferencia',
    usuario_id INT(11) NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_conta (conta_bancaria_id),
    INDEX idx_data (data_movimento),
    INDEX idx_tipo (tipo),
    INDEX idx_origem (origem),
    INDEX idx_conta_pagar (conta_pagar_id),
    INDEX idx_conta_receber (conta_receber_id),
    INDEX idx_transferencia (transferencia_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (conta_bancaria_id) REFERENCES contas_bancarias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. CONTAS A PAGAR (por empresa)
-- ============================================================
CREATE TABLE contas_pagar (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    fornecedor_id INT(11) NOT NULL,
    categoria_id INT(11) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    numero_documento VARCHAR(100),
    valor DECIMAL(15,2) NOT NULL,
    data_emissao DATE NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    valor_pago DECIMAL(15,2) NULL,
    forma_pagamento ENUM('boleto','pix','transferencia','dinheiro','cartao','cheque','outros') DEFAULT 'boleto',
    conta_bancaria_id INT(11) NULL COMMENT 'Conta de onde saiu o dinheiro (quando paga)',
    status ENUM('pendente','aprovada','paga','cancelada') NOT NULL DEFAULT 'pendente',
    parcelas INT(11) DEFAULT 1 COMMENT '1 = a vista, >1 = parcelado',
    parcela_atual INT(11) DEFAULT 1,
    conta_pai_id INT(11) NULL COMMENT 'ID da conta original quando for parcela',
    observacoes TEXT,
    usuario_criacao_id INT(11) NOT NULL,
    usuario_aprovacao_id INT(11) NULL,
    usuario_pagamento_id INT(11) NULL,
    data_aprovacao DATETIME NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_fornecedor (fornecedor_id),
    INDEX idx_categoria (categoria_id),
    INDEX idx_vencimento (data_vencimento),
    INDEX idx_status (status),
    INDEX idx_conta_pai (conta_pai_id),
    INDEX idx_conta_bancaria (conta_bancaria_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE RESTRICT,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
    FOREIGN KEY (conta_bancaria_id) REFERENCES contas_bancarias(id) ON DELETE SET NULL,
    FOREIGN KEY (conta_pai_id) REFERENCES contas_pagar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. CONTAS A PAGAR PARCELAS (opcional, versao expandida)
-- Mantida para compatibilidade - parcela_atual ja da conta
-- ============================================================
CREATE TABLE contas_pagar_parcelas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    conta_pai_id INT(11) NOT NULL,
    numero_parcela INT(11) NOT NULL,
    total_parcelas INT(11) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    status ENUM('pendente','paga','cancelada') NOT NULL DEFAULT 'pendente',
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_conta_pai (conta_pai_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (conta_pai_id) REFERENCES contas_pagar(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. CONTAS A PAGAR RECORRENCIA (templates mensais)
-- ============================================================
CREATE TABLE contas_pagar_recorrencia (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    fornecedor_id INT(11) NOT NULL,
    categoria_id INT(11) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    dia_vencimento INT(11) NOT NULL COMMENT 'Dia do mes (1-31)',
    forma_pagamento ENUM('boleto','pix','transferencia','dinheiro','cartao','cheque','outros') DEFAULT 'boleto',
    ativa TINYINT(1) DEFAULT 1,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    proxima_geracao DATE NULL COMMENT 'Data em que a proxima conta sera gerada',
    observacoes TEXT,
    usuario_criacao_id INT(11) NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_ativa (ativa),
    INDEX idx_proxima_geracao (proxima_geracao),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE RESTRICT,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. CONTAS A RECEBER (por empresa) - NOVO
-- ============================================================
CREATE TABLE contas_receber (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    cliente_id INT(11) NOT NULL,
    categoria_id INT(11) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    numero_documento VARCHAR(100),
    valor DECIMAL(15,2) NOT NULL,
    data_emissao DATE NOT NULL,
    data_vencimento DATE NOT NULL,
    data_recebimento DATE NULL,
    valor_recebido DECIMAL(15,2) NULL,
    forma_recebimento ENUM('boleto','pix','transferencia','dinheiro','cartao','cheque','deposito','outros') DEFAULT 'boleto',
    conta_bancaria_id INT(11) NULL COMMENT 'Conta onde entrou o dinheiro (quando recebida)',
    status ENUM('pendente','aprovada','recebida','cancelada') NOT NULL DEFAULT 'pendente',
    parcelas INT(11) DEFAULT 1,
    parcela_atual INT(11) DEFAULT 1,
    conta_pai_id INT(11) NULL,
    observacoes TEXT,
    usuario_criacao_id INT(11) NOT NULL,
    usuario_aprovacao_id INT(11) NULL,
    usuario_recebimento_id INT(11) NULL,
    data_aprovacao DATETIME NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_cliente (cliente_id),
    INDEX idx_categoria (categoria_id),
    INDEX idx_vencimento (data_vencimento),
    INDEX idx_status (status),
    INDEX idx_conta_pai (conta_pai_id),
    INDEX idx_conta_bancaria (conta_bancaria_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT,
    FOREIGN KEY (conta_bancaria_id) REFERENCES contas_bancarias(id) ON DELETE SET NULL,
    FOREIGN KEY (conta_pai_id) REFERENCES contas_receber(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. CONTAS A RECEBER PARCELAS - NOVO
-- ============================================================
CREATE TABLE contas_receber_parcelas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    conta_pai_id INT(11) NOT NULL,
    numero_parcela INT(11) NOT NULL,
    total_parcelas INT(11) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    status ENUM('pendente','recebida','cancelada') NOT NULL DEFAULT 'pendente',
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_conta_pai (conta_pai_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (conta_pai_id) REFERENCES contas_receber(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. CONTAS A RECEBER RECORRENCIA (mensalidades) - NOVO
-- ============================================================
CREATE TABLE contas_receber_recorrencia (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    cliente_id INT(11) NOT NULL,
    categoria_id INT(11) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    dia_vencimento INT(11) NOT NULL,
    forma_recebimento ENUM('boleto','pix','transferencia','dinheiro','cartao','cheque','deposito','outros') DEFAULT 'boleto',
    ativa TINYINT(1) DEFAULT 1,
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    proxima_geracao DATE NULL,
    observacoes TEXT,
    usuario_criacao_id INT(11) NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_ativa (ativa),
    INDEX idx_proxima_geracao (proxima_geracao),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. ANEXOS (compartilhado - PDFs de notas fiscais/recibos)
-- ============================================================
CREATE TABLE anexos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    tipo_origem ENUM('conta_pagar','conta_receber') NOT NULL,
    origem_id INT(11) NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    tamanho INT(11) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    caminho VARCHAR(500) NOT NULL,
    usuario_upload_id INT(11) NOT NULL,
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_origem (tipo_origem, origem_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_upload_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. LOG DE OPERACOES (auditoria)
-- ============================================================
CREATE TABLE log_operacoes (
    id INT(11) NOT NULL AUTO_INCREMENT,
    empresa_id INT(11) NOT NULL,
    usuario_id INT(11) NOT NULL,
    tipo_operacao VARCHAR(50) NOT NULL,
    tabela_afetada VARCHAR(50) NOT NULL,
    registro_id INT(11),
    dados_anteriores TEXT,
    dados_novos TEXT,
    ip_address VARCHAR(45),
    data_operacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_data (data_operacao),
    INDEX idx_tabela (tabela_afetada, registro_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FIM DO SCHEMA
-- Total: 16 tabelas (4 novas: clientes, contas_bancarias, movimentacoes_bancarias,
--                       contas_receber + 2 tabelas relacionadas)
-- ============================================================