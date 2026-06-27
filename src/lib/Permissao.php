<?php
/**
 * Permissao - Sistema de perfis por empresa
 *
 * Perfis (em ordem de privilégio):
 *   - admin        : tudo
 *   - operador     : criar/editar registros, sem aprovar/pagar/receber
 *   - aprovador    : tudo do operador + aprovar
 *   - pagador      : tudo do operador + pagar (Pagar) / receber (Receber)
 *   - visualizador : só leitura
 *
 * Métodos retornam bool. Cada um checa uma capability específica.
 */

declare(strict_types=1);

final class Permissao
{
    public const PERFIS = ['admin', 'aprovador', 'pagador', 'operador', 'visualizador'];

    /** Matriz de capabilities por perfil */
    private const CAPS = [
        'admin' => [
            'criar', 'editar', 'excluir', 'aprovar', 'pagar', 'receber',
            'gerenciar_usuarios', 'gerenciar_empresas', 'gerenciar_cadastros',
        ],
        'aprovador' => [
            'criar', 'editar', 'aprovar',
            'gerenciar_cadastros',
        ],
        'pagador' => [
            'criar', 'editar', 'pagar', 'receber',
        ],
        'operador' => [
            'criar', 'editar',
        ],
        'visualizador' => [
            // só leitura, sem nenhuma capability
        ],
    ];

    /**
     * Verifica se o usuário logado tem determinada capability.
     *
     * @param string $capability ex: 'aprovar', 'pagar', 'receber'
     * @return bool
     */
    public static function tem(string $capability): bool
    {
        $perfil = Auth::perfilAtual();

        if (!$perfil) {
            return false;
        }

        return in_array($capability, self::CAPS[$perfil] ?? [], true);
    }

    /**
     * Atalho para verificar múltiplas capabilities (OR).
     *
     * @param string ...$caps
     * @return bool
     */
    public static function temAlguma(string ...$caps): bool
    {
        foreach ($caps as $c) {
            if (self::tem($c)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retorna todas as capabilities do usuário atual.
     *
     * @return array
     */
    public static function caps(): array
    {
        $perfil = Auth::perfilAtual();
        return self::CAPS[$perfil] ?? [];
    }

    /**
     * Bloqueia a ação se o usuário não tiver a capability.
     * Redireciona com flash de erro.
     *
     * @param string $capability
     * @param string $redirect URL de redirecionamento em caso de bloqueio
     */
    public static function requer(string $capability, string $redirect = 'index.php'): void
    {
        if (!self::tem($capability)) {
            Flash::set('erro', 'Você não tem permissão para essa ação.');
            header("Location: $redirect");
            exit;
        }
    }
}