<?php
/**
 * ContasPagarController - CRUD de Contas a Pagar (com integração bancária)
 *
 * Baseado no controller original do sistema contas_pagar, com adaptação:
 *   - ao PAGAR uma conta → gera movimentação de SAÍDA na conta bancária selecionada
 *   - novos campos: conta_bancaria_id (no pagamento)
 *
 * Suporta parcelamento e recorrência.
 */

declare(strict_types=1);

final class ContasPagarController
{
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        $status       = $_GET['status']       ?? '';
        $fornecedorId = (int)($_GET['fornecedor_id'] ?? 0);
        $categoriaId  = (int)($_GET['categoria_id']  ?? 0);
        $dataInicio   = $_GET['data_inicio']   ?? '';
        $dataFim      = $_GET['data_fim']      ?? '';

        $sql = '
            SELECT cp.*,
                   f.razao_social AS fornecedor_nome,
                   cat.nome AS categoria_nome,
                   cat.cor AS categoria_cor,
                   cb.descricao AS conta_bancaria_descricao,
                   u.nome AS usuario_criacao_nome
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            JOIN categorias cat ON cat.id = cp.categoria_id
            LEFT JOIN contas_bancarias cb ON cb.id = cp.conta_bancaria_id
            JOIN usuarios u ON u.id = cp.usuario_criacao_id
            WHERE cp.empresa_id = ?
        ';
        $params = [$empresaId];

        if (in_array($status, ['pendente', 'aprovada', 'paga', 'cancelada'], true)) {
            $sql .= ' AND cp.status = ?';
            $params[] = $status;
        }
        if ($fornecedorId > 0) {
            $sql .= ' AND cp.fornecedor_id = ?';
            $params[] = $fornecedorId;
        }
        if ($categoriaId > 0) {
            $sql .= ' AND cp.categoria_id = ?';
            $params[] = $categoriaId;
        }
        if (!empty($dataInicio)) {
            $sql .= ' AND cp.data_vencimento >= ?';
            $params[] = $dataInicio;
        }
        if (!empty($dataFim)) {
            $sql .= ' AND cp.data_vencimento <= ?';
            $params[] = $dataFim;
        }

        $sql .= ' ORDER BY cp.status, cp.data_vencimento ASC';

        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contas = $stmt->fetchAll();

        $fornecedores = $this->listarFornecedores($empresaId);
        $categorias   = $this->listarCategorias($empresaId);
        $resumo       = $this->calcularResumo($empresaId);

