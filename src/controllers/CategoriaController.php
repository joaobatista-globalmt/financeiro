<?php
/**
 * CategoriaController - CRUD de categorias (compartilhada Pagar/Receber)
 */

declare(strict_types=1);

final class CategoriaController
{
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT c.*,
                   (SELECT COUNT(*) FROM contas_pagar    WHERE categoria_id = c.id) AS qtd_pagar,
                   (SELECT COUNT(*) FROM contas_receber  WHERE categoria_id = c.id) AS qtd_receber
            FROM categorias c
            WHERE c.empresa_id = ?
            ORDER BY c.ativo DESC, c.nome
        ');
        $stmt->execute([$empresaId]);
        $categorias = $stmt->fetchAll();

        layout('Categorias', 'categorias/index.php', [
            'categorias' => $categorias,
        ]);
    }

    public function form(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $categoria = null;

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM categorias WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, Auth::user()['empresa_id']]);
            $categoria = $stmt->fetch();

            if (!$categoria) {
                Flash::set('erro', 'Categoria não encontrada.');
                redirect('categorias.php');
            }
        }

        layout($categoria ? 'Editar Categoria' : 'Nova Categoria', 'categorias/form.php', [
            'categoria' => $categoria,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_cadastros', 'categorias.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);

        if (empty(trim($_POST['nome'] ?? ''))) {
            Flash::set('erro', 'Nome é obrigatório.');
            redirect($id > 0 ? "categoria_form.php?id=$id" : 'categoria_form.php');
        }
        if (!in_array($_POST['tipo'] ?? '', ['despesa', 'receita', 'ambos'], true)) {
            Flash::set('erro', 'Tipo inválido.');
            redirect($id > 0 ? "categoria_form.php?id=$id" : 'categoria_form.php');
        }

        $dados = [
            'nome'  => trim($_POST['nome']),
            'tipo'  => $_POST['tipo'],
            'cor'   => $_POST['cor'] ?? '#6c757d',
            'descricao' => trim($_POST['descricao'] ?? '') ?: null,
            'ativo' => 1,
        ];

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                $dados['ativo'] = isset($_POST['ativo']) ? 1 : 0;
                $stmt = $db->prepare('
                    UPDATE categorias SET nome=:nome, tipo=:tipo, cor=:cor, descricao=:descricao, ativo=:ativo
                    WHERE id=:id AND empresa_id=:empresa_id
                ');
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                Flash::set('sucesso', 'Categoria atualizada.');
            } else {
                $dados['empresa_id'] = $empresaId;
                $stmt = $db->prepare('
                    INSERT INTO categorias (empresa_id, nome, tipo, cor, descricao, ativo)
                    VALUES (:empresa_id, :nome, :tipo, :cor, :descricao, :ativo)
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Categoria criada.');
            }
        } catch (PDOException $e) {
            error_log('[Categoria] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar categoria.');
        }

        redirect('categorias.php');
    }

    public function acao(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_cadastros', 'categorias.php');

        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('categorias.php');
        }

        $db = Database::getConnection();

        try {
            if ($acao === 'ativar') {
                $stmt = $db->prepare('UPDATE categorias SET ativo = 1 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Categoria ativada.');
            } elseif ($acao === 'desativar') {
                $stmt = $db->prepare('UPDATE categorias SET ativo = 0 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Categoria desativada.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }
        } catch (PDOException $e) {
            error_log('[Categoria] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('categorias.php');
    }
}