<?php
/**
 * DashboardController - Dashboard unificado
 *
 * Mostra visão consolidada:
 *   - Cards: contas a pagar/receber (atrasadas, próximos 7 dias, mês)
 *   - Saldo total das contas bancárias
 *   - Saldo previsto = total a receber - total a pagar
 *   - Últimas movimentações
 */

declare(strict_types=1);

final class DashboardController
{
    /**
     * Placeholder - login.php renderiza diretamente.
     * Mantido aqui pra satisfazer a rota se for chamado.
     */
    public function login(): void
    {
        header('Location: login.php');
        exit;
    }

    /**
     * Placeholder - logout.php chama Auth::logout diretamente.
     */
    public function logout(): void
    {
        Auth::logout();
    }

    public function index(): void
    {
        Auth::require();
        $empresaId = Auth::user()['empresa_id'];

        $db = Database::getConnection();
        $hoje = date('Y-m-d');
        $semana = date('Y-m-d', strtotime('+7 days'));
        $inicioMes = date('Y-m-01');
        $fimMes = date('Y-m-t');

        // === Contas a Pagar ===
        $stmt = $db->prepare('
            SELECT
                SUM(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento < ? THEN valor ELSE 0 END) AS pagar_atrasadas,
                SUM(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento BETWEEN ? AND ? THEN valor ELSE 0 END) AS pagar_proximos_7,
                SUM(CASE WHEN status IN ("pendente","aprovada") THEN valor ELSE 0 END) AS pagar_total_pendente,
                SUM(CASE WHEN status = "paga" AND data_pagamento BETWEEN ? AND ? THEN valor_pago ELSE 0 END) AS pagar_pago_mes,
                COUNT(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento < ? THEN 1 END) AS qtd_pagar_atrasadas
            FROM contas_pagar
            WHERE empresa_id = ?
        ');
        $stmt->execute([$hoje, $hoje, $semana, $inicioMes, $fimMes, $hoje, $empresaId]);
        $pagar = $stmt->fetch();

        // === Contas a Receber ===
        $stmt = $db->prepare('
            SELECT
                SUM(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento < ? THEN valor ELSE 0 END) AS receber_atrasadas,
                SUM(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento BETWEEN ? AND ? THEN valor ELSE 0 END) AS receber_proximos_7,
                SUM(CASE WHEN status IN ("pendente","aprovada") THEN valor ELSE 0 END) AS receber_total_pendente,
                SUM(CASE WHEN status = "recebida" AND data_recebimento BETWEEN ? AND ? THEN valor_recebido ELSE 0 END) AS receber_recebido_mes,
                COUNT(CASE WHEN status IN ("pendente","aprovada") AND data_vencimento < ? THEN 1 END) AS qtd_receber_atrasadas
            FROM contas_receber
            WHERE empresa_id = ?
        ');
        $stmt->execute([$hoje, $hoje, $semana, $inicioMes, $fimMes, $hoje, $empresaId]);
        $receber = $stmt->fetch();

        // === Contas Bancárias (saldo total) ===
        $stmt = $db->prepare('
            SELECT
                cb.id, cb.descricao, cb.tipo, cb.banco,
                cb.saldo_inicial,
                (SELECT COALESCE(SUM(CASE WHEN m.tipo = "entrada" THEN m.valor ELSE -m.valor END), 0)
                 FROM movimentacoes_bancarias m
                 WHERE m.conta_bancaria_id = cb.id
                   AND m.data_movimento >= cb.data_saldo_inicial
                   AND m.data_movimento <= ?
                ) AS saldo_atual
            FROM contas_bancarias cb
            WHERE cb.empresa_id = ? AND cb.ativo = 1
            ORDER BY cb.descricao
        ');
        $stmt->execute([$hoje, $empresaId]);
        $contasBanco = $stmt->fetchAll();
        $saldoTotal = array_sum(array_column($contasBanco, 'saldo_atual'));

        // === Últimas movimentações ===
        $stmt = $db->prepare('
            SELECT m.*, cb.descricao AS conta_descricao, u.nome AS usuario_nome
            FROM movimentacoes_bancarias m
            JOIN contas_bancarias cb ON cb.id = m.conta_bancaria_id
            JOIN usuarios u ON u.id = m.usuario_id
            WHERE m.empresa_id = ?
            ORDER BY m.data_movimento DESC, m.id DESC
            LIMIT 10
        ');
        $stmt->execute([$empresaId]);
        $ultimasMovs = $stmt->fetchAll();

        // === Saldo previsto ===
        $saldoPrevisto = $saldoTotal + (float)$receber['receber_total_pendente'] - (float)$pagar['pagar_total_pendente'];

        // === Fluxo de caixa últimos 30 dias (por dia) ===
        $stmt = $db->prepare('
            SELECT
                data_movimento,
                SUM(CASE WHEN tipo = "entrada" THEN valor ELSE 0 END) AS entradas,
                SUM(CASE WHEN tipo = "saida" THEN valor ELSE 0 END) AS saidas
            FROM movimentacoes_bancarias
            WHERE empresa_id = ?
              AND data_movimento BETWEEN ? AND ?
            GROUP BY data_movimento
            ORDER BY data_movimento
        ');
        $stmt->execute([$empresaId, date('Y-m-d', strtotime('-30 days')), $hoje]);
        $fluxo30d = $stmt->fetchAll();

        layout('Dashboard', 'dashboard/index.php', [
            'pagar'         => $pagar,
            'receber'       => $receber,
            'contasBanco'   => $contasBanco,
            'saldoTotal'    => $saldoTotal,
            'saldoPrevisto' => $saldoPrevisto,
            'ultimasMovs'   => $ultimasMovs,
            'fluxo30d'      => $fluxo30d,
        ]);
    }


    /**
     * Drill-down de movimentacoes de uma conta bancaria especifica.
     * GET dashboard_drilldown_conta.php?conta_id=N
     * Retorna JSON com: titulo, saldo, qtd, contas[]
     */
    public function drilldownContaBanco(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        Auth::require();
        $contaId = (int)($_GET['conta_id'] ?? 0);
        if ($contaId <= 0) {
            echo json_encode(['erro' => 'conta_id obrigatorio']);
            return;
        }
        $empresaId = Auth::user()['empresa_id'];
        $db = Database::getConnection();

        // Header da conta (com filtro de empresa - seguranca)
        $stmt = $db->prepare('SELECT descricao, banco, tipo FROM contas_bancarias WHERE id = ? AND empresa_id = ?');
        $stmt->execute([$contaId, $empresaId]);
        $conta = $stmt->fetch();
        if (!$conta) {
            echo json_encode(['erro' => 'Conta nao encontrada ou sem permissao']);
            return;
        }

        // Movimentacoes (ultimas 200, mais recentes primeiro)
        $stmt = $db->prepare('
            SELECT m.data_movimento, m.tipo, m.origem, m.valor, m.descricao
            FROM movimentacoes_bancarias m
            WHERE m.conta_bancaria_id = ?
            ORDER BY m.data_movimento DESC, m.id DESC
            LIMIT 200
        ');
        $stmt->execute([$contaId]);
        $movs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Saldo atual (mesma formula do card: saldo_inicial + soma das movs)
        $stmt = $db->prepare('
            SELECT
                cb.saldo_inicial,
                (SELECT COALESCE(SUM(CASE WHEN m.tipo = "entrada" THEN m.valor ELSE -m.valor END), 0)
                 FROM movimentacoes_bancarias m
                 WHERE m.conta_bancaria_id = cb.id) AS saldo_movs
            FROM contas_bancarias cb
            WHERE cb.id = ? AND cb.empresa_id = ?
        ');
        $stmt->execute([$contaId, $empresaId]);
        $s = $stmt->fetch();
        $saldo = (float)($s['saldo_inicial'] ?? 0) + (float)($s['saldo_movs'] ?? 0);

        $tituloConta = $conta['descricao'] . (empty($conta['banco']) ? '' : ' (' . $conta['banco'] . ')');

        echo json_encode([
            'titulo' => $tituloConta,
            'saldo'  => $saldo,
            'qtd'    => count($movs),
            'contas' => $movs,
        ], JSON_UNESCAPED_UNICODE);
    }
}
