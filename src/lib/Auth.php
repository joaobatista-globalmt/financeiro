<?php
/**
 * Auth - Autenticação e gerenciamento de sessão
 *
 * Gerencia login, logout, e estado da sessão do usuário.
 * Multi-empresa: usuário pode ter várias empresas vinculadas.
 * A empresa ativa fica em $_SESSION['empresa_id'].
 */

declare(strict_types=1);

final class Auth
{
    /**
     * Inicia sessão PHP se ainda não iniciada.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Tenta autenticar usuário por email e senha.
     * Retorna array com dados do usuário + empresas vinculadas em caso de sucesso.
     * Retorna null em caso de falha.
     *
     * @param string $email
     * @param string $senha
     * @return array|null
     */
    public static function login(string $email, string $senha): ?array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return null;
        }

        if (!password_verify($senha, $usuario['senha_hash'])) {
            return null;
        }

        // Buscar empresas vinculadas
        $stmtEmp = $db->prepare('
            SELECT ue.empresa_id, ue.perfil_na_empresa, e.razao_social, e.nome_fantasia
            FROM usuarios_empresas ue
            JOIN empresas e ON e.id = ue.empresa_id
            WHERE ue.usuario_id = ? AND ue.ativo = 1 AND e.ativo = 1
            ORDER BY e.razao_social
        ');
        $stmtEmp->execute([$usuario['id']]);
        $empresas = $stmtEmp->fetchAll();

        if (empty($empresas)) {
            return null; // usuário sem empresa ativa
        }

        // Atualizar último acesso
        $stmtUp = $db->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?');
        $stmtUp->execute([$usuario['id']]);

        // Definir empresa ativa (primeira da lista, ou perfil padrao)
        $empresaAtivaId = $empresas[0]['empresa_id'];

        $_SESSION['usuario_id']      = (int)$usuario['id'];
        $_SESSION['usuario_nome']    = $usuario['nome'];
        $_SESSION['usuario_email']   = $usuario['email'];
        $_SESSION['empresa_id']      = (int)$empresaAtivaId;
        $_SESSION['empresas']        = $empresas;

        return [
            'usuario'  => $usuario,
            'empresas' => $empresas,
        ];
    }

    /**
     * Troca a empresa ativa do usuário logado.
     * Verifica se o usuário tem acesso à empresa solicitada.
     *
     * @param int $empresaId
     * @return bool
     */
    public static function trocarEmpresa(int $empresaId): bool
    {
        self::start();

        if (!self::isLogged()) {
            return false;
        }

        foreach ($_SESSION['empresas'] as $emp) {
            if ((int)$emp['empresa_id'] === $empresaId) {
                $_SESSION['empresa_id'] = $empresaId;
                return true;
            }
        }

        return false;
    }

    /**
     * Retorna o perfil ativo do usuário na empresa atual.
     */
    public static function perfilAtual(): string
    {
        self::start();

        if (!self::isLogged()) {
            return '';
        }

        foreach ($_SESSION['empresas'] as $emp) {
            if ((int)$emp['empresa_id'] === (int)$_SESSION['empresa_id']) {
                return $emp['perfil_na_empresa'];
            }
        }

        return '';
    }

    /**
     * Encerra a sessão e redireciona para login.
     */
    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }

    /**
     * Verifica se o usuário está logado. Redireciona para login se não estiver.
     */
    public static function require(): void
    {
        self::start();

        if (!self::isLogged()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * Verifica se está logado (sem redirecionar).
     */
    public static function isLogged(): bool
    {
        self::start();
        return isset($_SESSION['usuario_id']) && isset($_SESSION['empresa_id']);
    }

    /**
     * Retorna dados do usuário logado.
     *
     * @return array{id:int,nome:string,email:string,empresa_id:int,perfil:string}|array{}
     */
    public static function user(): array
    {
        self::start();

        if (!self::isLogged()) {
            return [];
        }

        return [
            'id'         => (int)$_SESSION['usuario_id'],
            'nome'       => $_SESSION['usuario_nome'] ?? '',
            'email'      => $_SESSION['usuario_email'] ?? '',
            'empresa_id' => (int)$_SESSION['empresa_id'],
            'perfil'     => self::perfilAtual(),
        ];
    }
}