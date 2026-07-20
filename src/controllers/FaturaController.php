<?php
/**
 * FaturaController - Gestao de Faturas Mensais (Fase 2)
 *
 * Fluxo:
 *  1. Listar faturas (com filtros por mes/cliente/status)
 *  2. Formulario de GERACAO em lote para um mes de referencia
 *  3. Processar geracao: para cada cliente com servicos ativos no mes,
 *     cria 1 fatura + N itens (snapshot do servico)
 *  4. Visualizar fatura (detalhe + itens)
 *  5. Marcar como paga / cancelar / excluir
 *
 * Padrao segue ClientesController:
 *  - Auth::require() + Permissao::requer('criar'|'editar'|'excluir', 'faturas.php')
 *  - Multi-tenant via empresa_id
 *  - Layout centralizado em src/views/faturas/
 */

declare(strict_types=1);

final class FaturaController
{
    /**
     * Lista faturas com filtros (mes_referencia, cliente_id, status).
     * GET faturas.php?mes=YYYY-MM&cliente_id=N&status=X
     */
    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        // Filtros
        $mes        = (string)($_GET['mes']        ?? date('Y-m'));
        $clienteId  = (int)   ($_GET['cliente_id'] ?? 0);
        $status     = (string)($_GET['status']     ?? '');

        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }

        // Monta query com filtros
        $where  = ['f.empresa_id = ?', 'f.mes_referencia = ?'];
        $params = [$empresaId, $mes];

        if ($clienteId > 0) {
            $where[] = 'f.cliente_id = ?';
            $params[] = $clienteId;
        }
        if ($status !== '' && in_array($status, ['aberta','paga','parcial','cancelada','vencida'], true)) {
            $where[] = 'f.status = ?';
            $params[] = $status;
        }

        $sql = "
            SELECT f.*, c.razao_social AS cliente_nome, c.cpf_cnpj AS cliente_doc
            FROM faturas f
            JOIN clientes c ON c.id = f.cliente_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY f.data_vencimento ASC, c.razao_social ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $faturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Resumo do mes
        $stmtRes = $db->prepare("
            SELECT
                COUNT(*) AS qtd,
                COALESCE(SUM(valor_total), 0) AS total,
                COALESCE(SUM(CASE WHEN status = 'paga' THEN valor_pago ELSE 0 END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN status IN ('aberta','vencida') THEN valor_total ELSE 0 END), 0) AS pendente
            FROM faturas
            WHERE empresa_id = ? AND mes_referencia = ?
        ");
        $stmtRes->execute([$empresaId, $mes]);
        $resumo = $stmtRes->fetch(PDO::FETCH_ASSOC);

        // Lista clientes pra filtro
        $cli = $db->prepare("SELECT id, razao_social FROM clientes WHERE empresa_id = ? ORDER BY razao_social");
        $cli->execute([$empresaId]);
        $clientes = $cli->fetchAll(PDO::FETCH_ASSOC);

        layout('Faturas - ' . $mes, 'faturas/index.php', [
            'faturas'   => $faturas,
            'resumo'    => $resumo,
            'clientes'  => $clientes,
            'mes'       => $mes,
            'clienteId' => $clienteId,
            'status'    => $status,
        ]);
    }

    /**
     * Formulario de GERACAO de faturas para um mes de referencia.
     * GET fatura_acao.php?acao=form
     */
    public function form(): void
    {
        Auth::require();
        Permissao::requer('criar', 'faturas.php');
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $mes = (string)($_GET['mes'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $mes = date('Y-m');
        }

        // Calcula janela de vigencia do mes
        $ini = $mes . '-01';
        $fim = date('Y-m-t', strtotime($ini));

        // Busca servicos ativos no mes (data_inicio <= fim AND (data_fim IS NULL OR data_fim >= ini))
        // E que ainda nao tenham fatura gerada para este mes
        $sql = "
            SELECT cs.id AS servico_id,
                   cs.cliente_id,
                   c.razao_social AS cliente_nome,
                   cs.descricao,
                   cs.valor_mensal,
                   cs.dia_vencimento,
                   cs.tipo_vencimento,
                   cs.tipo_cobranca,
                   cs.conta_bancaria_id
    FROM cliente_servicos cs
            JOIN clientes c ON c.id = cs.cliente_id
            WHERE cs.empresa_id = ?
              AND cs.ativo = 1
              AND cs.data_inicio <= ?
              AND (cs.data_fim IS NULL OR cs.data_fim >= ?)
              AND NOT EXISTS (
                  SELECT 1 FROM fatura_itens fi2
                  JOIN faturas f2 ON f2.id = fi2.fatura_id
                  WHERE fi2.cliente_servico_id = cs.id AND f2.mes_referencia = ?
              )
            ORDER BY c.razao_social, cs.descricao
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$empresaId, $fim, $ini, $mes]);
        $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Totalizadores
        $totalClientes = count(array_unique(array_column($candidatos, 'cliente_id')));
        $totalValor    = array_sum(array_column($candidatos, 'valor_mensal'));

        layout('Gerar Faturas - ' . $mes, 'faturas/form.php', [
            'mes'           => $mes,
            'ini'           => $ini,
            'fim'           => $fim,
            'candidatos'    => $candidatos,
            'totalClientes' => $totalClientes,
            'totalValor'    => $totalValor,
        ]);
    }

    /**
     * Processa GERACAO de faturas em lote.
     * POST fatura_acao.php?acao=gerar
     * Body: mes_referencia, servicos[] (ids selecionados)
     */
    /**
     * Cria 1 contas_receber a partir de uma fatura.
     * Retorna ['ok' => bool, 'id' => int|null, 'msg' => string]
     *
     * Regras:
     * - Fatura precisa estar em status 'aberta' ou 'vencida'
     * - Idempotente via numero_documento='FAT-{id}'
     * - Auto-pick categoria ativa (1a da empresa)
     * - Herda conta_bancaria_id do 1o item (cliente_servicos)
     */
    private function criarContaReceberPorFatura(int $faturaId, int $empresaId, int $usuarioId): array
    {
        $db = Database::getConnection();

        // Carrega a fatura
        $stmt = $db->prepare("
            SELECT f.*, c.razao_social AS cliente_nome
            FROM faturas f
            JOIN clientes c ON c.id = f.cliente_id
            WHERE f.id = ? AND f.empresa_id = ?
        ");
        $stmt->execute([$faturaId, $empresaId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fatura) {
            return ['ok' => false, 'id' => null, 'msg' => 'Fatura nao encontrada.'];
        }

        if (!in_array($fatura['status'], ['aberta', 'vencida'], true)) {
            return ['ok' => false, 'id' => null, 'msg' => 'Status "' . $fatura['status'] . '" nao permite gerar CR.'];
        }

        // Idempotencia
        $numeroDoc = 'FAT-' . $faturaId;
        $stmtCheck = $db->prepare("SELECT id, status FROM contas_receber WHERE numero_documento = ? AND empresa_id = ?");
        $stmtCheck->execute([$numeroDoc, $empresaId]);
        $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($existente) {
            return ['ok' => false, 'id' => (int)$existente['id'], 'msg' => "Ja existe CR #{$existente['id']} (status: {$existente['status']})"];
        }

        // 1o item -> conta_bancaria_id
        $stmtItem = $db->prepare("SELECT cliente_servico_id FROM fatura_itens WHERE fatura_id = ? LIMIT 1");
        $stmtItem->execute([$faturaId]);
        $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            return ['ok' => false, 'id' => null, 'msg' => 'Fatura sem itens.'];
        }
        $stmtServ = $db->prepare("SELECT conta_bancaria_id FROM cliente_servicos WHERE id = ?");
        $stmtServ->execute([$item['cliente_servico_id']]);
        $servico = $stmtServ->fetch(PDO::FETCH_ASSOC);
        $contaBancariaId = !empty($servico['conta_bancaria_id']) ? (int)$servico['conta_bancaria_id'] : null;

        // Auto-pick categoria ativa
        $stmtCat = $db->prepare("SELECT id FROM categorias WHERE empresa_id = ? AND ativo = 1 AND (tipo = 'receita' OR tipo = 'ambos') ORDER BY id LIMIT 1");
        $stmtCat->execute([$empresaId]);
        $categoriaId = $stmtCat->fetchColumn();
        if (!$categoriaId) {
            return ['ok' => false, 'id' => null, 'msg' => 'Sem categoria de receita cadastrada.'];
        }

        // Descricao
        $descricao = sprintf('Fatura #%d - %s - %s', $faturaId, $fatura['mes_referencia'], substr($fatura['cliente_nome'], 0, 80));

        // Insere
        try {
            $stmtIns = $db->prepare("
                INSERT INTO contas_receber (
                    empresa_id, cliente_id, categoria_id, descricao, numero_documento,
                    valor, data_emissao, data_vencimento, forma_recebimento,
                    conta_bancaria_id, status, parcelas, parcela_atual,
                    observacoes, usuario_criacao_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 1, 1, ?, ?)
            ");
            $stmtIns->execute([
                $empresaId,
                (int)$fatura['cliente_id'],
                (int)$categoriaId,
                $descricao,
                $numeroDoc,
                (float)$fatura['valor_total'],
                date('Y-m-d'),
                $fatura['data_vencimento'],
                'boleto',
                $contaBancariaId,
                'Gerado da fatura #' . $faturaId . ' em ' . date('d/m/Y H:i'),
                (int)$usuarioId,
            ]);
            $contaId = (int)$db->lastInsertId();
            return ['ok' => true, 'id' => $contaId, 'msg' => "CR #{$contaId} gerada"];
        } catch (Throwable $e) {
            return ['ok' => false, 'id' => null, 'msg' => 'Erro ao inserir: ' . $e->getMessage()];
        }
    }


    public function gerar(): void
    {
        Auth::require();
        Permissao::requer('criar', 'faturas.php');
        $empresaId = Auth::user()['empresa_id'];
        $usuarioId = Auth::user()['id'];
        $db = Database::getConnection();

        $mes       = (string)($_POST['mes_referencia'] ?? '');
        $servicos  = (array) ($_POST['servicos']       ?? []);

        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $_SESSION['flash_erro'] = 'Mes de referencia invalido.';
            header('Location: faturas.php');
            exit;
        }
        if (empty($servicos)) {
            $_SESSION['flash_erro'] = 'Selecione ao menos um servico para gerar fatura.';
            header('Location: fatura_acao.php?acao=form&mes=' . urlencode($mes));
            exit;
        }

        $ini = $mes . '-01';
        $fim = date('Y-m-t', strtotime($ini));

        // Busca os servicos selecionados
        $placeholders = implode(',', array_fill(0, count($servicos), '?'));
        $sql = "
            SELECT cs.*, c.empresa_id AS cli_empresa_id
            FROM cliente_servicos cs
            JOIN clientes c ON c.id = cs.cliente_id
            WHERE cs.id IN ($placeholders)
              AND cs.empresa_id = ?
              AND cs.ativo = 1
        ";
        $params = array_merge(array_map('intval', $servicos), [$empresaId]);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($lista)) {
            $_SESSION['flash_erro'] = 'Nenhum servico valido encontrado.';
            header('Location: faturas.php');
            exit;
        }

        // Agrupa por cliente
        $porCliente = [];
        foreach ($lista as $s) {
            $cid = (int)$s['cliente_id'];
            if (!isset($porCliente[$cid])) {
                $porCliente[$cid] = [
                    'cliente_id' => $cid,
                    'empresa_id' => $empresaId,
                    'itens'      => [],
                    'valor_total' => 0,
                ];
            }
            $porCliente[$cid]['itens'][] = $s;
            $porCliente[$cid]['valor_total'] += (float)$s['valor_mensal'];
        }

        // Calcula data de vencimento padrao (dia do mes, se <= dias do mes)
        $diasMes = (int)date('t', strtotime($ini));

        $geradas = 0;
        $puladas = 0;
        $erros  = [];

        $db->beginTransaction();
        try {
            $faturasIdsGeradas = [];
            foreach ($porCliente as $cid => $grp) {
                // Pega o primeiro servico para herdar dia_vencimento e conta_bancaria
                $primeiro = $grp['itens'][0];

                // Calcula vencimento
                $dia = (int)($primeiro['dia_vencimento'] ?? 0);
                if ($dia < 1 || $dia > $diasMes) {
                    $dia = min(15, $diasMes); // fallback: dia 15 (ou ultimo dia)
                }
                $vencimento = sprintf('%s-%02d', $mes, $dia);

                // Verifica se ja existe fatura pro (cliente, mes)
                $stmtCheck = $db->prepare("
                    SELECT id FROM faturas
                    WHERE empresa_id = ? AND cliente_id = ? AND mes_referencia = ?
                ");
                $stmtCheck->execute([$empresaId, $cid, $mes]);
                if ($stmtCheck->fetch()) {
                    $puladas++;
                    continue;
                }

                // Insere fatura
                $stmtF = $db->prepare("
                    INSERT INTO faturas (
                        empresa_id, cliente_id, mes_referencia,
                        data_emissao, data_vencimento,
                        valor_total, status
                    ) VALUES (?, ?, ?, ?, ?, ?, 'aberta')
                ");
                $stmtF->execute([
                    $empresaId,
                    $cid,
                    $mes,
                    date('Y-m-d'),
                    $vencimento,
                    $grp['valor_total'],
                ]);
                $faturaId = (int)$db->lastInsertId();

                // Insere itens
                $stmtI = $db->prepare("
                    INSERT INTO fatura_itens (
                        fatura_id, cliente_servico_id, descricao,
                        valor_unitario, valor_total
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($grp['itens'] as $s) {
                    $stmtI->execute([
                        $faturaId,
                        (int)$s['id'],
                        $s['descricao'],
                        $s['valor_mensal'],
                        $s['valor_mensal'],
                    ]);
                }

                $geradas++;
                $faturasIdsGeradas[] = $faturaId;
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['flash_erro'] = 'Erro ao gerar faturas: ' . $e->getMessage();
            header('Location: fatura_acao.php?acao=form&mes=' . urlencode($mes));
            exit;
        }

        $msg = sprintf(
            '%d fatura(s) gerada(s) para %s. %d pulada(s) (ja existiam).',
            $geradas, $mes, $puladas
        );

        // Se action=gerar_receber, gera CRs em loop (apos commit das faturas)
        $action = (string)($_POST['action'] ?? 'gerar');
        if ($action === 'gerar_receber' && !empty($faturasIdsGeradas)) {
            $crGeradas = 0;
            $crPuladas = 0;
            $crErros   = [];
            foreach ($faturasIdsGeradas as $fid) {
                $res = $this->criarContaReceberPorFatura($fid, $empresaId, $usuarioId);
                if ($res['ok']) {
                    $crGeradas++;
                } elseif ($res['id']) {
                    $crPuladas++; // ja existia
                } else {
                    $crErros[] = "Fatura #{$fid}: " . $res['msg'];
                }
            }
            $msg .= sprintf(
                ' %d conta(s) a receber gerada(s). %d pulada(s) (ja existiam).',
                $crGeradas, $crPuladas
            );
            if (!empty($crErros)) {
                $msg .= " Erros: " . implode('; ', array_slice($crErros, 0, 3));
                if (count($crErros) > 3) $msg .= " (+" . (count($crErros) - 3) . " mais)";
            }
        }

        $_SESSION['flash_sucesso'] = $msg;
        header('Location: faturas.php?mes=' . urlencode($mes));
        exit;
    }

    /**
     * Detalhe de uma fatura.
     * GET fatura_acao.php?acao=show&id=N
     */
    public function show(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: faturas.php');
            exit;
        }

        $db = Database::getConnection();

        $stmt = $db->prepare("
            SELECT f.*, c.razao_social AS cliente_nome, c.cpf_cnpj AS cliente_doc,
                   c.email AS cliente_email
            FROM faturas f
            JOIN clientes c ON c.id = f.cliente_id
            WHERE f.id = ? AND f.empresa_id = ?
        ");
        $stmt->execute([$id, $empresaId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fatura) {
            $_SESSION['flash_erro'] = 'Fatura nao encontrada.';
            header('Location: faturas.php');
            exit;
        }

        $stmtI = $db->prepare("
            SELECT fi.*, cs.tipo_cobranca
            FROM fatura_itens fi
            JOIN cliente_servicos cs ON cs.id = fi.cliente_servico_id
            WHERE fi.fatura_id = ?
            ORDER BY fi.id
        ");
        $stmtI->execute([$id]);
        $itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

        // Flag: pode gerar conta a receber? (fatura aberta/vencida + ainda nao gerada)
        $podeGerarReceber = in_array($fatura['status'], ['aberta', 'vencida'], true);
        if ($podeGerarReceber) {
            $stmtCR = $db->prepare("SELECT id, status FROM contas_receber WHERE numero_documento = ? AND empresa_id = ?");
            $stmtCR->execute(['FAT-' . $id, $empresaId]);
            $contaExistente = $stmtCR->fetch(PDO::FETCH_ASSOC);
            if ($contaExistente) {
                $podeGerarReceber = false;
            }
        }

        layout('Fatura #' . $id, 'faturas/show.php', [
            'fatura'           => $fatura,
            'itens'            => $itens,
            'podeGerarReceber' => $podeGerarReceber,
        ]);
    }

    /**
     * Marca fatura como PAGA.
     * POST fatura_acao.php?acao=pagar
     * Body: id, data_pagamento, valor_pago, observacoes
     */
    public function pagar(): void
    {
        Auth::require();
        Permissao::requer('editar', 'faturas.php');
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $id     = (int)($_POST['id']            ?? 0);
        $data   = (string)($_POST['data_pagamento'] ?? date('Y-m-d'));
        $valor  = str_replace(['.', ','], ['', '.'], (string)($_POST['valor_pago'] ?? '0'));
        $valor  = (float)$valor;
        $obs    = trim((string)($_POST['observacoes'] ?? ''));
        $obs    = ($obs === '' ? null : $obs);

        if ($id <= 0) {
            $_SESSION['flash_erro'] = 'ID invalido.';
            header('Location: faturas.php');
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $data = date('Y-m-d');
        }
        if ($valor <= 0) {
            $_SESSION['flash_erro'] = 'Valor pago deve ser maior que zero.';
            header('Location: fatura_acao.php?acao=show&id=' . $id);
            exit;
        }

        // Verifica se existe e pertence a empresa
        $stmt = $db->prepare("SELECT id, valor_total, status FROM faturas WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresaId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fatura) {
            $_SESSION['flash_erro'] = 'Fatura nao encontrada.';
            header('Location: faturas.php');
            exit;
        }
        if (in_array($fatura['status'], ['cancelada'], true)) {
            $_SESSION['flash_erro'] = 'Fatura cancelada nao pode ser marcada como paga.';
            header('Location: fatura_acao.php?acao=show&id=' . $id);
            exit;
        }

        // Define status
        $novoStatus = ($valor >= (float)$fatura['valor_total'] - 0.01) ? 'paga' : 'parcial';

        $stmt = $db->prepare("
            UPDATE faturas
            SET status = ?, data_pagamento = ?, valor_pago = ?, observacoes = COALESCE(?, observacoes)
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$novoStatus, $data, $valor, $obs, $id, $empresaId]);

        $_SESSION['flash_sucesso'] = sprintf('Fatura #%d marcada como %s.', $id, $novoStatus);
        header('Location: fatura_acao.php?acao=show&id=' . $id);
        exit;
    }

    /**
     * Cancela uma fatura (estorno).
     * POST fatura_acao.php?acao=cancelar
     * Body: id, motivo
     */
    public function cancelar(): void
    {
        Auth::require();
        Permissao::requer('editar', 'faturas.php');
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $id     = (int)($_POST['id']     ?? 0);
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        $motivo = ($motivo === '' ? 'Cancelada pelo usuario' : $motivo);

        if ($id <= 0) {
            $_SESSION['flash_erro'] = 'ID invalido.';
            header('Location: faturas.php');
            exit;
        }

        $stmt = $db->prepare("
            UPDATE faturas
            SET status = 'cancelada', observacoes = CONCAT_WS(CHAR(10), observacoes, CONCAT('CANCELADA: ', ?))
            WHERE id = ? AND empresa_id = ? AND status NOT IN ('paga')
        ");
        $stmt->execute([$motivo, $id, $empresaId]);

        if ($stmt->rowCount() === 0) {
            $_SESSION['flash_erro'] = 'Nao foi possivel cancelar (ja paga ou inexistente).';
        } else {
            $_SESSION['flash_sucesso'] = 'Fatura #' . $id . ' cancelada.';
        }
        header('Location: fatura_acao.php?acao=show&id=' . $id);
        exit;
    }

    /**
     * Exclui uma fatura (somente canceladas ou abertas sem pagamento).
     * POST fatura_acao.php?acao=excluir
     */
    public function excluir(): void
    {
        Auth::require();
        Permissao::requer('excluir', 'faturas.php');
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['flash_erro'] = 'ID invalido.';
            header('Location: faturas.php');
            exit;
        }

        try {
            $db->beginTransaction();

            // 1. Carrega a fatura (multi-tenant safe)
            $stmt = $db->prepare("SELECT id, status FROM faturas WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresaId]);
            $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fatura) {
                $db->rollBack();
                $_SESSION['flash_erro'] = 'Fatura nao encontrada.';
                header('Location: faturas.php');
                exit;
            }

            // 2. Bloqueia se fatura ja paga/parcial (mantido)
            if ($fatura['status'] === 'paga' || $fatura['status'] === 'parcial') {
                $db->rollBack();
                $_SESSION['flash_erro'] = 'Fatura com pagamento nao pode ser excluida. Cancele primeiro.';
                header('Location: fatura_acao.php?acao=show&id=' . $id);
                exit;
            }

            // 3. Verifica se tem CR gerada (numero_documento='FAT-{id}')
            $numeroDoc = 'FAT-' . $id;
            $stmtCR = $db->prepare("SELECT id, status FROM contas_receber WHERE numero_documento = ? AND empresa_id = ?");
            $stmtCR->execute([$numeroDoc, $empresaId]);
            $cr = $stmtCR->fetch(PDO::FETCH_ASSOC);

            $crDeletadaId = null;
            $crStatus = null;
            if ($cr) {
                $crDeletadaId = (int)$cr['id'];
                $crStatus = $cr['status'];

                // 4. BLOQUEIA se CR ja foi recebida (paga) - preserva historico financeiro
                if ($crStatus === 'recebida') {
                    $db->rollBack();
                    $_SESSION['flash_erro'] = sprintf(
                        'Fatura #%d possui CR #%d ja RECEBIDA (paga). Cancele ou estorne a CR antes de excluir a fatura, para preservar o historico financeiro.',
                        $id, $crDeletadaId
                    );
                    header('Location: fatura_acao.php?acao=show&id=' . $id);
                    exit;
                }

                // 5. Deleta a CR (CASCADE remove parcelas + faturas_mensais vira NULL via SET NULL)
                $stmtDelCR = $db->prepare("DELETE FROM contas_receber WHERE id = ? AND empresa_id = ?");
                $stmtDelCR->execute([$crDeletadaId, $empresaId]);
            }

            // 6. Deleta a fatura (CASCADE remove fatura_itens)
            $stmtDel = $db->prepare("DELETE FROM faturas WHERE id = ? AND empresa_id = ?");
            $stmtDel->execute([$id, $empresaId]);

            $db->commit();

            // 7. Mensagem de sucesso informa o que foi removido
            if ($crDeletadaId !== null) {
                $_SESSION['flash_sucesso'] = sprintf(
                    'Fatura #%d e CR #%d (status: %s) excluidas com sucesso.',
                    $id, $crDeletadaId, $crStatus
                );
            } else {
                $_SESSION['flash_sucesso'] = 'Fatura #' . $id . ' excluida com sucesso.';
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['flash_erro'] = 'Erro ao excluir fatura: ' . $e->getMessage();
        }

        header('Location: faturas.php');
        exit;
    }

    /**
     * Roteador: acao vem do ?acao= ou $_POST['acao'].
     */
    /**
     * Gera 1 conta a receber a partir da fatura (idempotente).
     * POST fatura_acao.php?acao=gerar_receber
     * Body: id (da fatura)
     *
     * Regras:
     * - Fatura precisa estar em status 'aberta' ou 'vencida'
     * - Nao duplica (checa por numero_documento='FAT-{id}')
     * - Auto-pick categoria ativa (1a da empresa) como categoria_id
     * - Herda conta_bancaria_id do 1o item da fatura
     */
    public function gerarReceber(): void
    {
        Auth::require();
        Permissao::requer('criar', 'faturas.php');
        $empresaId = Auth::user()['empresa_id'];
        $usuarioId = Auth::user()['id'];
        $db = Database::getConnection();

        $faturaId = (int)($_POST['id'] ?? 0);
        if ($faturaId <= 0) {
            $_SESSION['flash_erro'] = 'ID invalido.';
            header('Location: faturas.php');
            exit;
        }

        // Carrega a fatura
        $stmt = $db->prepare("
            SELECT f.*, c.razao_social AS cliente_nome
            FROM faturas f
            JOIN clientes c ON c.id = f.cliente_id
            WHERE f.id = ? AND f.empresa_id = ?
        ");
        $stmt->execute([$faturaId, $empresaId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fatura) {
            $_SESSION['flash_erro'] = 'Fatura nao encontrada.';
            header('Location: faturas.php');
            exit;
        }

        // Valida status
        if (!in_array($fatura['status'], ['aberta', 'vencida'], true)) {
            $_SESSION['flash_erro'] = 'Fatura com status "' . $fatura['status'] . '" nao pode gerar conta a receber (apenas aberta/vencida).';
            header('Location: fatura_acao.php?acao=show&id=' . $faturaId);
            exit;
        }

        // Idempotencia: checa se ja existe conta_receber com numero_documento = FAT-{id}
        $numeroDoc = 'FAT-' . $faturaId;
        $stmtCheck = $db->prepare("SELECT id, status, data_criacao FROM contas_receber WHERE numero_documento = ? AND empresa_id = ?");
        $stmtCheck->execute([$numeroDoc, $empresaId]);
        $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($existente) {
            $_SESSION['flash_erro'] = sprintf(
                'Esta fatura ja gerou a conta a receber #%d (status: %s, criada em %s). Nao foi gerada duplicada.',
                $existente['id'],
                $existente['status'],
                date('d/m/Y H:i', strtotime($existente['data_criacao']))
            );
            header('Location: fatura_acao.php?acao=show&id=' . $faturaId);
            exit;
        }

        // Pega o 1o item pra herdar conta_bancaria_id
        $stmtItem = $db->prepare("SELECT cliente_servico_id FROM fatura_itens WHERE fatura_id = ? LIMIT 1");
        $stmtItem->execute([$faturaId]);
        $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            $_SESSION['flash_erro'] = 'Fatura sem itens. Adicione servicos antes de gerar a conta.';
            header('Location: fatura_acao.php?acao=show&id=' . $faturaId);
            exit;
        }

        // Pega o servico pra pegar conta_bancaria_id
        $stmtServ = $db->prepare("SELECT conta_bancaria_id FROM cliente_servicos WHERE id = ?");
        $stmtServ->execute([$item['cliente_servico_id']]);
        $servico = $stmtServ->fetch(PDO::FETCH_ASSOC);
        $contaBancariaId = !empty($servico['conta_bancaria_id']) ? (int)$servico['conta_bancaria_id'] : null;

        // Auto-pick categoria ativa (1a da empresa)
        $stmtCat = $db->prepare("SELECT id FROM categorias WHERE empresa_id = ? AND ativo = 1 AND (tipo = 'receita' OR tipo = 'ambos') ORDER BY id LIMIT 1");
        $stmtCat->execute([$empresaId]);
        $categoriaId = $stmtCat->fetchColumn();
        if (!$categoriaId) {
            $_SESSION['flash_erro'] = 'Nenhuma categoria cadastrada. Cadastre uma categoria de receita antes de gerar a conta.';
            header('Location: fatura_acao.php?acao=show&id=' . $faturaId);
            exit;
        }

        // Monta descricao
        $descricao = sprintf('Fatura #%d - %s - %s',
            $faturaId,
            $fatura['mes_referencia'],
            substr($fatura['cliente_nome'], 0, 80)
        );

        // Insere
        $stmtIns = $db->prepare("
            INSERT INTO contas_receber (
                empresa_id, cliente_id, categoria_id, descricao, numero_documento,
                valor, data_emissao, data_vencimento, forma_recebimento,
                conta_bancaria_id, status, parcelas, parcela_atual,
                observacoes, usuario_criacao_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente', 1, 1, ?, ?)
        ");
        $stmtIns->execute([
            $empresaId,
            (int)$fatura['cliente_id'],
            (int)$categoriaId,
            $descricao,
            $numeroDoc,
            (float)$fatura['valor_total'],
            date('Y-m-d'),
            $fatura['data_vencimento'],
            'boleto',
            $contaBancariaId,
            'Gerado da fatura #' . $faturaId . ' em ' . date('d/m/Y H:i'),
            (int)$usuarioId,
        ]);
        $contaId = (int)$db->lastInsertId();

        $_SESSION['flash_sucesso'] = sprintf('Conta a receber #%d gerada a partir da fatura #%d.', $contaId, $faturaId);
        header('Location: fatura_acao.php?acao=show&id=' . $faturaId);
        exit;
    }


    /**
     * Relatorio de faturas geradas, com filtros por data_emissao e mes_referencia.
     * GET relatorio_faturas.php
     * Query params:
     *   data_inicial     YYYY-MM-DD (opcional)
     *   data_final       YYYY-MM-DD (opcional)
     *   mes_referencia   YYYY-MM   (opcional)
     *
     * Mostra: tabela com faturas no periodo + cards de totalizadores
     * (qtd, valor total, recebido, pendente).
     */
    public function relatorio(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        // Parametros
        $dataInicial    = trim((string)($_GET['data_inicial'] ?? ''));
        $dataFinal      = trim((string)($_GET['data_final'] ?? ''));
        $mesRef         = trim((string)($_GET['mes_referencia'] ?? ''));

        // Monta WHERE com filtros opcionais
        $where  = ['f.empresa_id = ?'];
        $params = [$empresaId];

        if ($dataInicial !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicial)) {
            $where[] = 'f.data_emissao >= ?';
            $params[] = $dataInicial;
        }
        if ($dataFinal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFinal)) {
            $where[] = 'f.data_emissao <= ?';
            $params[] = $dataFinal;
        }
        if ($mesRef !== '' && preg_match('/^\d{4}-\d{2}$/', $mesRef)) {
            $where[] = 'f.mes_referencia = ?';
            $params[] = $mesRef;
        }

        $whereSql = implode(' AND ', $where);

        // Query principal (com dados do cliente via JOIN)
        $sql = "
            SELECT f.id, f.data_emissao, f.mes_referencia, f.data_vencimento,
                   f.valor_total, f.status, f.data_pagamento, f.valor_pago,
                   c.razao_social AS cliente_nome, c.cpf_cnpj
            FROM faturas f
            JOIN clientes c ON c.id = f.cliente_id
            WHERE $whereSql
            ORDER BY f.data_emissao DESC, f.id DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $faturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Totalizadores
        $totalGeral    = 0.0;
        $totalPago     = 0.0;
        $totalPendente = 0.0;
        $countPorStatus = [];
        foreach ($faturas as $f) {
            $v = (float)$f['valor_total'];
            $totalGeral += $v;
            if ($f['status'] === 'paga') {
                $totalPago += $v;
            } elseif (in_array($f['status'], ['aberta', 'vencida', 'parcial'], true)) {
                $totalPendente += $v;
            }
            $countPorStatus[$f['status']] = ($countPorStatus[$f['status']] ?? 0) + 1;
        }

        // Meses disponiveis para o dropdown
        $stmtMeses = $db->prepare("
            SELECT DISTINCT mes_referencia
            FROM faturas
            WHERE empresa_id = ?
            ORDER BY mes_referencia DESC
        ");
        $stmtMeses->execute([$empresaId]);
        $mesesDisponiveis = $stmtMeses->fetchAll(PDO::FETCH_COLUMN);

        // Render view
        $title = 'Relatorio de Faturas';
        require __DIR__ . '/../views/relatorio_faturas.php';
    }
    /**
     * Exporta o relatorio de faturas em PDF (via wkhtmltopdf).
     * GET relatorio_faturas_pdf.php?data_inicial=...&data_final=...&mes_referencia=...
     *
     * Segue o padrao ja usado em RelatorioController::gerarPdf() do financeiro.
     */
    public function relatorioPdf(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        // Mesmos parametros do relatorio() HTML
        $dataInicial    = trim((string)($_GET['data_inicial'] ?? ''));
        $dataFinal      = trim((string)($_GET['data_final'] ?? ''));
        $mesRef         = trim((string)($_GET['mes_referencia'] ?? ''));

        // Empresa (para o cabecalho)
        $stmtEmp = $db->prepare("SELECT nome_fantasia, razao_social FROM empresas WHERE id = ?");
        $stmtEmp->execute([$empresaId]);
        $empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC) ?: ['nome_fantasia' => 'Empresa', 'razao_social' => ''];

        // Monta WHERE com filtros opcionais
        $where  = ['f.empresa_id = ?'];
        $params = [$empresaId];

        if ($dataInicial !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicial)) {
            $where[] = 'f.data_emissao >= ?';
            $params[] = $dataInicial;
        }
        if ($dataFinal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFinal)) {
            $where[] = 'f.data_emissao <= ?';
            $params[] = $dataFinal;
        }
        if ($mesRef !== '' && preg_match('/^\d{4}-\d{2}$/', $mesRef)) {
            $where[] = 'f.mes_referencia = ?';
            $params[] = $mesRef;
        }

        $whereSql = implode(' AND ', $where);

        // Query principal
        $sql = "
            SELECT f.id, f.data_emissao, f.mes_referencia, f.data_vencimento,
                   f.valor_total, f.status, f.data_pagamento, f.valor_pago,
                   c.razao_social AS cliente_nome, c.cpf_cnpj
            FROM faturas f
            JOIN clientes c ON c.id = f.cliente_id
            WHERE $whereSql
            ORDER BY f.data_emissao ASC, f.id ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $faturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Totalizadores
        $totalGeral    = 0.0;
        $totalPago     = 0.0;
        $totalPendente = 0.0;
        $countPorStatus = [];
        foreach ($faturas as $f) {
            $v = (float)$f['valor_total'];
            $totalGeral += $v;
            if ($f['status'] === 'paga') {
                $totalPago += $v;
            } elseif (in_array($f['status'], ['aberta', 'vencida', 'parcial'], true)) {
                $totalPendente += $v;
            }
            $countPorStatus[$f['status']] = ($countPorStatus[$f['status']] ?? 0) + 1;
        }

        // Monta o HTML do PDF (limpo, sem nav, sem form)
        $periodo = '';
        if ($dataInicial || $dataFinal) {
            $periodo = ($dataInicial ? date('d/m/Y', strtotime($dataInicial)) : '...') . ' ate ' . ($dataFinal ? date('d/m/Y', strtotime($dataFinal)) : '...');
        } else {
            $periodo = 'Todas as datas';
        }
        if ($mesRef) {
            $periodo .= ' (mes ref.: ' . htmlspecialchars($mesRef) . ')';
        }

        $empresaNome = htmlspecialchars($empresa['nome_fantasia'] ?: $empresa['razao_social']);
        $emitidoEm = date('d/m/Y H:i');

        $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Relatorio de Faturas</title>
<style>
    body { font-family: Arial, sans-serif; font-size: 10pt; color: #000; margin: 0; padding: 0; }
    .header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 12px; }
    .header h1 { font-size: 16pt; margin: 0 0 4px 0; }
    .header .info { font-size: 9pt; color: #555; }
    table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    th, td { padding: 4px 6px; border: 1px solid #d0d0d0; }
    th { background: #f0f0f0; text-align: left; font-weight: 600; }
    tr.total-row { background: #f9f9f9; font-weight: 700; }
    .right { text-align: right; }
    .center { text-align: center; }
    .totals { margin-top: 16px; padding: 8px 12px; background: #f9fafb; border: 1px solid #ddd; font-size: 9pt; }
    .totals table { border: 0; }
    .totals td { border: 0; padding: 2px 8px; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8pt; }
    .footer { margin-top: 20px; font-size: 8pt; color: #666; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }
</style>
</head>
<body>
    <div class="header">
        <h1>Relatorio de Faturas Geradas</h1>
        <div class="info"><strong>Empresa:</strong> ' . $empresaNome . ' &nbsp;|&nbsp; <strong>Periodo:</strong> ' . $periodo . ' &nbsp;|&nbsp; <strong>Emitido em:</strong> ' . $emitidoEm . '</div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="center" style="width: 40px;">ID</th>
                <th class="center" style="width: 70px;">Emissao</th>
                <th class="center" style="width: 60px;">Mes Ref.</th>
                <th>Cliente</th>
                <th class="right" style="width: 90px;">Valor (R$)</th>
                <th class="center" style="width: 70px;">Status</th>
                <th class="center" style="width: 70px;">Vencimento</th>
                <th class="center" style="width: 70px;">Pago em</th>
            </tr>
        </thead>
        <tbody>';

        if (empty($faturas)) {
            $html .= '<tr><td colspan="8" class="center" style="padding: 16px; color: #888;">Nenhuma fatura encontrada com os filtros aplicados.</td></tr>';
        } else {
            foreach ($faturas as $f) {
                $html .= '<tr>';
                $html .= '<td class="center">#' . (int)$f['id'] . '</td>';
                $html .= '<td class="center">' . date('d/m/Y', strtotime($f['data_emissao'])) . '</td>';
                $html .= '<td class="center">' . htmlspecialchars($f['mes_referencia']) . '</td>';
                $html .= '<td>' . htmlspecialchars($f['cliente_nome']) . '</td>';
                $html .= '<td class="right">' . number_format((float)$f['valor_total'], 2, ',', '.') . '</td>';
                $html .= '<td class="center"><span class="badge">' . htmlspecialchars($f['status']) . '</span></td>';
                $html .= '<td class="center">' . date('d/m/Y', strtotime($f['data_vencimento'])) . '</td>';
                $html .= '<td class="center">' . ($f['data_pagamento'] ? date('d/m/Y', strtotime($f['data_pagamento'])) : '-') . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table>';

        // Totalizadores
        $html .= '<div class="totals">
            <strong>Totalizadores</strong>
            <table style="margin-top: 6px;">
                <tr>
                    <td><strong>Qtd faturas:</strong> ' . count($faturas) . '</td>
                    <td class="right"><strong>Valor total:</strong> R$ ' . number_format($totalGeral, 2, ',', '.') . '</td>
                </tr>
                <tr>
                    <td><strong>Recebido:</strong> R$ ' . number_format($totalPago, 2, ',', '.') . '</td>
                    <td class="right"><strong>Pendente:</strong> R$ ' . number_format($totalPendente, 2, ',', '.') . '</td>
                </tr>
            </table>
        </div>';

        $html .= '<div class="footer">Sistema Financeiro &middot; Relatorio gerado em ' . $emitidoEm . '</div>';

        $html .= '</body></html>';

        // Salva HTML temporario e chama wkhtmltopdf
        $tmpHtml = tempnam(sys_get_temp_dir(), 'rel_fat_') . '.html';
        $tmpPdf  = tempnam(sys_get_temp_dir(), 'rel_fat_') . '.pdf';
        file_put_contents($tmpHtml, $html);

        $cmd = sprintf(
            'wkhtmltopdf --quiet --orientation Portrait --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm %s %s 2>&1',
            escapeshellarg($tmpHtml),
            escapeshellarg($tmpPdf)
        );
        exec($cmd, $output, $rc);

        // Limpa HTML temp
        @unlink($tmpHtml);

        if ($rc !== 0 || !file_exists($tmpPdf)) {
            @unlink($tmpPdf);
            $_SESSION['flash_erro'] = 'Erro ao gerar PDF (rc=' . $rc . '). Verifique se wkhtmltopdf esta instalado.';
            header('Location: relatorio_faturas.php?' . http_build_query(array_filter([
                'data_inicial' => $dataInicial,
                'data_final'   => $dataFinal,
                'mes_referencia' => $mesRef,
            ])));
            exit;
        }

        // Stream do PDF
        $filename = 'relatorio_faturas_' . date('Ymd_His') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($tmpPdf));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($tmpPdf);

        // Limpa PDF temp
        @unlink($tmpPdf);
        exit;
    }




    public function relatorioPorCliente(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $dataInicial  = trim((string)($_GET['data_inicial'] ?? ''));
        $dataFinal    = trim((string)($_GET['data_final'] ?? ''));
        $mesRef       = trim((string)($_GET['mes_referencia'] ?? ''));

        $where  = ['f.empresa_id = ?'];
        $params = [$empresaId];
        if ($dataInicial !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicial)) {
            $where[] = 'f.data_emissao >= ?'; $params[] = $dataInicial;
        }
        if ($dataFinal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFinal)) {
            $where[] = 'f.data_emissao <= ?'; $params[] = $dataFinal;
        }
        if ($mesRef !== '' && preg_match('/^\d{4}-\d{2}$/', $mesRef)) {
            $where[] = 'f.mes_referencia = ?'; $params[] = $mesRef;
        }
        $whereSql = implode(' AND ', $where);
        $whereFat = $whereSql === 'f.empresa_id = ?' ? '' : str_replace('f.empresa_id = ? AND ', '', $whereSql);

        $sql = "
            SELECT c.id AS cliente_id, c.razao_social, c.cpf_cnpj,
                   COUNT(f.id) AS qtd_faturas,
                   COALESCE(SUM(f.valor_total), 0) AS valor_total,
                   SUM(CASE WHEN f.status = 'paga' THEN 1 ELSE 0 END) AS qtd_pagas,
                   SUM(CASE WHEN f.status IN ('aberta', 'vencida', 'parcial') THEN 1 ELSE 0 END) AS qtd_pendentes,
                   COALESCE(SUM(CASE WHEN f.status = 'paga' THEN f.valor_total ELSE 0 END), 0) AS valor_pago,
                   COALESCE(SUM(CASE WHEN f.status IN ('aberta', 'vencida', 'parcial') THEN f.valor_total ELSE 0 END), 0) AS valor_pendente
            FROM clientes c
            LEFT JOIN faturas f ON f.cliente_id = c.id AND f.empresa_id = c.empresa_id " . ($whereFat ? "AND $whereFat" : '') . "
            WHERE c.empresa_id = ? AND c.ativo = 1
            GROUP BY c.id, c.razao_social, c.cpf_cnpj
            HAVING qtd_faturas > 0
            ORDER BY valor_total DESC
        ";

        $paramsFinal = array_merge(array_slice($params, 1), [$empresaId]);
        $stmt = $db->prepare($sql);
        $stmt->execute($paramsFinal);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalGeralClientes = count($clientes);
        $totalGeralValor    = array_sum(array_column($clientes, 'valor_total'));
        $totalGeralPago     = array_sum(array_column($clientes, 'valor_pago'));
        $totalGeralPendente = array_sum(array_column($clientes, 'valor_pendente'));

        $stmtMeses = $db->prepare("SELECT DISTINCT mes_referencia FROM faturas WHERE empresa_id = ? ORDER BY mes_referencia DESC");
        $stmtMeses->execute([$empresaId]);
        $mesesDisponiveis = $stmtMeses->fetchAll(PDO::FETCH_COLUMN);

        require __DIR__ . '/../views/relatorio_faturas_por_cliente.php';
    }


    public function relatorioPorStatus(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $dataInicial = trim((string)($_GET['data_inicial'] ?? ''));
        $dataFinal   = trim((string)($_GET['data_final'] ?? ''));
        $mesRef      = trim((string)($_GET['mes_referencia'] ?? ''));

        $where  = ['empresa_id = ?'];
        $params = [$empresaId];
        if ($dataInicial !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicial)) {
            $where[] = 'data_emissao >= ?'; $params[] = $dataInicial;
        }
        if ($dataFinal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFinal)) {
            $where[] = 'data_emissao <= ?'; $params[] = $dataFinal;
        }
        if ($mesRef !== '' && preg_match('/^\d{4}-\d{2}$/', $mesRef)) {
            $where[] = 'mes_referencia = ?'; $params[] = $mesRef;
        }
        $whereSql = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT status,
                   COUNT(*) AS qtd,
                   COALESCE(SUM(valor_total), 0) AS valor_total,
                   COALESCE(SUM(valor_pago), 0) AS valor_pago
            FROM faturas
            WHERE $whereSql
            GROUP BY status
            ORDER BY qtd DESC
        ");
        $stmt->execute($params);
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalQtd = array_sum(array_column($stats, 'qtd'));
        $totalValor = array_sum(array_column($stats, 'valor_total'));

        $stmtMeses = $db->prepare("SELECT DISTINCT mes_referencia FROM faturas WHERE empresa_id = ? ORDER BY mes_referencia DESC");
        $stmtMeses->execute([$empresaId]);
        $mesesDisponiveis = $stmtMeses->fetchAll(PDO::FETCH_COLUMN);

        require __DIR__ . '/../views/relatorio_faturas_por_status.php';
    }


    public function relatorioVencidas(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $sql = "
            SELECT f.id, f.data_emissao, f.mes_referencia, f.data_vencimento, f.valor_total,
                   f.status, f.data_pagamento, f.valor_pago,
                   DATEDIFF(CURDATE(), f.data_vencimento) AS dias_atraso,
                   c.razao_social AS cliente_nome, c.cpf_cnpj, c.telefone, c.email,
                   cr.id AS cr_id, cr.status AS cr_status
            FROM faturas f
            JOIN clientes c ON c.id = f.cliente_id
            LEFT JOIN contas_receber cr ON cr.numero_documento = CONCAT('FAT-', f.id)
              AND cr.empresa_id = f.empresa_id
            WHERE f.empresa_id = ?
              AND f.data_vencimento < CURDATE()
              AND f.status NOT IN ('paga', 'cancelada')
            ORDER BY dias_atraso DESC, f.valor_total DESC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$empresaId]);
        $vencidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalQtd   = count($vencidas);
        $totalValor = array_sum(array_column($vencidas, 'valor_total'));
        $totalPago  = array_sum(array_column($vencidas, 'valor_pago'));
        $totalPendente = $totalValor - $totalPago;

        $faixas = ['1-7' => 0, '8-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
        $valoresFaixa = ['1-7' => 0, '8-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
        foreach ($vencidas as $v) {
            $d = (int)$v['dias_atraso'];
            $val = (float)$v['valor_total'];
            if ($d <= 7) { $faixas['1-7']++; $valoresFaixa['1-7'] += $val; }
            elseif ($d <= 30) { $faixas['8-30']++; $valoresFaixa['8-30'] += $val; }
            elseif ($d <= 60) { $faixas['31-60']++; $valoresFaixa['31-60'] += $val; }
            elseif ($d <= 90) { $faixas['61-90']++; $valoresFaixa['61-90'] += $val; }
            else { $faixas['90+']++; $valoresFaixa['90+'] += $val; }
        }

        require __DIR__ . '/../views/relatorio_faturas_vencidas.php';
    }

    private function editarFatura(int $id): void
    {
        Auth::require();
        Permissao::requer('editar', 'faturas.php');
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT f.*, c.razao_social AS cliente_nome FROM faturas f JOIN clientes c ON c.id = f.cliente_id WHERE f.id = ? AND f.empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fatura) {
            Flash::set('erro', 'Fatura nao encontrada.');
            redirect('faturas.php');
        }
        if ($fatura['status'] === 'paga') {
            Flash::set('erro', 'Fatura ja paga nao pode ser editada.');
            redirect("fatura_acao.php?acao=show&id=$id");
        }

        require __DIR__ . '/../views/faturas/editar.php';
    }

    private function salvarEdicaoFatura(int $id): void
    {
        Auth::require();
        Permissao::requer('editar', 'faturas.php');
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        $stmt = $db->prepare('SELECT * FROM faturas WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$id, $empresaId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fatura) {
            Flash::set('erro', 'Fatura nao encontrada.');
            redirect('faturas.php');
        }
        if ($fatura['status'] === 'paga') {
            Flash::set('erro', 'Fatura ja paga nao pode ser editada.');
            redirect("fatura_acao.php?acao=show&id=$id");
        }

        $valorTotal     = (float)str_replace(',', '.', $_POST['valor_total']     ?? '0');
        $valorDesconto  = (float)str_replace(',', '.', $_POST['valor_desconto']  ?? '0');
        $valorJuros     = (float)str_replace(',', '.', $_POST['valor_juros']     ?? '0');
        $valorMulta     = (float)str_replace(',', '.', $_POST['valor_multa']     ?? '0');
        $dataVencimento = trim((string)($_POST['data_vencimento'] ?? ''));
        $observacoes    = trim((string)($_POST['observacoes']    ?? ''));

        if ($valorTotal <= 0) {
            Flash::set('erro', 'Valor total deve ser maior que zero.');
            redirect("fatura_acao.php?acao=editar&id=$id");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataVencimento)) {
            Flash::set('erro', 'Data de vencimento invalida.');
            redirect("fatura_acao.php?acao=editar&id=$id");
        }

        try {
            $stmtU = $db->prepare("
                UPDATE faturas SET
                    valor_total = :valor_total,
                    valor_desconto = :valor_desconto,
                    valor_juros = :valor_juros,
                    valor_multa = :valor_multa,
                    data_vencimento = :data_vencimento,
                    observacoes = :observacoes
                WHERE id = :id AND empresa_id = :empresa_id AND status != 'paga'
            ");
            $stmtU->execute([
                'valor_total'     => $valorTotal,
                'valor_desconto'  => $valorDesconto,
                'valor_juros'     => $valorJuros,
                'valor_multa'     => $valorMulta,
                'data_vencimento' => $dataVencimento,
                'observacoes'     => $observacoes ?: null,
                'id'              => $id,
                'empresa_id'      => $empresaId,
            ]);

            Flash::set('sucesso', 'Fatura #' . $id . ' atualizada com sucesso.');
            redirect("fatura_acao.php?acao=show&id=$id");
        } catch (PDOException $e) {
            error_log('[Faturas] Erro ao editar: ' . $e->getMessage());
            Flash::set('erro', 'Erro ao salvar edicao.');
            redirect("fatura_acao.php?acao=editar&id=$id");
        }
    }

    private function getCrIdPorFatura(int $faturaId): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();
        $stmt = $db->prepare('SELECT id FROM contas_receber WHERE empresa_id = ? AND numero_documento = ? LIMIT 1');
        $stmt->execute([$empresaId, 'FAT-' . $faturaId]);
        $crId = $stmt->fetchColumn();
        header('Content-Type: application/json');
        echo json_encode(['cr_id' => $crId ? (int)$crId : null, 'fatura_id' => $faturaId]);
        exit;
    }

    public function acao(): void
    {
        $acao = $_REQUEST['acao'] ?? 'index';
        $id   = (int)($_REQUEST['id'] ?? 0);

        switch ($acao) {
            case 'index':   $this->index();   break;
            case 'form':    $this->form();    break;
            case 'gerar':   $this->gerar();   break;
            case 'show':    $this->show();    break;
            case 'pagar':   $this->pagar();   break;
            case 'cancelar':$this->cancelar();break;
            case 'excluir': $this->excluir(); break;
            case 'get_cr_id':  $this->getCrIdPorFatura($id);  break;
            case 'gerar_receber': $this->gerarReceber(); break;
            case 'editar':        $this->editarFatura($id);       break;
            case 'salvar_edicao': $this->salvarEdicaoFatura($id); break;
            default:
                header('Location: faturas.php');
                exit;
        }
    }
}
