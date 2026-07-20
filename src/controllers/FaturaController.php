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

        layout('Fatura #' . $id, 'faturas/show.php', [
            'fatura' => $fatura,
            'itens'  => $itens,
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

        $stmt = $db->prepare("SELECT status FROM faturas WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresaId]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fatura) {
            $_SESSION['flash_erro'] = 'Fatura nao encontrada.';
            header('Location: faturas.php');
            exit;
        }
        if ($fatura['status'] === 'paga' || $fatura['status'] === 'parcial') {
            $_SESSION['flash_erro'] = 'Fatura com pagamento nao pode ser excluida. Cancele primeiro.';
            header('Location: fatura_acao.php?acao=show&id=' . $id);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM faturas WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresaId]);

        $_SESSION['flash_sucesso'] = 'Fatura #' . $id . ' excluida.';
        header('Location: faturas.php');
        exit;
    }

    /**
     * Roteador: acao vem do ?acao= ou $_POST['acao'].
     */
    public function acao(): void
    {
        $acao = $_REQUEST['acao'] ?? 'index';

        switch ($acao) {
            case 'index':   $this->index();   break;
            case 'form':    $this->form();    break;
            case 'gerar':   $this->gerar();   break;
            case 'show':    $this->show();    break;
            case 'pagar':   $this->pagar();   break;
            case 'cancelar':$this->cancelar();break;
            case 'excluir': $this->excluir(); break;
            default:
                header('Location: faturas.php');
                exit;
        }
    }
}
