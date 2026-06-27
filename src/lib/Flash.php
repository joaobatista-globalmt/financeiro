<?php
/**
 * Flash - Mensagens temporárias entre requisições
 *
 * Armazena mensagens na sessão que são exibidas uma vez e depois apagadas.
 * Suporta tipos: sucesso, erro, aviso, info.
 */

declare(strict_types=1);

final class Flash
{
    public const TIPOS = ['sucesso', 'erro', 'aviso', 'info'];

    /**
     * Define uma mensagem flash.
     *
     * @param string $tipo sucesso|erro|aviso|info
     * @param string $mensagem
     */
    public static function set(string $tipo, string $mensagem): void
    {
        Auth::start();

        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'info';
        }

        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }

        $_SESSION['_flash'][] = ['tipo' => $tipo, 'msg' => $mensagem];
    }

    /**
     * Retorna todas as mensagens pendentes e as remove da sessão.
     *
     * @return array<array{tipo:string,msg:string}>
     */
    public static function get(): array
    {
        Auth::start();

        $msgs = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $msgs;
    }

    /**
     * Verifica se há mensagens pendentes (sem removê-las).
     */
    public static function tem(): bool
    {
        Auth::start();
        return !empty($_SESSION['_flash']);
    }
}