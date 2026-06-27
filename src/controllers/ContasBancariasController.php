<?php
/**
 * ContasBancariasController - CRUD de contas bancárias + saldo
 *
 * Tabelas: contas_bancarias, movimentacoes_bancarias
 *
 * Saldo é calculado em tempo real:
 *   saldo_atual = saldo_inicial
 *               + SUM(entradas) na data >= data_saldo_inicial
 *               - SUM(saidas)   na data >= data_saldo_inicial
 */

declare(strict_types=1);

final class ContasBancariasController
{
    /**
     * GET /contas_bancarias.php
     * Lista todas as contas bancárias da empresa ativa com saldo atual.
     */
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT
                cb.*,
                (SELECT COALESCE(SUM(CASE WHEN m.tipo = "entrada" THEN m.valor ELSE -m.valor END), 0)
                 FROM movimentacoes_bancarias m
                 WHERE m.conta_bancaria_id = cb.id
                   AND m.data_movimento >= cb.data_saldo_inicial
                ) AS variacao,
                (cb.saldo_inicial +
                 (SELECT COALESCE(SUM(CASE WHEN m.tipo = "entrada" THEN m.valor ELSE -m.valor END), 0)
                  FROM movimentacoes_bancarias m
                  WHERE m.conta_bancaria_id = cb.id
                    AND m.data_movimento >= cb.data_saldo_inicial
                 )
                ) AS saldo_atual
            FROM contas_bancarias cb
            WHERE cb.empresa_id = ?
            ORDER BY cb.ativo DESC, cb.descricao
        ');
        $stmt->execute([$empresaId]);
        $contas = $stmt->fetchAll();

        $saldoTotal = array_sum(array_column($contas, 'saldo_atual'));

