<?php
/**
 * Endpoint: cnae_servicos_listar.php
 * Lista os tipos de serviços por CNAE da empresa ativa.
 */
require __DIR__ . '/bootstrap.php';
(new CnaeServicoController)->listar();
