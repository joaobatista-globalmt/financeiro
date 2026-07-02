<?php
/**
 * ContasReceberController - CRUD de Contas a Receber
 *
 * Espelho do ContasPagarController, mas com:
 *   - clientes no lugar de fornecedores
 *   - status 'recebida' no lugar de 'paga'
 *   - data_recebimento / valor_recebido
 *   - ao receber: gera movimentação de ENTRADA na conta bancária
 *
 * Suporta parcelamento e recorrência (mesmo padrão).
 */

declare(strict_types=1);

final class ContasReceberController
{
    /**
     * Lista contas a receber com filtros.
     */
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        // Filtros
        $status      = $_GET['status']      ?? '';
        $clienteId   = (int)($_GET['cliente_id']   ?? 0);
        $categoriaId = (int)($_GET['categoria_id'] ?? 0);
        $dataInicio  = $_GET['data_inicio']  ?? '';
        $dataFim     = $_GET['data_fim']     ?? '';

        $sql = '
            SELECT cr.*,
                   c.razao_social AS cliente_nome,
                   cat.nome AS categoria_nome,
                   cat.cor AS categoria_cor,
                   cb.descricao AS conta_bancaria_descricao,
                   u.nome AS usuario_criacao_nome
            FROM contas_receber cr
            JOIN clientes c     ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            LEFT JOIN contas_bancarias cb ON cb.id = cr.conta_bancaria_id
            JOIN usuarios u ON u.id = cr.usuario_criacao_id
            WHERE cr.empresa_id = ?
        ';
        $params = [$empresaId];

        if (in_array($status, ['pendente', 'aprovada', 'recebida', 'cancelada'], true)) {
            $sql .= ' AND cr.status = ?';
            $params[] = $status;
        }
        if ($clienteId > 0) {
            $sql .= ' AND cr.cliente_id = ?';
            $params[] = $clienteId;
        }
        if ($categoriaId > 0) {
            $sql .= ' AND cr.categoria_id = ?';
            $params[] = $categoriaId;
        }
        if (!empty($dataInicio)) {
            $sql .= ' AND cr.data_vencimento >= ?';
            $params[] = $dataInicio;
        }
        if (!empty($dataFim)) {
            $sql .= ' AND cr.data_vencimento <= ?';
            $params[] = $dataFim;
        }

        $sql .= ' ORDER BY cr.status, cr.data_vencimento ASC';

        $db = Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $contas = $stmt->fetchAll();

        // Para os selects de filtro
        $clientes   = $this->listarClientes($empresaId);
        $categorias = $this->listarCategorias($empresaId);

        // Cards resumo
        $resumo = $this->calcularResumo($empresaId);

