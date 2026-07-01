-- ============================================================
-- Migration: adicionar campos PIX em fornecedores e clientes
--
-- Tipos de chave PIX (padrão Banco Central):
--   - cpf        : CPF (11 dígitos)
--   - cnpj       : CNPJ (14 dígitos)
--   - email      : endereço de e-mail
--   - telefone   : celular com DDD (ex: 65999998888)
--   - aleatoria  : chave aleatória UUID
--
-- Aplicar com:
--   mysql -u root -p financeiro < database/migrate-pix-fields.sql
-- ============================================================

ALTER TABLE fornecedores
    ADD COLUMN pix_chave VARCHAR(255) NULL AFTER observacoes,
    ADD COLUMN pix_tipo  ENUM('cpf','cnpj','email','telefone','aleatoria') NULL AFTER pix_chave;

ALTER TABLE clientes
    ADD COLUMN pix_chave VARCHAR(255) NULL AFTER observacoes,
    ADD COLUMN pix_tipo  ENUM('cpf','cnpj','email','telefone','aleatoria') NULL AFTER pix_chave;
