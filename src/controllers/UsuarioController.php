<?php
/**
 * UsuarioController - CRUD de usuários e vinculação empresa/perfil
 */

declare(strict_types=1);

final class UsuarioController
{
    public function index(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_usuarios', 'index.php');

        $db = Database::getConnection();
        $stmt = $db->query('
            SELECT u.*,
                   GROUP_CONCAT(DISTINCT e.nome_fantasia ORDER BY e.nome_fantasia SEPARATOR ", ") AS empresas_vinculadas
            FROM usuarios u
            LEFT JOIN usuarios_empresas ue ON ue.usuario_id = u.id AND ue.ativo = 1
            LEFT JOIN empresas e ON e.id = ue.empresa_id
            GROUP BY u.id
            ORDER BY u.ativo DESC, u.nome
        ');
        $usuarios = $stmt->fetchAll();

        layout('Usuários', 'usuarios/index.php', [
            'usuarios' => $usuarios,
        ]);
    }

    public function form(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_usuarios', 'index.php');

        $id = (int)($_GET['id'] ?? 0);
        $usuario = null;
        $vinculos = [];

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
            $usuario = $stmt->fetch();

            if (!$usuario) {
                Flash::set('erro', 'Usuário não encontrado.');
                redirect('usuarios.php');
            }

            $stmtV = $db->prepare('SELECT empresa_id, perfil_na_empresa, ativo FROM usuarios_empresas WHERE usuario_id = ?');
            $stmtV->execute([$id]);
            $vinculos = $stmtV->fetchAll();
        }

        $empresas = $db = Database::getConnection();
        $empresas = $db->query('SELECT id, razao_social, nome_fantasia FROM empresas WHERE ativo = 1 ORDER BY razao_social')->fetchAll();

        layout($usuario ? 'Editar Usuário' : 'Novo Usuário', 'usuarios/form.php', [
            'usuario'  => $usuario,
            'vinculos' => $vinculos,
            'empresas' => $empresas,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_usuarios', 'index.php');

        $id = (int)($_POST['id'] ?? 0);

        if (empty(trim($_POST['nome'] ?? ''))) {
            Flash::set('erro', 'Nome é obrigatório.');
            redirect($id > 0 ? "usuario_form.php?id=$id" : 'usuario_form.php');
        }
        if (empty(trim($_POST['email'] ?? ''))) {
            Flash::set('erro', 'E-mail é obrigatório.');
            redirect($id > 0 ? "usuario_form.php?id=$id" : 'usuario_form.php');
        }
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            Flash::set('erro', 'E-mail inválido.');
            redirect($id > 0 ? "usuario_form.php?id=$id" : 'usuario_form.php');
        }
        if (!in_array($_POST['perfil_padrao'] ?? '', Permissao::PERFIS, true)) {
            Flash::set('erro', 'Perfil inválido.');
            redirect($id > 0 ? "usuario_form.php?id=$id" : 'usuario_form.php');
        }

        $senha = $_POST['senha'] ?? '';
        $confirmaSenha = $_POST['confirma_senha'] ?? '';

        if ($id === 0 && empty($senha)) {
            Flash::set('erro', 'Senha é obrigatória para novo usuário.');
            redirect('usuario_form.php');
        }
        if (!empty($senha) && $senha !== $confirmaSenha) {
            Flash::set('erro', 'Senhas não conferem.');
            redirect($id > 0 ? "usuario_form.php?id=$id" : 'usuario_form.php');
        }
        if (!empty($senha) && strlen($senha) < 6) {
            Flash::set('erro', 'Senha deve ter no mínimo 6 caracteres.');
            redirect($id > 0 ? "usuario_form.php?id=$id" : 'usuario_form.php');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            if ($id > 0) {
                $sql = 'UPDATE usuarios SET nome=:nome, email=:email, perfil_padrao=:perfil_padrao';
                $params = [
                    'nome' => trim($_POST['nome']),
                    'email' => trim($_POST['email']),
                    'perfil_padrao' => $_POST['perfil_padrao'],
                    'id' => $id,
                ];

                if (!empty($senha)) {
                    $sql .= ', senha_hash=:senha_hash';
                    $params['senha_hash'] = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]);
                }
                $sql .= ' WHERE id=:id';
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $db->prepare('
                    INSERT INTO usuarios (nome, email, senha_hash, perfil_padrao, ativo)
                    VALUES (:nome, :email, :senha_hash, :perfil_padrao, 1)
                ');
                $stmt->execute([
                    'nome'          => trim($_POST['nome']),
                    'email'         => trim($_POST['email']),
                    'senha_hash'    => password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]),
                    'perfil_padrao' => $_POST['perfil_padrao'],
                ]);
                $id = (int)$db->lastInsertId();
            }

            // Vínculos empresa/perfil
            $vinculos = $_POST['vinculos'] ?? [];
            $stmtDel = $db->prepare('DELETE FROM usuarios_empresas WHERE usuario_id = ?');
            $stmtDel->execute([$id]);

            $stmtIns = $db->prepare('
                INSERT INTO usuarios_empresas (usuario_id, empresa_id, perfil_na_empresa, ativo)
                VALUES (:usuario_id, :empresa_id, :perfil, 1)
            ');
            foreach ($vinculos as $empresaId => $perfil) {
                if (!in_array($perfil, Permissao::PERFIS, true)) continue;
                $stmtIns->execute([
                    'usuario_id' => $id,
                    'empresa_id' => (int)$empresaId,
                    'perfil'     => $perfil,
                ]);
            }

            $db->commit();
            Flash::set('sucesso', 'Usuário salvo.');
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[Usuario] Erro: ' . $e->getMessage());
            if (str_contains($e->getMessage(), 'Duplicate') && str_contains($e->getMessage(), 'email')) {
                Flash::set('erro', 'Já existe um usuário com este e-mail.');
            } else {
                Flash::set('erro', 'Erro ao salvar usuário.');
            }
        }

        redirect('usuarios.php');
    }

    public function acao(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_usuarios', 'index.php');

        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';

        if ($id <= 0 || $id === (int)Auth::user()['id']) {
            Flash::set('erro', 'Operação inválida.');
            redirect('usuarios.php');
        }

        $db = Database::getConnection();

        try {
            if ($acao === 'ativar') {
                $stmt = $db->prepare('UPDATE usuarios SET ativo = 1 WHERE id = ?');
                $stmt->execute([$id]);
                Flash::set('sucesso', 'Usuário ativado.');
            } elseif ($acao === 'desativar') {
                $stmt = $db->prepare('UPDATE usuarios SET ativo = 0 WHERE id = ?');
                $stmt->execute([$id]);
                Flash::set('sucesso', 'Usuário desativado.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }
        } catch (PDOException $e) {
            error_log('[Usuario] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('usuarios.php');
    }
}