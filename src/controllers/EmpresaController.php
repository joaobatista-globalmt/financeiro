<?php
/**
 * EmpresaController - CRUD de empresas (multi-tenant)
 *
 * Apenas admin pode criar/editar/excluir.
 */

declare(strict_types=1);

final class EmpresaController
{
    public function index(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_empresas', 'index.php');

        $db = Database::getConnection();
        $stmt = $db->query('
            SELECT e.*,
                   (SELECT COUNT(*) FROM usuarios_empresas WHERE empresa_id = e.id AND ativo = 1) AS total_usuarios
            FROM empresas e
            ORDER BY e.ativo DESC, e.razao_social
        ');
        $empresas = $stmt->fetchAll();

        layout('Empresas', 'empresas/index.php', [
            'empresas' => $empresas,
        ]);
    }

    public function form(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_empresas', 'index.php');

        $id = (int)($_GET['id'] ?? 0);
        $empresa = null;

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM empresas WHERE id = ?');
            $stmt->execute([$id]);
            $empresa = $stmt->fetch();

            if (!$empresa) {
                Flash::set('erro', 'Empresa não encontrada.');
                redirect('empresas.php');
            }
        }

        layout($empresa ? 'Editar Empresa' : 'Nova Empresa', 'empresas/form.php', [
            'empresa' => $empresa,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_empresas', 'index.php');

        $id = (int)($_POST['id'] ?? 0);

        if (empty(trim($_POST['razao_social'] ?? ''))) {
            Flash::set('erro', 'Razão social é obrigatória.');
            redirect($id > 0 ? "empresa_form.php?id=$id" : 'empresa_form.php');
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
            'ativo'              => isset($_POST['ativo']) ? 1 : 1,
        ];

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                $dados['ativo'] = isset($_POST['ativo']) ? 1 : 0;
                $stmt = $db->prepare('
                    UPDATE empresas SET
                        razao_social=:razao_social, nome_fantasia=:nome_fantasia, cnpj=:cnpj,
                        inscricao_estadual=:inscricao_estadual, endereco=:endereco, cidade=:cidade,
                        uf=:uf, cep=:cep, telefone=:telefone, email=:email, ativo=:ativo
                    WHERE id=:id
                ');
                $dados['id'] = $id;
                $stmt->execute($dados);
                Flash::set('sucesso', 'Empresa atualizada.');
            } else {
                $stmt = $db->prepare('
                    INSERT INTO empresas
                        (razao_social, nome_fantasia, cnpj, inscricao_estadual, endereco, cidade,
                         uf, cep, telefone, email, ativo)
                    VALUES
                        (:razao_social, :nome_fantasia, :cnpj, :inscricao_estadual, :endereco, :cidade,
                         :uf, :cep, :telefone, :email, :ativo)
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Empresa criada.');
            }
        } catch (PDOException $e) {
            error_log('[Empresa] Erro: ' . $e->getMessage());
            if (str_contains($e->getMessage(), 'Duplicate') && str_contains($e->getMessage(), 'cnpj')) {
                Flash::set('erro', 'Já existe uma empresa com este CNPJ.');
            } else {
                Flash::set('erro', 'Erro ao salvar empresa.');
            }
        }

        redirect('empresas.php');
    }

    public function acao(): void
    {
        Auth::require();
        Permissao::requer('gerenciar_empresas', 'index.php');

        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('empresas.php');
        }

        $db = Database::getConnection();

        try {
            if ($acao === 'ativar') {
                $stmt = $db->prepare('UPDATE empresas SET ativo = 1 WHERE id = ?');
                $stmt->execute([$id]);
                Flash::set('sucesso', 'Empresa ativada.');
            } elseif ($acao === 'desativar') {
                $stmt = $db->prepare('UPDATE empresas SET ativo = 0 WHERE id = ?');
                $stmt->execute([$id]);
                Flash::set('sucesso', 'Empresa desativada.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }
        } catch (PDOException $e) {
            error_log('[Empresa] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('empresas.php');
    }

    /**
     * POST /trocar-empresa.php
     * Troca a empresa ativa (acessível a todos os usuários logados).
     */
    public function trocar(): void
    {
        Auth::require();
        $empresaId = (int)($_POST['empresa_id'] ?? $_GET['empresa_id'] ?? 0);

        if ($empresaId <= 0) {
            redirect('index.php');
        }

        if (Auth::trocarEmpresa($empresaId)) {
            Flash::set('sucesso', 'Empresa alterada.');
        } else {
            Flash::set('erro', 'Você não tem acesso a essa empresa.');
        }

        redirect('index.php');
    }
}