        layout('Contas Bancárias', 'contas_bancarias/index.php', [
            'contas'     => $contas,
            'saldoTotal' => $saldoTotal,
        ]);
    }

    /**
     * GET /conta_bancaria_form.php?id=N
     * Formulário de cadastro/edição.
     */
    public function form(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $conta = null;

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                SELECT * FROM contas_bancarias
                WHERE id = ? AND empresa_id = ?
            ');
            $stmt->execute([$id, Auth::user()['empresa_id']]);
            $conta = $stmt->fetch();

            if (!$conta) {
                Flash::set('erro', 'Conta bancária não encontrada.');
                redirect('contas_bancarias.php');
            }
        }

        layout($conta ? 'Editar Conta Bancária' : 'Nova Conta Bancária', 'contas_bancarias/form.php', [
            'conta' => $conta,
        ]);
    }

    /**
     * POST /conta_bancaria_salvar.php
     * Cria ou atualiza uma conta bancária.
     */
    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_bancarias.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);

        // Validações
        $erros = [];

        if (empty(trim($_POST['descricao'] ?? ''))) {
            $erros[] = 'Descrição é obrigatória.';
        }
        if (!in_array($_POST['tipo'] ?? '', ['conta_corrente', 'poupanca', 'caixa_fisico', 'cartao', 'investimento'], true)) {
            $erros[] = 'Tipo inválido.';
        }
        if (!is_numeric($_POST['saldo_inicial'] ?? '')) {
            $erros[] = 'Saldo inicial inválido.';
        }
        if (empty($_POST['data_saldo_inicial'] ?? '')) {
            $erros[] = 'Data do saldo inicial é obrigatória.';
        }

        if (!empty($erros)) {
            Flash::set('erro', implode('<br>', $erros));
            redirect($id > 0 ? "conta_bancaria_form.php?id=$id" : 'conta_bancaria_form.php');
        }

        $dados = [
            'descricao'          => trim($_POST['descricao']),
            'tipo'               => $_POST['tipo'],
            'banco'              => trim($_POST['banco'] ?? '') ?: null,
            'agencia'            => trim($_POST['agencia'] ?? '') ?: null,
            'numero_conta'       => trim($_POST['numero_conta'] ?? '') ?: null,
            'digito'             => trim($_POST['digito'] ?? '') ?: null,
            'titular'            => trim($_POST['titular'] ?? '') ?: null,
            'cpf_cnpj_titular'   => trim($_POST['cpf_cnpj_titular'] ?? '') ?: null,
            'saldo_inicial'      => (float)str_replace(',', '.', $_POST['saldo_inicial']),
            'data_saldo_inicial' => $_POST['data_saldo_inicial'],
            'observacoes'        => trim($_POST['observacoes'] ?? '') ?: null,
            'ativo'              => isset($_POST['ativo']) ? 1 : 1, // sempre ativo ao criar
        ];

        $db = Database::getConnection();

        try {
            if ($id > 0) {
                // edição
                $dados['ativo'] = isset($_POST['ativo']) ? 1 : 0;
                $stmt = $db->prepare('
                    UPDATE contas_bancarias SET
                        descricao=:descricao, tipo=:tipo, banco=:banco, agencia=:agencia,
                        numero_conta=:numero_conta, digito=:digito, titular=:titular,
                        cpf_cnpj_titular=:cpf_cnpj_titular, saldo_inicial=:saldo_inicial,
                        data_saldo_inicial=:data_saldo_inicial, observacoes=:observacoes,
                        ativo=:ativo
                    WHERE id=:id AND empresa_id=:empresa_id
                ');
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                Flash::set('sucesso', 'Conta bancária atualizada.');
            } else {
                // criação
                $dados['empresa_id'] = $empresaId;
                $stmt = $db->prepare('
                    INSERT INTO contas_bancarias
                        (empresa_id, descricao, tipo, banco, agencia, numero_conta, digito,
                         titular, cpf_cnpj_titular, saldo_inicial, data_saldo_inicial, observacoes, ativo)
                    VALUES
                        (:empresa_id, :descricao, :tipo, :banco, :agencia, :numero_conta, :digito,
                         :titular, :cpf_cnpj_titular, :saldo_inicial, :data_saldo_inicial, :observacoes, :ativo)
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Conta bancária criada.');
            }
        } catch (PDOException $e) {
            error_log('[ContasBancarias] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar conta bancária.');
        }

        redirect('contas_bancarias.php');
    }

    /**
     * POST /conta_bancaria_acao.php
     * Ações: ativar, desativar, excluir
     */
    public function acao(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'contas_bancarias.php');

        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('contas_bancarias.php');
        }

        $db = Database::getConnection();

        try {
            if ($acao === 'excluir') {
                // Verifica se há movimentações
                $stmtCheck = $db->prepare('SELECT COUNT(*) AS total FROM movimentacoes_bancarias WHERE conta_bancaria_id = ?');
                $stmtCheck->execute([$id]);
                $temMov = $stmtCheck->fetch()['total'] > 0;

                if ($temMov) {
                    // Inativa em vez de excluir
                    $stmt = $db->prepare('UPDATE contas_bancarias SET ativo = 0 WHERE id = ? AND empresa_id = ?');
                    $stmt->execute([$id, $empresaId]);
                    Flash::set('aviso', 'Conta possui movimentações. Foi inativada em vez de excluída.');
                } else {
                    $stmt = $db->prepare('DELETE FROM contas_bancarias WHERE id = ? AND empresa_id = ?');
                    $stmt->execute([$id, $empresaId]);
                    Flash::set('sucesso', 'Conta bancária excluída.');
                }
            } elseif ($acao === 'ativar') {
                $stmt = $db->prepare('UPDATE contas_bancarias SET ativo = 1 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Conta ativada.');
            } elseif ($acao === 'desativar') {
                $stmt = $db->prepare('UPDATE contas_bancarias SET ativo = 0 WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Conta desativada.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }
        } catch (PDOException $e) {
            error_log('[ContasBancarias] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('contas_bancarias.php');
    }

    /**
     * Calcula o saldo atual de uma conta em uma data de referência.
     * (Estático, usado também em outros controllers.)
     *
     * @param int $contaId
     * @param string|null $ateData Data ISO yyyy-mm-dd, ou null = hoje
     * @return float
     */
    public static function calcularSaldo(int $contaId, ?string $ateData = null): float
    {
        $ateData = $ateData ?? date('Y-m-d');
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT
                cb.saldo_inicial,
                cb.data_saldo_inicial,
                COALESCE(SUM(CASE WHEN m.tipo = "entrada" THEN m.valor ELSE 0 END), 0) AS total_entradas,
                COALESCE(SUM(CASE WHEN m.tipo = "saida"   THEN m.valor ELSE 0 END), 0) AS total_saidas
            FROM contas_bancarias cb
            LEFT JOIN movimentacoes_bancarias m
                ON m.conta_bancaria_id = cb.id
                AND m.data_movimento >= cb.data_saldo_inicial
                AND m.data_movimento <= ?
            WHERE cb.id = ?
            GROUP BY cb.id
        ');
        $stmt->execute([$ateData, $contaId]);
        $row = $stmt->fetch();

        if (!$row) {
            return 0.0;
        }

        return (float)$row['saldo_inicial']
             + (float)$row['total_entradas']
             - (float)$row['total_saidas'];
    }
}