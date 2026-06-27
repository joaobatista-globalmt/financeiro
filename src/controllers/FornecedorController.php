<?php
/**
 * FornecedorController - CRUD de fornecedores
 */

declare(strict_types=1);

final class FornecedorController
{
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT f.*,
                   (SELECT COUNT(*) FROM contas_pagar WHERE fornecedor_id = f.id) AS total_contas
            FROM fornecedores f
            WHERE f.empresa_id = ?
            ORDER BY f.ativo DESC, f.razao_social
        ');
        $stmt->execute([$empresaId]);
        $fornecedores = $stmt->fetchAll();

        layout('Fornecedores', 'fornecedores/index.php', [
            'fornecedores' => $fornecedores,
        ]);
    }

    public function form(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $fornecedor = null;

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM fornecedores WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, Auth::user()['empresa_id']]);
            $fornecedor = $stmt->fetch();

            if (!$fornecedor) {
                Flash::set('erro', 'Fornecedor não encontrado.');
                redirect('fornecedores.php');
            }
        }

        layout($fornecedor ? 'Editar Fornecedor' : 'Novo Fornecedor', 'fornecedores/form.php', [
            'fornecedor' => $fornecedor,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_cadastros', 'fornecedores.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);

        if (empty(trim($_POST['razao_social'] ?? ''))) {
            Flash::set('erro', 'Razão social é obrigatória.');
            redirect($id > 0 ? "fornecedor_form.php?id=$id" : 'fornecedor_form.php');
        }

        $dados = [
            'razao_social'       => trim($_POST['razao_social']),
            'nome_fantasia'      => trim($_POST['nome_fantasia'] ?? '') ?: null,
            'cnpj'               => trim($_POST['cnpj'] ?? '') ?: null,
            'inscricao_estadual' => trim($_POST['inscricao_estadual'] ?? '') ?: null,
            'endereco'           => trim($_POST['endereco'] ?? '') ?: null,
            'cidade'             => trim($_POST['cidade'] ?? '') ?: null,
            'uf'                 => strtoupper(trim($_POST['uf'] ?? '')) ?: null,
            'cep'                => trim($_POST['cep'] ?? '') ?: null,
            'telefone'           => trim($_POST['telefone'] ?? '') ?: null,
            'email'              => trim($_POST['email'] ?? '') ?: null,
            'contato'            => trim($_POST['contato'] ?? '') ?: null,
            'observacoes'        => trim($_POST['observacoes'] ?? '') ?: null,
            'ativo'              => 1,
        ];

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                $dados['ativo'] = isset($_POST['ativo']) ? 1 : 0;
                $stmt = $db->prepare('
                    UPDATE fornecedores SET
                        razao_social=:razao_social, nome_fantasia=:nome_fantasia, cnpj=:cnpj,
                        inscricao_estadual=:inscricao_estadual, endereco=:endereco, cidade=:cidade,
                        uf=:uf, cep=:cep, telefone=:telefone, email=:email, contato=:contato,
                        observacoes=:observacoes, ativo=:ativo
                    WHERE id=:id AND empresa_id=:empresa_id
                ');
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                Flash::set('sucesso', 'Fornecedor atualizado.');
            } else {
                $dados['empresa_id'] = $empresaId;
                $stmt = $db->prepare('
                    INSERT INTO fornecedores
                        (empresa_id, razao_social, nome_fantasia, cnpj, inscricao_estadual,
                         endereco, cidade, uf, cep, telefone, email, contato, observacoes, ativo)
                    VALUES
                        (:empresa_id, :razao_social, :nome_fantasia, :cnpj, :inscricao_estadual,
                         :endereco, :cidade, :uf, :cep, :telefone, :email, :contato, :observacoes, :ativo)
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Fornecedor criado.');
            }
        } catch (PDOException $e) {
            error_log('[Fornecedor] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar fornecedor.');
        }

        redirect('fornecedores.php');
    }

    public function acao(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_cadastros', 'fornecedores.php');

        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('fornecedores.php');
        }

        $db = Database::getConnection();

        try {
            if ($acao === 'ativar') {
                $stmt = $db->prepare('UPDATE fornecedores SET ativo = 1 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Fornecedor ativado.');
            } elseif ($acao === 'desativar') {
                $stmt = $db->prepare('UPDATE fornecedores SET ativo = 0 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Fornecedor desativado.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }
        } catch (PDOException $e) {
            error_log('[Fornecedor] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('fornecedores.php');
    }
}