        layout('Contas a Pagar', 'contas_pagar/index.php', [
            'contas'        => $contas,
            'fornecedores'  => $fornecedores,
            'categorias'    => $categorias,
            'resumo'        => $resumo,
            'filtros'       => [
                'status'       => $status,
                'fornecedor_id'=> $fornecedorId,
                'categoria_id' => $categoriaId,
                'data_inicio'  => $dataInicio,
                'data_fim'     => $dataFim,
            ],
        ]);
    }

    public function form(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $conta = null;
        $empresaId = Auth::user()['empresa_id'];

        if ($id > 0) {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT * FROM contas_pagar WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $conta = $stmt->fetch();

            if (!$conta) {
                Flash::set('erro', 'Conta a pagar não encontrada.');
                redirect('contas_pagar.php');
            }
        }

        $fornecedores = $this->listarFornecedores($empresaId);
        $categorias   = $this->listarCategorias($empresaId);
        $contasBanco  = $this->listarContasBancarias($empresaId);

        layout($conta ? 'Editar Conta a Pagar' : 'Nova Conta a Pagar', 'contas_pagar/form.php', [
            'conta'         => $conta,
            'fornecedores'  => $fornecedores,
            'categorias'    => $categorias,
            'contasBanco'   => $contasBanco,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_pagar.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);
        $parcelas = max(1, (int)($_POST['parcelas'] ?? 1));

        if (empty(trim($_POST['descricao'] ?? ''))) {
            Flash::set('erro', 'Descrição é obrigatória.');
            redirect($id > 0 ? "conta_form.php?id=$id" : 'conta_form.php');
        }
        if (!is_numeric($_POST['valor'] ?? '') || (float)$_POST['valor'] <= 0) {
            Flash::set('erro', 'Valor inválido.');
            redirect($id > 0 ? "conta_form.php?id=$id" : 'conta_form.php');
        }
        if (empty($_POST['data_vencimento'] ?? '')) {
            Flash::set('erro', 'Data de vencimento é obrigatória.');
            redirect($id > 0 ? "conta_form.php?id=$id" : 'conta_form.php');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            if ($id > 0) {
                $dados = $this->coletarDadosFormulario($empresaId);
                $dados['id'] = $id;
                $stmt = $db->prepare('
                    UPDATE contas_pagar SET
                        fornecedor_id=:fornecedor_id, categoria_id=:categoria_id, descricao=:descricao,
                        numero_documento=:numero_documento, valor=:valor, data_emissao=:data_emissao,
                        data_vencimento=:data_vencimento, forma_pagamento=:forma_pagamento,
                        observacoes=:observacoes
                    WHERE id=:id AND empresa_id=:empresa_id
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Conta a pagar atualizada.');
            } else {
                if ($parcelas > 1) {
                    $dados = $this->coletarDadosFormulario($empresaId);
                    $dados['empresa_id']     = $empresaId;
                    $dados['parcelas']       = $parcelas;
                    $dados['parcela_atual']  = 1;
                    $dados['status']         = 'pendente';
                    $dados['usuario_criacao_id'] = Auth::user()['id'];

                    $stmt = $db->prepare('
                        INSERT INTO contas_pagar
                            (empresa_id, fornecedor_id, categoria_id, descricao, numero_documento, valor,
                             data_emissao, data_vencimento, forma_pagamento, observacoes,
                             parcelas, parcela_atual, status, usuario_criacao_id)
                        VALUES
                            (:empresa_id, :fornecedor_id, :categoria_id, :descricao, :numero_documento, :valor,
                             :data_emissao, :data_vencimento, :forma_pagamento, :observacoes,
                             :parcelas, :parcela_atual, :status, :usuario_criacao_id)
                    ');
                    $stmt->execute($dados);
                    $contaPaiId = (int)$db->lastInsertId();

                    $valorParcela = round($dados['valor'] / $parcelas, 2);
                    $vencimentoBase = new DateTime($dados['data_vencimento']);

                    for ($i = 2; $i <= $parcelas; $i++) {
                        $vencimento = clone $vencimentoBase;
                        $vencimento->modify("+" . ($i - 1) . " months");

                        $stmtF = $db->prepare('
                            INSERT INTO contas_pagar
                                (empresa_id, fornecedor_id, categoria_id, descricao, numero_documento, valor,
                                 data_emissao, data_vencimento, forma_pagamento, observacoes,
                                 parcelas, parcela_atual, conta_pai_id, status, usuario_criacao_id)
                            VALUES
                                (:empresa_id, :fornecedor_id, :categoria_id, :descricao, :numero_documento, :valor,
                                 :data_emissao, :data_vencimento, :forma_pagamento, :observacoes,
                             :parcelas, :parcela_atual, :conta_pai_id, :status, :usuario_criacao_id)
                        ');
                        $stmtF->execute([
                            'empresa_id' => $empresaId,
                            'fornecedor_id' => $dados['fornecedor_id'],
                            'categoria_id' => $dados['categoria_id'],
                            'descricao' => $dados['descricao'] . " (parcela $i/$parcelas)",
                            'numero_documento' => $dados['numero_documento'],
                            'valor' => $valorParcela,
                            'data_emissao' => $dados['data_emissao'],
                            'data_vencimento' => $vencimento->format('Y-m-d'),
                            'forma_pagamento' => $dados['forma_pagamento'],
                            'observacoes' => $dados['observacoes'],
                            'parcelas' => $parcelas,
                            'parcela_atual' => $i,
                            'conta_pai_id' => $contaPaiId,
                            'status' => 'pendente',
                            'usuario_criacao_id' => Auth::user()['id'],
                        ]);
                    }

                    $stmtUp = $db->prepare('UPDATE contas_pagar SET descricao = ? WHERE id = ?');
                    $stmtUp->execute([$dados['descricao'] . " (parcela 1/$parcelas)", $contaPaiId]);

                    Flash::set('sucesso', "Conta parcelada em $parcelas vezes.");
                } else {
                    $dados = $this->coletarDadosFormulario($empresaId);
                    $dados['empresa_id']     = $empresaId;
                    $dados['parcelas']       = 1;
                    $dados['parcela_atual']  = 1;
                    $dados['status']         = 'pendente';
                    $dados['usuario_criacao_id'] = Auth::user()['id'];

                    $stmt = $db->prepare('
                        INSERT INTO contas_pagar
                            (empresa_id, fornecedor_id, categoria_id, descricao, numero_documento, valor,
                             data_emissao, data_vencimento, forma_pagamento, observacoes,
                             parcelas, parcela_atual, status, usuario_criacao_id)
                        VALUES
                            (:empresa_id, :fornecedor_id, :categoria_id, :descricao, :numero_documento, :valor,
                             :data_emissao, :data_vencimento, :forma_pagamento, :observacoes,
                             :parcelas, :parcela_atual, :status, :usuario_criacao_id)
                    ');
                    $stmt->execute($dados);
                    Flash::set('sucesso', 'Conta a pagar criada.');
                }
            }

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[ContasPagar] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar conta a pagar.');
        }

        redirect('contas_pagar.php');
    }

    public function acao(): void
    {
        Auth::require();
        $id = (int)($_POST['id'] ?? 0);
        $acao = $_POST['acao'] ?? '';
        $empresaId = Auth::user()['empresa_id'];
        $usuarioId = Auth::user()['id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('contas_pagar.php');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM contas_pagar WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $conta = $stmt->fetch();

            if (!$conta) {
                Flash::set('erro', 'Conta não encontrada.');
                redirect('contas_pagar.php');
            }

            if ($acao === 'aprovar') {
                Permissao::requer('aprovar', 'contas_pagar.php');
                if ($conta['status'] !== 'pendente') {
                    Flash::set('erro', 'Só é possível aprovar contas pendentes.');
                    redirect('contas_pagar.php');
                }
                $stmtU = $db->prepare('UPDATE contas_pagar SET status="aprovada", usuario_aprovacao_id=?, data_aprovacao=NOW() WHERE id=?');
                $stmtU->execute([$usuarioId, $id]);
                Flash::set('sucesso', 'Conta aprovada.');
            } elseif ($acao === 'pagar') {
                Permissao::requer('pagar', 'contas_pagar.php');
                if (!in_array($conta['status'], ['pendente', 'aprovada'], true)) {
                    Flash::set('erro', 'Só é possível pagar contas pendentes ou aprovadas.');
                    redirect('contas_pagar.php');
                }

                $contaBancariaId = (int)($_POST['conta_bancaria_id'] ?? 0);
                if ($contaBancariaId <= 0) {
                    Flash::set('erro', 'Selecione a conta bancária de onde saiu o pagamento.');
                    redirect("conta_form.php?id=$id");
                }
                $dataPagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
                $valorPago     = (float)str_replace(',', '.', $_POST['valor_pago'] ?? $conta['valor']);

                // Verifica saldo disponível
                $saldoAtual = ContasBancariasController::calcularSaldo($contaBancariaId, $dataPagamento);
                if ($valorPago > $saldoAtual) {
                    Flash::set('erro', sprintf(
                        'Saldo insuficiente na conta. Disponível: R$ %s, necessário: R$ %s.',
                        number_format($saldoAtual, 2, ',', '.'),
                        number_format($valorPago, 2, ',', '.')
                    ));
                    redirect("conta_form.php?id=$id");
                }

                $stmtU = $db->prepare('
                    UPDATE contas_pagar SET
                        status="paga",
                        data_pagamento=:data_pagamento,
                        valor_pago=:valor_pago,
                        conta_bancaria_id=:conta_bancaria_id,
                        usuario_pagamento_id=:usuario_id
                    WHERE id=:id
                ');
                $stmtU->execute([
                    'data_pagamento'    => $dataPagamento,
                    'valor_pago'        => $valorPago,
                    'conta_bancaria_id' => $contaBancariaId,
                    'usuario_id'        => $usuarioId,
                    'id'                => $id,
                ]);

                // Gera movimentação automática de SAÍDA
                MovimentacoesController::lancar(
                    $empresaId,
                    $contaBancariaId,
                    $dataPagamento,
                    'saida',
                    'conta_pagar',
                    $valorPago,
                    'Pagamento: ' . $conta['descricao'],
                    $usuarioId,
                    $id,
                    null,
                    null
                );

                Flash::set('sucesso', 'Pagamento registrado e lançado no extrato.');
            } elseif ($acao === 'cancelar') {
                if ($conta['status'] === 'paga') {
                    Flash::set('erro', 'Conta já paga. Use estorno se necessário.');
                    redirect('contas_pagar.php');
                }
                $stmtU = $db->prepare('UPDATE contas_pagar SET status="cancelada" WHERE id=?');
                $stmtU->execute([$id]);
                Flash::set('sucesso', 'Conta cancelada.');
            } elseif ($acao === 'excluir') {
                Permissao::requer('excluir', 'contas_pagar.php');
                if ($conta['status'] === 'paga') {
                    Flash::set('erro', 'Não é possível excluir conta paga.');
                    redirect('contas_pagar.php');
                }
                $stmtD = $db->prepare('DELETE FROM contas_pagar WHERE id = ? AND empresa_id = ?');
                $stmtD->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Conta excluída.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[ContasPagar] Erro na ação: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('contas_pagar.php');
    }

    public function detalhe(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            redirect('contas_pagar.php');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT cp.*,
                   f.razao_social AS fornecedor_nome, f.cnpj AS fornecedor_doc,
                   cat.nome AS categoria_nome, cat.cor AS categoria_cor,
                   cb.descricao AS conta_bancaria_descricao,
                   u1.nome AS usuario_criacao_nome,
                   u2.nome AS usuario_aprovacao_nome,
                   u3.nome AS usuario_pagamento_nome
            FROM contas_pagar cp
            JOIN fornecedores f ON f.id = cp.fornecedor_id
            JOIN categorias cat ON cat.id = cp.categoria_id
            LEFT JOIN contas_bancarias cb ON cb.id = cp.conta_bancaria_id
            JOIN usuarios u1 ON u1.id = cp.usuario_criacao_id
            LEFT JOIN usuarios u2 ON u2.id = cp.usuario_aprovacao_id
            LEFT JOIN usuarios u3 ON u3.id = cp.usuario_pagamento_id
            WHERE cp.id = ? AND cp.empresa_id = ?
        ');
        $stmt->execute([$id, $empresaId]);
        $conta = $stmt->fetch();

        if (!$conta) {
            Flash::set('erro', 'Conta não encontrada.');
            redirect('contas_pagar.php');
        }

        $stmtA = $db->prepare('SELECT * FROM anexos WHERE tipo_origem = "conta_pagar" AND origem_id = ? ORDER BY data_upload DESC');
        $stmtA->execute([$id]);
        $anexos = $stmtA->fetchAll();

        $parcelas = [];
        if ($conta['conta_pai_id']) {
            $stmtP = $db->prepare('SELECT * FROM contas_pagar WHERE (id = ? OR conta_pai_id = ?) AND id != ? ORDER BY parcela_atual');
            $stmtP->execute([$conta['conta_pai_id'], $conta['conta_pai_id'], $id]);
            $parcelas = $stmtP->fetchAll();
        }

        layout('Detalhes da Conta a Pagar', 'contas_pagar/detalhe.php', [
            'conta'    => $conta,
            'anexos'   => $anexos,
            'parcelas' => $parcelas,
        ]);
    }

    private function coletarDadosFormulario(int $empresaId): array
    {
        return [
            'fornecedor_id'      => (int)$_POST['fornecedor_id'],
            'categoria_id'       => (int)$_POST['categoria_id'],
            'descricao'          => trim($_POST['descricao']),
            'numero_documento'   => trim($_POST['numero_documento'] ?? '') ?: null,
            'valor'              => (float)str_replace(',', '.', $_POST['valor']),
            'data_emissao'       => $_POST['data_emissao'] ?? date('Y-m-d'),
            'data_vencimento'    => $_POST['data_vencimento'],
            'forma_pagamento'    => $_POST['forma_pagamento'] ?? 'boleto',
            'observacoes'        => trim($_POST['observacoes'] ?? '') ?: null,
        ];
    }

    private function listarFornecedores(int $empresaId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, razao_social, cnpj FROM fornecedores WHERE empresa_id = ? AND ativo = 1 ORDER BY razao_social');
        $stmt->execute([$empresaId]);
        return $stmt->fetchAll();
    }

    private function listarCategorias(int $empresaId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, nome, cor FROM categorias WHERE empresa_id = ? AND ativo = 1 AND tipo IN ("despesa", "ambos") ORDER BY nome');
        $stmt->execute([$empresaId]);
        return $stmt->fetchAll();
    }

    private function listarContasBancarias(int $empresaId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, descricao, tipo, banco FROM contas_bancarias WHERE empresa_id = ? AND ativo = 1 ORDER BY descricao');
        $stmt->execute([$empresaId]);
        return $stmt->fetchAll();
    }

    private function calcularResumo(int $empresaId): array
    {
        $db = Database::getConnection();
        $hoje = date('Y-m-d');
        $semana = date('Y-m-d', strtotime('+7 days'));

        $stmt = $db->prepare('
            SELECT
                SUM(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento < ? THEN valor ELSE 0 END) AS atrasadas,
                SUM(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento BETWEEN ? AND ? THEN valor ELSE 0 END) AS proximos_7_dias,
                SUM(CASE WHEN status IN ("pendente","aprovada") THEN valor ELSE 0 END) AS total_pendente,
                SUM(CASE WHEN status = "paga" AND MONTH(data_pagamento) = MONTH(?) AND YEAR(data_pagamento) = YEAR(?) THEN valor_pago ELSE 0 END) AS pago_mes
            FROM contas_pagar
            WHERE empresa_id = ?
        ');
        $stmt->execute([$hoje, $hoje, $semana, $hoje, $hoje, $empresaId]);
        return $stmt->fetch();
    }
}