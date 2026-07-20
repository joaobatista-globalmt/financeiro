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

        // Suporte a retorno para tela de origem (ex: conta_form.php) com seleção automática
        // Aceita nomes como "conta_form", "conta_receber_form", "fornecedores" (sem .php)
        $returnTo = preg_match('/^[a-z0-9_]+$/', (string)($_GET['return'] ?? '')) ? $_GET['return'] : '';
        $returnSelect = preg_match('/^[a-z0-9_]+$/', (string)($_GET['select'] ?? '')) ? $_GET['select'] : '';

        if (empty(trim($_POST['razao_social'] ?? ''))) {
            Flash::set('erro', 'Razão social é obrigatória.');
            $back = $returnTo ? $returnTo . ($returnSelect ? '?select=' . $returnSelect : '') : ($id > 0 ? "fornecedor_form.php?id=$id" : 'fornecedor_form.php');
            redirect($back);
        }

        // Validação da chave PIX (se informada)
        $pixTipo  = $_POST['pix_tipo'] ?? '';
        $pixChave = trim((string)($_POST['pix_chave'] ?? ''));
        $tiposPixValidos = ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'];
        if ($pixTipo !== '' && !in_array($pixTipo, $tiposPixValidos, true)) {
            $pixTipo = '';
        }
        if ($pixChave === '') {
            $pixTipo = ''; // se não tem chave, não tem tipo
        } elseif ($pixTipo === '') {
            $pixChave = ''; // se não tem tipo, ignora chave
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
            'pix_chave'          => $pixChave ?: null,
            'pix_tipo'           => $pixTipo ?: null,
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
                        observacoes=:observacoes, pix_chave=:pix_chave, pix_tipo=:pix_tipo, ativo=:ativo
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
                         endereco, cidade, uf, cep, telefone, email, contato, observacoes,
                         pix_chave, pix_tipo, ativo)
                    VALUES
                        (:empresa_id, :razao_social, :nome_fantasia, :cnpj, :inscricao_estadual,
                         :endereco, :cidade, :uf, :cep, :telefone, :email, :contato, :observacoes,
                         :pix_chave, :pix_tipo, :ativo)
                ');
                $stmt->execute($dados);
                $novoId = (int)$db->lastInsertId();
                Flash::set('sucesso', 'Fornecedor criado.');

                // Se veio de outra tela (ex: conta_form), volta pra lá com o novo ID + label
                // Mas usa view intermediária (cross-window) pra preservar dados da janela pai
                if ($returnTo && $novoId > 0) {
                    $alvo = (str_ends_with($returnTo, '.php') ? $returnTo : $returnTo . '.php');
                    $query = http_build_query([
                        'tipo'   => 'fornecedor',
                        'select' => $returnSelect,
                        'id'     => $novoId,
                        'label'  => $dados['razao_social'],
                    ]);
                    redirect('_criar_filho_sucesso.php?' . $query);
                }
            }
        } catch (PDOException $e) {
            error_log('[Fornecedor] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar fornecedor.');
        }

        // Fallback: vai pra lista (comportamento padrão existente)
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