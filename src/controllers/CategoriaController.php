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

        // Suporte a retorno para tela de origem (ex: conta_form.php) com seleção automática
        $returnTo = preg_match('/^[a-z0-9_]+$/', (string)($_GET['return'] ?? '')) ? $_GET['return'] : '';
        $returnSelect = preg_match('/^[a-z0-9_]+$/', (string)($_GET['select'] ?? '')) ? $_GET['select'] : '';

        if (empty(trim($_POST['nome'] ?? ''))) {
            Flash::set('erro', 'Nome é obrigatório.');
            $back = $returnTo ? $returnTo : ($id > 0 ? "categoria_form.php?id=$id" : 'categoria_form.php');
            redirect($back);
        }
        if (!in_array($_POST['tipo'] ?? '', ['despesa', 'receita', 'ambos'], true)) {
            Flash::set('erro', 'Tipo inválido.');
            $back = $returnTo ? $returnTo : ($id > 0 ? "categoria_form.php?id=$id" : 'categoria_form.php');
            redirect($back);
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
                $novoId = (int)$db->lastInsertId();
                Flash::set('sucesso', 'Categoria criada.');

                // Se veio de outra tela (ex: conta_form), volta via view intermediária (cross-window)
                // pra preservar dados da janela pai (não causa reload)
                if ($returnTo && $novoId > 0) {
                    $query = http_build_query([
                        'tipo'   => 'categoria',
                        'select' => $returnSelect,
                        'id'     => $novoId,
                        'label'  => $dados['nome'],
                    ]);
                    redirect('_criar_filho_sucesso.php?' . $query);
                }
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