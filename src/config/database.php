<?php
/**
 * Configuração de Banco de Dados - Sistema Financeiro
 *
 * Conexão PDO com MariaDB. Lê credenciais de variáveis de ambiente
 * com fallback para desenvolvimento local.
 *
 * Em produção (servidor 192.168.70.45), as variáveis estão definidas em
 * /etc/php/8.2/fpm/pool.d/www.conf via env[DB_FINANCEIRO_*]
 */

declare(strict_types=1);

return [
    'host'    => getenv('DB_FIN_HOST') ?: '127.0.0.1',
    'port'    => (int)(getenv('DB_FIN_PORT') ?: 3306),
    'dbname'  => getenv('DB_FIN_DB')   ?: 'financeiro',
    'user'    => getenv('DB_FIN_USER') ?: 'financeiro_app',
    'pass'    => getenv('DB_FIN_PASS') ?: '',
    'charset' => 'utf8mb4',
];

/**
 * Dica: nunca commitar este arquivo com senha real.
 * Variáveis de ambiente no servidor (PHP-FPM):
 *   env[DB_FIN_HOST] = 127.0.0.1
 *   env[DB_FIN_PORT] = 3306
 *   env[DB_FIN_DB]   = financeiro
 *   env[DB_FIN_USER] = financeiro_app
 *   env[DB_FIN_PASS] = <ver gerenciador de senhas>
 */