        layout('Contas a Receber', 'contas_receber/index.php', [
            'contas'      => $contas,
            'clientes'    => $clientes,
            'categorias'  => $categorias,
            'resumo'      => $resumo,
            'filtros'     => [
                'status'       => $status,
                'cliente_id'   => $clienteId,
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
            $stmt = $db->prepare('SELECT * FROM contas_receber WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $conta = $stmt->fetch();

            if (!$conta) {
                Flash::set('erro', 'Conta a receber não encontrada.');
                redirect('contas_receber.php');
            }
        }

        $clientes     = $this->listarClientes($empresaId);
        $categorias   = $this->listarCategorias($empresaId, true);
        $contasBanco  = $this->listarContasBancarias($empresaId);

        layout($conta ? 'Editar Conta a Receber' : 'Nova Conta a Receber', 'contas_receber/form.php', [
            'conta'        => $conta,
            'clientes'     => $clientes,
            'categorias'   => $categorias,
            'contasBanco'  => $contasBanco,
        ]);
    }

    public function salvar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_receber.php');

        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_POST['id'] ?? 0);
        $parcelas = max(1, (int)($_POST['parcelas'] ?? 1));

        if (empty(trim($_POST['descricao'] ?? ''))) {
            Flash::set('erro', 'Descrição é obrigatória.');
            redirect($id > 0 ? "conta_receber_form.php?id=$id" : 'conta_receber_form.php');
        }
        if (!is_numeric($_POST['valor'] ?? '') || (float)$_POST['valor'] <= 0) {
            Flash::set('erro', 'Valor inválido.');
            redirect($id > 0 ? "conta_receber_form.php?id=$id" : 'conta_receber_form.php');
        }
        if (empty($_POST['data_vencimento'] ?? '')) {
            Flash::set('erro', 'Data de vencimento é obrigatória.');
            redirect($id > 0 ? "conta_receber_form.php?id=$id" : 'conta_receber_form.php');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            if ($id > 0) {
                // Edição simples (não mexe em parcelas já geradas — só na conta-pai)
                $dados = $this->coletarDadosFormulario($empresaId);
                $dados['id'] = $id;
                $dados['empresa_id'] = $empresaId;
                $stmt = $db->prepare('
                    UPDATE contas_receber SET
                        cliente_id=:cliente_id, categoria_id=:categoria_id, descricao=:descricao,
                        numero_documento=:numero_documento, valor=:valor, data_emissao=:data_emissao,
                        data_vencimento=:data_vencimento, forma_recebimento=:forma_recebimento,
                        observacoes=:observacoes
                    WHERE id=:id AND empresa_id=:empresa_id
                ');
                $stmt->execute($dados);
                Flash::set('sucesso', 'Conta a receber atualizada.');
            } else {
                if ($parcelas > 1) {
                    // Criar conta-pai + N filhas
                    $dados = $this->coletarDadosFormulario($empresaId);
                    $dados['empresa_id']     = $empresaId;
                    $dados['parcelas']       = $parcelas;
                    $dados['parcela_atual']  = 1;
                    $dados['status']         = 'pendente';
                    $dados['usuario_criacao_id'] = Auth::user()['id'];

                    $stmt = $db->prepare('
                        INSERT INTO contas_receber
                            (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor,
                             data_emissao, data_vencimento, forma_recebimento, observacoes,
                             parcelas, parcela_atual, status, usuario_criacao_id)
                        VALUES
                            (:empresa_id, :cliente_id, :categoria_id, :descricao, :numero_documento, :valor,
                             :data_emissao, :data_vencimento, :forma_recebimento, :observacoes,
                             :parcelas, :parcela_atual, :status, :usuario_criacao_id)
                    ');
                    $stmt->execute($dados);
                    $contaPaiId = (int)$db->lastInsertId();

                    // Gerar filhas
                    $valorParcela = round($dados['valor'] / $parcelas, 2);
                    $vencimentoBase = new DateTime($dados['data_vencimento']);

                    for ($i = 2; $i <= $parcelas; $i++) {
                        $vencimento = clone $vencimentoBase;
                        $vencimento->modify("+" . ($i - 1) . " months");

                        $stmtF = $db->prepare('
                            INSERT INTO contas_receber
                                (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor,
                                 data_emissao, data_vencimento, forma_recebimento, observacoes,
                                 parcelas, parcela_atual, conta_pai_id, status, usuario_criacao_id)
                            VALUES
                                (:empresa_id, :cliente_id, :categoria_id, :descricao, :numero_documento, :valor,
                                 :data_emissao, :data_vencimento, :forma_recebimento, :observacoes,
                                 :parcelas, :parcela_atual, :conta_pai_id, :status, :usuario_criacao_id)
                        ');
                        $stmtF->execute([
                            'empresa_id' => $empresaId,
                            'cliente_id' => $dados['cliente_id'],
                            'categoria_id' => $dados['categoria_id'],
                            'descricao' => $dados['descricao'] . " (parcela $i/$parcelas)",
                            'numero_documento' => $dados['numero_documento'],
                            'valor' => $valorParcela,
                            'data_emissao' => $dados['data_emissao'],
                            'data_vencimento' => $vencimento->format('Y-m-d'),
                            'forma_recebimento' => $dados['forma_recebimento'],
                            'observacoes' => $dados['observacoes'],
                            'parcelas' => $parcelas,
                            'parcela_atual' => $i,
                            'conta_pai_id' => $contaPaiId,
                            'status' => 'pendente',
                            'usuario_criacao_id' => Auth::user()['id'],
                        ]);
                    }

                    // Atualiza descrição da pai também
                    $stmtUp = $db->prepare('UPDATE contas_receber SET descricao = ? WHERE id = ?');
                    $stmtUp->execute([$dados['descricao'] . " (parcela 1/$parcelas)", $contaPaiId]);

                    Flash::set('sucesso', "Conta a receber parcelada em $parcelas vezes.");
                } else {
                    // Criação simples
                    $dados = $this->coletarDadosFormulario($empresaId);
                    $dados['empresa_id']     = $empresaId;
                    $dados['parcelas']       = 1;
                    $dados['parcela_atual']  = 1;
                    $dados['status']         = 'pendente';
                    $dados['usuario_criacao_id'] = Auth::user()['id'];

                    $stmt = $db->prepare('
                        INSERT INTO contas_receber
                            (empresa_id, cliente_id, categoria_id, descricao, numero_documento, valor,
                             data_emissao, data_vencimento, forma_recebimento, observacoes,
                             parcelas, parcela_atual, status, usuario_criacao_id)
                        VALUES
                            (:empresa_id, :cliente_id, :categoria_id, :descricao, :numero_documento, :valor,
                             :data_emissao, :data_vencimento, :forma_recebimento, :observacoes,
                             :parcelas, :parcela_atual, :status, :usuario_criacao_id)
                    ');
                    $stmt->execute($dados);
                    Flash::set('sucesso', 'Conta a receber criada.');
                }
            }

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[ContasReceber] Erro: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar conta a receber.');
        }

        redirect('contas_receber.php');
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
            redirect('contas_receber.php');
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('SELECT * FROM contas_receber WHERE id = ? AND empresa_id = ?');
            $stmt->execute([$id, $empresaId]);
            $conta = $stmt->fetch();

            if (!$conta) {
                Flash::set('erro', 'Conta não encontrada.');
                redirect('contas_receber.php');
            }

            if ($acao === 'aprovar') {
                Permissao::requer('aprovar', 'contas_receber.php');
                if ($conta['status'] !== 'pendente') {
                    Flash::set('erro', 'Só é possível aprovar contas pendentes.');
                    redirect('contas_receber.php');
                }
                $stmtU = $db->prepare('UPDATE contas_receber SET status="aprovada", usuario_aprovacao_id=?, data_aprovacao=NOW() WHERE id=?');
                $stmtU->execute([$usuarioId, $id]);
                Flash::set('sucesso', 'Conta aprovada.');
            } elseif ($acao === 'receber') {
                Permissao::requer('receber', 'contas_receber.php');
                if (!in_array($conta['status'], ['pendente', 'aprovada'], true)) {
                    Flash::set('erro', 'Só é possível receber contas pendentes ou aprovadas.');
                    redirect('contas_receber.php');
                }

                $contaBancariaId = (int)($_POST['conta_bancaria_id'] ?? 0);
                if ($contaBancariaId <= 0) {
                    Flash::set('erro', 'Selecione a conta bancária que recebeu o valor.');
                    redirect("conta_receber_form.php?id=$id");
                }
                $dataRecebimento = $_POST['data_recebimento'] ?? date('Y-m-d');
                $valorRecebido   = (float)str_replace(',', '.', $_POST['valor_recebido'] ?? $conta['valor']);

                // Atualiza conta
                $stmtU = $db->prepare('
                    UPDATE contas_receber SET
                        status="recebida",
                        data_recebimento=:data_recebimento,
                        valor_recebido=:valor_recebido,
                        conta_bancaria_id=:conta_bancaria_id,
                        usuario_recebimento_id=:usuario_id
                    WHERE id=:id
                ');
                $stmtU->execute([
                    'data_recebimento'  => $dataRecebimento,
                    'valor_recebido'    => $valorRecebido,
                    'conta_bancaria_id' => $contaBancariaId,
                    'usuario_id'        => $usuarioId,
                    'id'                => $id,
                ]);

                // Gera movimentação automática de entrada
                MovimentacoesController::lancar(
                    $empresaId,
                    $contaBancariaId,
                    $dataRecebimento,
                    'entrada',
                    'conta_receber',
                    $valorRecebido,
                    'Recebimento: ' . $conta['descricao'],
                    $usuarioId,
                    null,
                    $id,
                    null
                );

                Flash::set('sucesso', 'Recebimento registrado e lançado no extrato.');
            } elseif ($acao === 'cancelar') {
                if (in_array($conta['status'], ['recebida'], true)) {
                    Flash::set('erro', 'Conta já recebida. Use estorno se necessário.');
                    redirect('contas_receber.php');
                }
                $stmtU = $db->prepare('UPDATE contas_receber SET status="cancelada" WHERE id=?');
                $stmtU->execute([$id]);
                Flash::set('sucesso', 'Conta cancelada.');
            } elseif ($acao === 'excluir') {
                Permissao::requer('excluir', 'contas_receber.php');
                if ($conta['status'] === 'recebida') {
                    Flash::set('erro', 'Não é possível excluir conta recebida.');
                    redirect('contas_receber.php');
                }
                $stmtD = $db->prepare('DELETE FROM contas_receber WHERE id = ? AND empresa_id = ?');
                $stmtD->execute([$id, $empresaId]);
                Flash::set('sucesso', 'Conta excluída.');
            } else {
                Flash::set('erro', 'Ação inválida.');
            }

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('[ContasReceber] Erro na ação: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao executar ação.');
        }

        redirect('contas_receber.php');
    }

    /**
     * GET /editar_recebimento.php?id=N
     * Form para editar recebimento de uma conta a receber já RECEBIDA.
     */
    public function editarRecebimento(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_receber.php');
        $id = (int)($_GET['id'] ?? 0);
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            redirect('contas_receber.php');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT cr.*, c.razao_social AS cliente_nome,
                   cb.descricao AS conta_bancaria_descricao
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            LEFT JOIN contas_bancarias cb ON cb.id = cr.conta_bancaria_id
            WHERE cr.id = ? AND cr.empresa_id = ?
        ');
        $stmt->execute([$id, $empresaId]);
        $conta = $stmt->fetch();

        if (!$conta) {
            Flash::set('erro', 'Conta não encontrada.');
            redirect('contas_receber.php');
        }

        if ($conta['status'] !== 'recebida') {
            Flash::set('erro', 'Só é possível editar recebimento de contas já recebidas. Status atual: ' . $conta['status']);
            redirect("conta_receber_detalhe.php?id=$id");
        }

        // Lista contas bancárias ativas
        $stmtCb = $db->prepare('
            SELECT id, descricao, tipo, banco FROM contas_bancarias
            WHERE empresa_id = ? AND ativo = 1
            ORDER BY descricao
        ');
        $stmtCb->execute([$empresaId]);
        $contasBanco = $stmtCb->fetchAll();

        layout('Editar Recebimento', 'contas_receber/editar_recebimento.php', [
            'conta'       => $conta,
            'contasBanco' => $contasBanco,
        ]);
    }

    /**
     * POST /editar_recebimento.php (Contas a Receber)
     * Salva edição do recebimento (data, valor, conta, forma).
     * Atualiza também a movimentação bancária correspondente.
     */
    public function salvarEdicaoRecebimento(): void
    {
        Auth::require();
        Permissao::requer('criar', 'contas_receber.php');
        $id = (int)($_POST['id'] ?? 0);
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            Flash::set('erro', 'ID inválido.');
            redirect('contas_receber.php');
        }

        $novaData     = $_POST['data_recebimento'] ?? '';
        $novoValor    = (float)str_replace(',', '.', $_POST['valor_recebido'] ?? 0);
        $novaContaId  = (int)($_POST['conta_bancaria_id'] ?? 0);
        $novaForma    = $_POST['forma_recebimento'] ?? '';

        if (empty($novaData) || $novoValor <= 0 || $novaContaId <= 0) {
            Flash::set('erro', 'Preencha data, valor e conta bancária.');
            redirect("editar_recebimento.php?id=$id");
        }
        if (!in_array($novaForma, ['boleto','pix','transferencia','dinheiro','cartao','cheque','outros'], true)) {
            Flash::set('erro', 'Forma de recebimento inválida.');
            redirect("editar_recebimento.php?id=$id");
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // Carrega a conta
            $stmt = $db->prepare('SELECT * FROM contas_receber WHERE id = ? AND empresa_id = ? FOR UPDATE');
            $stmt->execute([$id, $empresaId]);
            $conta = $stmt->fetch();

            if (!$conta) {
                throw new \RuntimeException('Conta não encontrada.');
            }
            if ($conta['status'] !== 'recebida') {
                throw new \RuntimeException('Só é possível editar recebimento de contas já recebidas.');
            }

            // Verifica conta bancária
            $stmtCb = $db->prepare('SELECT id FROM contas_bancarias WHERE id = ? AND empresa_id = ?');
            $stmtCb->execute([$novaContaId, $empresaId]);
            if (!$stmtCb->fetch()) {
                throw new \RuntimeException('Conta bancária inválida.');
            }

            // Atualiza a conta
            $stmtU = $db->prepare('
                UPDATE contas_receber SET
                    data_recebimento = :data_recebimento,
                    valor_recebido = :valor_recebido,
                    conta_bancaria_id = :conta_bancaria_id,
                    forma_recebimento = :forma_recebimento
                WHERE id = :id AND empresa_id = :empresa_id
            ');
            $stmtU->execute([
                'data_recebimento'  => $novaData,
                'valor_recebido'    => $novoValor,
                'conta_bancaria_id' => $novaContaId,
                'forma_recebimento' => $novaForma,
                'id'                => $id,
                'empresa_id'        => $empresaId,
            ]);

            // Atualiza movimentação bancária (se vinculada)
            $stmtM = $db->prepare('
                UPDATE movimentacoes_bancarias SET
                    conta_bancaria_id = :conta_bancaria_id,
                    data_movimento = :data_movimento,
                    valor = :valor,
                    descricao = :descricao
                WHERE conta_receber_id = :id AND empresa_id = :empresa_id
            ');
            $stmtM->execute([
                'conta_bancaria_id' => $novaContaId,
                'data_movimento'    => $novaData,
                'valor'             => $novoValor,
                'descricao'         => 'Recebimento: ' . $conta['descricao'],
                'id'                => $id,
                'empresa_id'        => $empresaId,
            ]);

            $db->commit();
            Flash::set('sucesso', 'Recebimento editado (conta e movimentação bancária atualizadas).');
            redirect("conta_receber_detalhe.php?id=$id");
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[ContasReceber] Erro ao editar recebimento: ' . $e->getMessage());
            Flash::set('erro', 'Erro: ' . $e->getMessage());
            redirect("editar_recebimento.php?id=$id");
        }
    }

    public function detalhe(): void
    {
        Auth::require();
        $id = (int)($_GET['id'] ?? 0);
        $empresaId = Auth::user()['empresa_id'];

        if ($id <= 0) {
            redirect('contas_receber.php');
        }

        $db = Database::getConnection();
        $stmt = $db->prepare('
            SELECT cr.*,
                   c.razao_social AS cliente_nome, c.cpf_cnpj AS cliente_doc,
                   cat.nome AS categoria_nome, cat.cor AS categoria_cor,
                   cb.descricao AS conta_bancaria_descricao,
                   u1.nome AS usuario_criacao_nome,
                   u2.nome AS usuario_aprovacao_nome,
                   u3.nome AS usuario_recebimento_nome
            FROM contas_receber cr
            JOIN clientes c ON c.id = cr.cliente_id
            JOIN categorias cat ON cat.id = cr.categoria_id
            LEFT JOIN contas_bancarias cb ON cb.id = cr.conta_bancaria_id
            JOIN usuarios u1 ON u1.id = cr.usuario_criacao_id
            LEFT JOIN usuarios u2 ON u2.id = cr.usuario_aprovacao_id
            LEFT JOIN usuarios u3 ON u3.id = cr.usuario_recebimento_id
            WHERE cr.id = ? AND cr.empresa_id = ?
        ');
        $stmt->execute([$id, $empresaId]);
        $conta = $stmt->fetch();

        if (!$conta) {
            Flash::set('erro', 'Conta não encontrada.');
            redirect('contas_receber.php');
        }

        // Anexos
        $stmtA = $db->prepare('SELECT * FROM anexos WHERE tipo_origem = "conta_receber" AND origem_id = ? ORDER BY data_upload DESC');
        $stmtA->execute([$id]);
        $anexos = $stmtA->fetchAll();

        // Parcelas relacionadas
        $parcelas = [];
        if ($conta['conta_pai_id']) {
            $stmtP = $db->prepare('SELECT * FROM contas_receber WHERE (id = ? OR conta_pai_id = ?) AND id != ? ORDER BY parcela_atual');
            $stmtP->execute([$conta['conta_pai_id'], $conta['conta_pai_id'], $id]);
            $parcelas = $stmtP->fetchAll();
        }

        layout('Detalhes da Conta a Receber', 'contas_receber/detalhe.php', [
            'conta'    => $conta,
            'anexos'   => $anexos,
            'parcelas' => $parcelas,
        ]);
    }

    private function coletarDadosFormulario(int $empresaId): array
    {
        return [
            'cliente_id'          => (int)$_POST['cliente_id'],
            'categoria_id'        => (int)$_POST['categoria_id'],
            'descricao'           => trim($_POST['descricao']),
            'numero_documento'    => trim($_POST['numero_documento'] ?? '') ?: null,
            'valor'               => (float)str_replace(',', '.', $_POST['valor']),
            'data_emissao'        => $_POST['data_emissao'] ?? date('Y-m-d'),
            'data_vencimento'     => $_POST['data_vencimento'],
            'forma_recebimento'   => $_POST['forma_recebimento'] ?? 'boleto',
            'observacoes'         => trim($_POST['observacoes'] ?? '') ?: null,
        ];
    }

    private function listarClientes(int $empresaId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id, razao_social, cpf_cnpj, pix_chave, pix_tipo FROM clientes WHERE empresa_id = ? AND ativo = 1 ORDER BY razao_social');
        $stmt->execute([$empresaId]);
        return $stmt->fetchAll();
    }

    private function listarCategorias(int $empresaId, bool $receita = false): array
    {
        $db = Database::getConnection();
        $sql = 'SELECT id, nome, cor FROM categorias WHERE empresa_id = ? AND ativo = 1';
        if ($receita) {
            $sql .= ' AND tipo IN ("receita", "ambos")';
        }
        $sql .= ' ORDER BY nome';
        $stmt = $db->prepare($sql);
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
                SUM(CASE WHEN status = "recebida" AND MONTH(data_recebimento) = MONTH(?) AND YEAR(data_recebimento) = YEAR(?) THEN valor_recebido ELSE 0 END) AS recebido_mes
            FROM contas_receber
            WHERE empresa_id = ?
        ');
        $stmt->execute([$hoje, $hoje, $semana, $hoje, $hoje, $empresaId]);
        return $stmt->fetch();
    }
}