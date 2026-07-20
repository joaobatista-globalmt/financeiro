<?php /** @var array $pagar */ /** @var array $receber */ /** @var array $contasBanco */ ?>
<div class="page-header">
    <h1>Dashboard</h1>
    <p class="muted">Visão geral financeira — <?= htmlspecialchars(Auth::user()['nome']) ?></p>
</div>

<!-- Saldos bancários -->
<div class="cards-grid">
    <?php foreach ($contasBanco as $cb): ?>
        <div class="card card-info">
            <div class="card-header">
                <span class="card-title"><?= htmlspecialchars($cb['descricao']) ?></span>
                <small class="muted"><?= htmlspecialchars($cb['banco'] ?? $cb['tipo']) ?></small>
            </div>
            <div class="card-value <?= (float)$cb['saldo_atual'] < 0 ? 'text-danger' : '' ?>">
                R$ <?= number_format((float)$cb['saldo_atual'], 2, ',', '.') ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="card card-primary">
        <div class="card-header">
            <span class="card-title">Saldo Total</span>
        </div>
        <div class="card-value">R$ <?= number_format((float)$saldoTotal, 2, ',', '.') ?></div>
    </div>
</div>

<!-- Resumo Pagar / Receber -->
<div class="row">
    <div class="col">
        <h2>💸 Contas a Pagar</h2>
        <div class="cards-grid">
            <div class="card card-danger">
                <div class="card-title">Atrasadas</div>
                <div class="card-value">R$ <?= number_format((float)($pagar['pagar_atrasadas'] ?? 0), 2, ',', '.') ?></div>
                <small><?= (int)($pagar['qtd_pagar_atrasadas'] ?? 0) ?> conta(s)</small>
            </div>
            <div class="card card-warning">
                <div class="card-title">Próx. 7 dias</div>
                <div class="card-value">R$ <?= number_format((float)($pagar['pagar_proximos_7'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="card card-secondary">
                <div class="card-title">Total Pendente</div>
                <div class="card-value">R$ <?= number_format((float)($pagar['pagar_total_pendente'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="card card-success">
                <div class="card-title">Pago no Mês</div>
                <div class="card-value">R$ <?= number_format((float)($pagar['pagar_pago_mes'] ?? 0), 2, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <div class="col">
        <h2>💰 Contas a Receber</h2>
        <div class="cards-grid">
            <div class="card card-danger">
                <div class="card-title">Atrasadas</div>
                <div class="card-value">R$ <?= number_format((float)($receber['receber_atrasadas'] ?? 0), 2, ',', '.') ?></div>
                <small><?= (int)($receber['qtd_receber_atrasadas'] ?? 0) ?> conta(s)</small>
            </div>
            <div class="card card-warning">
                <div class="card-title">Próx. 7 dias</div>
                <div class="card-value">R$ <?= number_format((float)($receber['receber_proximos_7'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="card card-secondary">
                <div class="card-title">Total Pendente</div>
                <div class="card-value">R$ <?= number_format((float)($receber['receber_total_pendente'] ?? 0), 2, ',', '.') ?></div>
            </div>
            <div class="card card-success">
                <div class="card-title">Recebido no Mês</div>
                <div class="card-value">R$ <?= number_format((float)($receber['receber_recebido_mes'] ?? 0), 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Saldo previsto -->
<div class="card card-primary card-large">
    <div class="card-title">📊 Saldo Previsto</div>
    <div class="card-value <?= (float)$saldoPrevisto < 0 ? 'text-danger' : 'text-success' ?>">
        R$ <?= number_format((float)$saldoPrevisto, 2, ',', '.') ?>
    </div>
    <small class="muted">
        Saldo Bancário (R$ <?= number_format((float)$saldoTotal, 2, ',', '.') ?>)
        + A Receber (R$ <?= number_format((float)($receber['receber_total_pendente'] ?? 0), 2, ',', '.') ?>)
        - A Pagar (R$ <?= number_format((float)($pagar['pagar_total_pendente'] ?? 0), 2, ',', '.') ?>)
    </small>
</div>

<!-- Últimas movimentações -->
<div class="section">
    <h2>Últimas Movimentações</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Conta</th>
                <th>Descrição</th>
                <th>Tipo</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($ultimasMovs)): ?>
                <tr><td colspan="5" class="muted center">Nenhuma movimentação.</td></tr>
            <?php else: foreach ($ultimasMovs as $m): ?>
                <tr>
                    <td><?= dataIsoParaBr($m['data_movimento']) ?></td>
                    <td><?= htmlspecialchars($m['conta_descricao']) ?></td>
                    <td><?= htmlspecialchars($m['descricao']) ?></td>
                    <td>
                        <span class="badge badge-<?= $m['tipo'] === 'entrada' ? 'success' : 'danger' ?>">
                            <?= $m['tipo'] === 'entrada' ? '↗ Entrada' : '↘ Saída' ?>
                        </span>
                        <small class="muted">(<?= htmlspecialchars($m['origem']) ?>)</small>
                    </td>
                    <td class="text-right <?= $m['tipo'] === 'entrada' ? 'text-success' : 'text-danger' ?>">
                        <?= $m['tipo'] === 'entrada' ? '+' : '-' ?> R$ <?= number_format((float)$m['valor'], 2, ',', '.') ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>