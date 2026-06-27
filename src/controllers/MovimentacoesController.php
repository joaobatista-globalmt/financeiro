<?php
/**
 * MovimentacoesController - Lançamentos manuais no extrato
 *
 * Permite ao usuário lançar entradas/saídas avulsas (ex: transferência
 * entre contas próprias, juros recebidos, tarifas bancárias, etc).
 *
 * Movimentações geradas automaticamente (origem = conta_pagar/conta_receber)
 * não devem ser editadas/excluídas por aqui — apenas consultadas.
 */

declare(strict_types=1);

final class MovimentacoesController
{
    /**
     * GET /movimentacoes.php?conta_id=N
     * Extrato de uma conta específica, com filtros.
     */
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $contaId = (int)($_GET['conta_id'] ?? 0);

        if ($contaId <= 0) {
            redirect('contas_bancarias.php');
        }

        $db = Database::getConnection();

        // Carregar conta
        $stmtC = $db->prepare('SELECT * FROM contas_bancarias WHERE id = ? AND empresa_id = ?');
        $stmtC->execute([$contaId, $empresaId]);
        $conta = $stmtC->fetch();

        if (!$conta) {
            Flash::set('erro', 'Conta bancária não encontrada.');
            redirect('contas_bancarias.php');
        }

        // Filtros
        $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
        $dataFim    = $_GET['data_fim']    ?? date('Y-m-t');
        $tipo       = $_GET['tipo']        ?? '';
        $origem     = $_GET['origem']      ?? '';

        // Montar query
        $sql = '
            SELECT m.*, u.nome AS usuario_nome
            FROM movimentacoes_bancarias m
            JOIN usuarios u ON u.id = m.usuario_id
            WHERE m.empresa_id = :empresa_id
              AND m.conta_bancaria_id = :conta_id
              AND m.data_movimento BETWEEN :data_inicio AND :data_fim
        ';
        $params = [
            'empresa_id'  => $empresaId,
            'conta_id'    => $contaId,
            'data_inicio' => $dataInicio,
            'data_fim'    => $dataFim,
        ];

        if (in_array($tipo, ['entrada', 'saida'], true)) {
            $sql .= ' AND m.tipo = :tipo';
            $params['tipo'] = $tipo;
        }
        if (in_array($origem, ['manual', 'conta_pagar', 'conta_receber', 'transferencia'], true)) {
            $sql .= ' AND m.origem = :origem';
            $params['origem'] = $origem;
        }

        $sql .= ' ORDER BY m.data_movimento DESC, m.id DESC';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $movs = $stmt->fetchAll();

        // Calcular saldo atual e saldo na data fim do filtro
        $saldoAtual = ContasBancariasController::calcularSaldo($contaId, date('Y-m-d'));
        $saldoPeriodo = ContasBancariasController::calcularSaldo($contaId, $dataFim);

        // Totais do período
        $totalEntradas = array_sum(array_filter(array_column($movs, 'valor'), function ($_, $k) use ($movs) {
            return $movs[$k]['tipo'] === 'entrada';
        }, ARRAY_FILTER_USE_BOTH));

        $totalSaidas = array_sum(array_filter(array_column($movs, 'valor'), function ($_, $k) use ($movs) {
            return $movs[$k]['tipo'] === 'saida';
        }, ARRAY_FILTER_USE_BOTH));

