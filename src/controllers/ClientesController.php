<?php
/**
 * ClientesController - CRUD de clientes (para Contas a Receber)
 *
 * Espelho do FornecedoresController, mas para a tabela `clientes`.
 */

declare(strict_types=1);

final class ClientesController
{
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT c.*,
                   (SELECT COUNT(*) FROM contas_receber WHERE cliente_id = c.id) AS total_contas
            FROM clientes c
            WHERE c.empresa_id = ?
            ORDER BY c.ativo DESC, c.razao_social
        ');
        $stmt->execute([$empresaId]);
        $clientes = $stmt->fetchAll();

        layout('Clientes', 'clientes/index.php', [
            'clientes' => $clientes,
        ]);
    }

    public function form(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $cliente = null;

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM clientes WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, Auth::user()['empresa_id']]);
            $cliente = $stmt->fetch();

            if (!$cliente) {
                Flash::set('erro', 'Cliente não encontrado.');
                redirect('clientes.php');
            }
        }

        layout($cliente ? 'Editar Cliente' : 'Novo Cliente', 'clientes/form.php', [
            'cliente' => $cliente,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'clientes.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);

        if (empty(trim($_POST['razao_social'] ?? ''))) {
            Flash::set('erro', 'Razão social é obrigatória.');
            redirect($id > 0 ? "cliente_form.php?id=$id" : 'cliente_form.php');
        }
        if (!in_array($_POST['tipo_pessoa'] ?? '', ['F', 'J'], true)) {
            Flash::set('erro', 'Tipo de pessoa inválido.');
            redirect($id > 0 ? "cliente_form.php?id=$id" : 'cliente_form.php');
        }

        $dados = [
            'razao_social'  => trim($_POST['razao_social']),
            'nome_fantasia' => trim($_POST['nome_fantasia'] ?? '') ?: null,
            'cpf_cnpj'      => trim($_POST['cpf_cnpj'] ?? '') ?: null,
            'tipo_pessoa'   => $_POST['tipo_pessoa'],
            'endereco'      => trim($_POST['endereco'] ?? '') ?: null,
            'cidade'        => trim($_POST['cidade'] ?? '') ?: null,
            'uf'            => strtoupper(trim($_POST['uf'] ?? '')) ?: null,
            'cep'           => trim($_POST['cep'] ?? '') ?: null,
            'telefone'      => trim($_POST['telefone'] ?? '') ?: null,
            'email'         => trim($_POST['email'] ?? '') ?: null,
            'contato'       => trim($_POST['contato'] ?? '') ?: null,
            'observacoes'   => trim($_POST['observacoes'] ?? '') ?: null,
            'ativo'         => isset($_POST['ativo']) ? 1 : 1,
        ];

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                $dados['ativo'] = isset($_POST['ativo']) ? 1 : 0;
                $stmt = $db->prepare('
                    UPDATE clientes SET
                        razao_social=:razao_social, nome_fantasia=:nome_fantasia, cpf_cnpj=:cpf_cnpj,
                        tipo_pessoa=:tipo_pessoa, endereco=:endereco, cidade=:cidade, uf=:uf,
                        cep=:cep, telefone=:telefone, email=:email, contato=:contato,
                        observacoes=:observacoes, ativo=:ativo
                    WHERE id=:id AND empresa_id=:empresa_id
                ');
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                Flash::set('sucesso', 'Cliente atualizado.');
            } else {
                $dados['empresa_id'] = $empresaId;
                $stmt = $db->prepare('
                    INSERT INTO clientes
                        (empresa_id, razao_social, nome_fantasia, cpf_cnpj, tipo_pessoa,
                         endereco, cidade, uf, cep, telefone, email, contato, observacoes, ativo)
                    VALUES
                        (:empresa_id, :razao_social, :nome_fantasia, :cpf_cnpj, :tipo_pessoa,
                         :endereco, :cidade, :uf, :cep, :telefone, :email, :contato, :observacoes, :ativo)
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Cliente criado.');
            }
        } catch (PDOException $e) {
            error_log('[Clientes] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar cliente.');
        }

        redirect('clientes.php');
    }

    public function acao(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'clientes.php');

        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('clientes.php');
        }

        $db = Database::getConnection();

        try {
            if ($acao === 'excluir') {
                $stmtCheck = $db->prepare('SELECT COUNT(*) AS total FROM contas_receber WHERE cliente_id = ?');
                $stmtCheck->execute([$id]);
                $temContas = $stmtCheck->fetch()['total'] > 0;

                if ($temContas) {
                    $stmt = $db->prepare('UPDATE clientes SET ativo = 0 WHERE id = ? AND empresa_id = ?');
                    $stmt->execute([$id, $empresaId]);
                    Flash::set('aviso', 'Cliente possui contas. Foi inativado em vez de excluído.');
                } else {
                    $stmt = $db->prepare('DELETE FROM clientes WHERE id = ? AND empresa_id = ?');
                    $stmt->execute([$id, $empresaId]);
                    Flash::set('sucesso', 'Cliente excluído.');
                }
            } elseif ($acao === 'ativar') {
                $stmt = $db->prepare('UPDATE clientes SET ativo = 1 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Cliente ativado.');
            } elseif ($acao === 'desativar') {
                $stmt = $db->prepare('UPDATE clientes SET ativo = 0 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Cliente desativado.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }
        } catch (PDOException $e) {
            error_log('[Clientes] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('clientes.php');
    }
}