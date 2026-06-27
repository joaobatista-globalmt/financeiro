<?php
/**
 * Database - Singleton PDO
 *
 * Retorna uma instância única de PDO configurada para MariaDB.
 * Lança RuntimeException em caso de erro de conexão.
 *
 * @return PDO
 * @throws RuntimeException
 */

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['dbname'],
                $config['charset']
            );

            try {
                self::$instance = new PDO($dsn, $config['user'], $config['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                error_log('[Database] Erro de conexão: ' . $e->getMessage());
                throw new RuntimeException('Erro ao conectar ao banco de dados.');
            }
        }

        return self::$instance;
    }

    /**
     * Impede clonagem e instanciação.
     */
    private function __construct() {}
    private function __clone() {}
}