        layout('Extrato - ' . $conta['descricao'], 'movimentacoes/index.php', [
            'conta'         => $conta,
            'movs'          => $movs,
            'saldoAtual'    => $saldoAtual,
            'saldoPeriodo'  => $saldoPeriodo,
            'totalEntradas' => $totalEntradas,
            'totalSaidas'   => $totalSaidas,
            'filtros'       => [
                'data_inicio' => $dataInicio,
                'data_fim'    => $dataFim,
                'tipo'        => $tipo,
                'origem'      => $origem,
            ],
        ]);
    }

    /**
     * GET /movimentacao_form.php?conta_id=N
     * Formulário para lançamento manual (entrada ou saída).
     */
    public function form(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $contaId = (int)($_GET['conta_id'] ?? 0);
        $id = (int)($_GET['id'] ?? 0);
        $mov = null;

        if ($contaId <= 0) {
            redirect('contas_bancarias.php');
        }

        $db = Database::getConnection();

        // Verifica conta
        $stmtC = $db->prepare('SELECT * FROM contas_bancarias WHERE id = ? AND empresa_id = ?');
        $stmtC->execute([$contaId, $empresaId]);
        $conta = $stmtC->fetch();

        if (!$conta) {
            Flash::set('erro', 'Conta bancária não encontrada.');
            redirect('contas_bancarias.php');
        }

        // Edição só permitida para origens manuais
        if ($id > 0) {
            $stmt = $db->prepare('SELECT * FROM movimentacoes_bancarias WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $mov = $stmt->fetch();

            if (!$mov) {
                Flash::set('erro', 'Movimentação não encontrada.');
                redirect("movimentacoes.php?conta_id=$contaId");
            }

            if ($mov['origem'] !== 'manual') {
                Flash::set('erro', 'Apenas movimentações manuais podem ser editadas.');
                redirect("movimentacoes.php?conta_id=$contaId");
            }
        }

        layout('Nova Movimentação', 'movimentacoes/form.php', [
            'conta' => $conta,
            'mov'   => $mov,
        ]);
    }

    /**
     * POST /movimentacao_salvar.php
     * Salva (cria ou edita) uma movimentação manual.
     */
    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_bancarias.php');

        $empresaId = Auth::user()['empresa_id'];
        $usuarioId = Auth::user()['id'];
        $id = (int)($_POST['id'] ?? 0);
        $contaId = (int)($_POST['conta_bancaria_id'] ?? 0);

        // Validações
        if (!in_array($_POST['tipo'] ?? '', ['entrada', 'saida'], true)) {
            Flash::set('erro', 'Tipo inválido.');
            redirect('contas_bancarias.php');
        }
        if (!is_numeric($_POST['valor'] ?? '') || (float)$_POST['valor'] <= 0) {
            Flash::set('erro', 'Valor deve ser positivo.');
            redirect("movimentacao_form.php?conta_id=$contaId");
        }
        if (empty(trim($_POST['descricao'] ?? ''))) {
            Flash::set('erro', 'Descrição é obrigatória.');
            redirect("movimentacao_form.php?conta_id=$contaId");
        }
        if (empty($_POST['data_movimento'] ?? '')) {
            Flash::set('erro', 'Data do movimento é obrigatória.');
            redirect("movimentacao_form.php?conta_id=$contaId");
        }

        $db = Database::getConnection();

        // Verifica conta
        $stmtC = $db->prepare('SELECT id FROM contas_bancarias WHERE id = ? AND empresa_id = ?');
        $stmtC->execute([$contaId, $empresaId]);
        if (!$stmtC->fetch()) {
            Flash::set('erro', 'Conta inválida.');
            redirect('contas_bancarias.php');
        }

        $dados = [
            'conta_bancaria_id' => $contaId,
            'data_movimento'    => $_POST['data_movimento'],
            'tipo'              => $_POST['tipo'],
            'origem'            => 'manual',
            'valor'             => (float)str_replace(',', '.', $_POST['valor']),
            'descricao'         => trim($_POST['descricao']),
            'usuario_id'        => $usuarioId,
        ];

        try {
            if ($id > 0) {
                // Edição (somente se origem = manual)
                $stmtChk = $db->prepare('SELECT origem FROM movimentacoes_bancarias WHERE id = ? AND empresa_id = ?');
                $stmtChk->execute([$id, $empresaId]);
                $origemAtual = $stmtChk->fetchColumn();

                if ($origemAtual !== 'manual') {
                    Flash::set('erro', 'Apenas movimentações manuais podem ser editadas.');
                    redirect("movimentacoes.php?conta_id=$contaId");
                }

                $stmt = $db->prepare('
                    UPDATE movimentacoes_bancarias SET
                        conta_bancaria_id=:conta_bancaria_id,
                        data_movimento=:data_movimento,
                        tipo=:tipo,
                        valor=:valor,
                        descricao=:descricao
                    WHERE id=:id AND empresa_id=:empresa_id AND origem="manual"
                ');
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                Flash::set('sucesso', 'Movimentação atualizada.');
            } else {
                // Criação
                $stmt = $db->prepare('
                    INSERT INTO movimentacoes_bancarias
                        (empresa_id, conta_bancaria_id, data_movimento, tipo, origem,
                         valor, descricao, usuario_id)
                    VALUES
                        (:empresa_id, :conta_bancaria_id, :data_movimento, :tipo, :origem,
                         :valor, :descricao, :usuario_id)
                ');
                $dados['empresa_id'] = $empresaId;
                $stmt->execute($dados);
                Flash::set('sucesso', 'Movimentação lançada.');
            }
        } catch (PDOException $e) {
            error_log('[Movimentacoes] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar movimentação.');
        }

        redirect("movimentacoes.php?conta_id=$contaId");
    }

    /**
     * POST /movimentacao_acao.php
     * Excluir movimentação manual.
     */
    public function acao(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'contas_bancarias.php');

        $id = (int)($_POST['id'] ?? 0);
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('contas_bancarias.php');
        }

        $db = Database::getConnection();

        try {
            // Só permite excluir manuais
            $stmt = $db->prepare('DELETE FROM movimentacoes_bancarias WHERE id = ? AND empresa_id = ? AND origem = "manual"');
            $stmt->execute([$id, $empresaId]);

            if ($stmt->rowCount() > 0) {
                Flash::set('sucesso', 'Movimentação excluída.');
            } else {
                Flash::set('erro', 'Movimentação não pode ser excluída (apenas manuais).');
            }
        } catch (PDOException $e) {
            error_log('[Movimentacoes] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao excluir movimentação.');
        }

        $contaId = (int)($_POST['conta_id'] ?? 0);
        if ($contaId > 0) {
            redirect("movimentacoes.php?conta_id=$contaId");
        } else {
            redirect('contas_bancarias.php');
        }
    }

    /**
     * Lança movimentação automática (usado por outros controllers).
     * Retorna ID da movimentação criada, ou false em caso de erro.
     *
     * @param int $empresaId
     * @param int $contaId
     * @param string $dataMovimento
     * @param string $tipo entrada|saida
     * @param string $origem conta_pagar|conta_receber|transferencia
     * @param float $valor
     * @param string $descricao
     * @param int $usuarioId
     * @param int|null $contaPagarId
     * @param int|null $contaReceberId
     * @param string|null $transferenciaId
     * @return int|false
     */
    public static function lancar(
        int $empresaId,
        int $contaId,
        string $dataMovimento,
        string $tipo,
        string $origem,
        float $valor,
        string $descricao,
        int $usuarioId,
        ?int $contaPagarId = null,
        ?int $contaReceberId = null,
        ?string $transferenciaId = null
    ): int|false {
        if (!in_array($tipo, ['entrada', 'saida'], true)) {
            return false;
        }

        $db = Database::getConnection();
        try {
            $stmt = $db->prepare('
                INSERT INTO movimentacoes_bancarias
                    (empresa_id, conta_bancaria_id, data_movimento, tipo, origem,
                     valor, descricao, conta_pagar_id, conta_receber_id, transferencia_id, usuario_id)
                VALUES
                    (:empresa_id, :conta_bancaria_id, :data_movimento, :tipo, :origem,
                     :valor, :descricao, :conta_pagar_id, :conta_receber_id, :transferencia_id, :usuario_id)
            ');
            $stmt->execute([
                'empresa_id'        => $empresaId,
                'conta_bancaria_id' => $contaId,
                'data_movimento'    => $dataMovimento,
                'tipo'              => $tipo,
                'origem'            => $origem,
                'valor'             => $valor,
                'descricao'         => $descricao,
                'conta_pagar_id'    => $contaPagarId,
                'conta_receber_id'  => $contaReceberId,
                'transferencia_id'  => $transferenciaId,
                'usuario_id'        => $usuarioId,
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            error_log('[Movimentacoes] Erro ao lançar automática: ' . $e->getMessage());
            return false;
        }
    }
}