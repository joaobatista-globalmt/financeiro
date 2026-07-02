<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
/** @var string $tipo */

$headers = $dados['headers'];
$rowsPagar   = $dados['rows_pagar']   ?? [];
$rowsReceber = $dados['rows_receber'] ?? [];
$sep = $dados['totais_separados'] ?? [
    'pagar'   => ['qtd' => 0, 'valor' => 0.0, 'max_atraso' => 0],
    'receber' => ['qtd' => 0, 'valor' => 0.0, 'max_atraso' => 0],
];
$totalQtd = $sep['pagar']['qtd'] + $sep['receber']['qtd'];
$temRegistros = $totalQtd > 0;
?>
<div class="page-header">
    <h1><?= htmlspecialchars($dados['titulo']) ?></h1>
    <div>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<div class="filters-bar" style="background: transparent; border: 0; padding: 0; margin-bottom: 16px;">
    <p class="muted" style="margin: 0;">Exibindo contas com vencimento anterior a hoje (<?= dataIsoParaBr(date('Y-m-d')) ?>) e status pendente ou aprovada.</p>
</div>

<?php if (!$temRegistros): ?>
    <div class="alert alert-success" style="margin: 20px 0;">✅ Nenhuma conta atrasada. Tudo em dia!</div>
<?php else: ?>

    <!-- Cards de resumo Pagar / Receber / Saldo -->
    <div class="report-cards-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 20px;">
        <div class="report-total-card" style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #991b1b; text-transform: uppercase; font-weight: 600;">🔴 A Pagar (atrasado)</div>
            <div style="font-size: 22px; font-weight: 700; color: #dc2626; margin-top: 4px;">
                R$ <?= number_format($sep['pagar']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $sep['pagar']['qtd'] ?> conta(s) &middot;
                Maior atraso: <?= $sep['pagar']['max_atraso'] ?> dia(s)
            </div>
        </div>
        <div class="report-total-card" style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #166534; text-transform: uppercase; font-weight: 600;">🟢 A Receber (atrasado)</div>
            <div style="font-size: 22px; font-weight: 700; color: #16a34a; margin-top: 4px;">
                R$ <?= number_format($sep['receber']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $sep['receber']['qtd'] ?> conta(s) &middot;
                Maior atraso: <?= $sep['receber']['max_atraso'] ?> dia(s)
            </div>
        </div>
        <div class="report-total-card" style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #1e40af; text-transform: uppercase; font-weight: 600;">💰 Saldo (Receber - Pagar)</div>
            <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin-top: 4px;">
                R$ <?= number_format($sep['receber']['valor'] - $sep['pagar']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $totalQtd ?> conta(s) total &middot;
                <?= max($sep['pagar']['max_atraso'], $sep['receber']['max_atraso']) ?> dia(s) de maior atraso
            </div>
        </div>
    </div>

    <!-- Tabela CONTAS A PAGAR -->
    <h2 style="margin: 24px 0 8px 0; color: #991b1b; font-size: 16px;">🔴 Contas a Pagar Atrasadas</h2>
    <table class="table">
        <colgroup>
            <col style="width: 2.5cm;">  <!-- Vencimento -->
            <col style="width: 7cm;">     <!-- Descrição -->
            <col style="width: 4cm;">     <!-- Entidade (Fornecedor) -->
            <col style="width: 3cm;">     <!-- Categoria -->
            <col style="width: 2.8cm;">   <!-- Valor -->
            <col style="width: 1.8cm;">   <!-- Dias Atraso -->
        </colgroup>
        <thead>
            <tr style="background: #fee2e2;">
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h === 'Entidade' ? 'Fornecedor' : $h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rowsPagar)): ?>
                <tr><td colspan="<?= count($headers) ?>" class="muted center" style="padding: 18px;">Nenhuma conta a pagar atrasada.</td></tr>
            <?php else: foreach ($rowsPagar as $row): ?>
                <tr>
                    <?php
                    $rowShow = $row;
                    unset($rowShow['__raw__']);
                    foreach ($rowShow as $cell): ?>
                        <td><?= htmlspecialchars((string)$cell) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rowsPagar)): ?>
        <tfoot>
            <tr class="report-total-row" style="background: #991b1b; color: white;">
                <th colspan="4" style="text-align: right; padding: 8px 12px; font-size: 13px;">SUBTOTAL A PAGAR (atrasado):</th>
                <th style="text-align: right; padding: 8px 12px; font-size: 13px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($sep['pagar']['valor'], 2, ',', '.') ?>
                </th>
                <th style="padding: 8px 12px; font-size: 12px;">
                    <?= $sep['pagar']['qtd'] ?> conta(s)
                </th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <!-- Tabela CONTAS A RECEBER -->
    <h2 style="margin: 32px 0 8px 0; color: #166534; font-size: 16px;">🟢 Contas a Receber Atrasadas</h2>
    <table class="table">
        <colgroup>
            <col style="width: 2.5cm;">
            <col style="width: 7cm;">
            <col style="width: 4cm;">
            <col style="width: 3cm;">
            <col style="width: 2.8cm;">
            <col style="width: 1.8cm;">
        </colgroup>
        <thead>
            <tr style="background: #dcfce7;">
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h === 'Entidade' ? 'Cliente' : $h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rowsReceber)): ?>
                <tr><td colspan="<?= count($headers) ?>" class="muted center" style="padding: 18px;">Nenhuma conta a receber atrasada.</td></tr>
            <?php else: foreach ($rowsReceber as $row): ?>
                <tr>
                    <?php
                    $rowShow = $row;
                    unset($rowShow['__raw__']);
                    foreach ($rowShow as $cell): ?>
                        <td><?= htmlspecialchars((string)$cell) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rowsReceber)): ?>
        <tfoot>
            <tr class="report-total-row" style="background: #166534; color: white;">
                <th colspan="4" style="text-align: right; padding: 8px 12px; font-size: 13px;">SUBTOTAL A RECEBER (atrasado):</th>
                <th style="text-align: right; padding: 8px 12px; font-size: 13px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($sep['receber']['valor'], 2, ',', '.') ?>
                </th>
                <th style="padding: 8px 12px; font-size: 12px;">
                    <?= $sep['receber']['qtd'] ?> conta(s)
                </th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <!-- TOTAL GERAL -->
    <div style="margin-top: 24px; padding: 16px; background: #1e40af; color: white; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div style="font-size: 12px; text-transform: uppercase; opacity: 0.85; font-weight: 600;">📊 TOTAL GERAL ATRASADO</div>
            <div style="font-size: 11px; opacity: 0.75; margin-top: 2px;">
                <?= $sep['pagar']['qtd'] ?> a pagar + <?= $sep['receber']['qtd'] ?> a receber = <?= $totalQtd ?> conta(s)
            </div>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 24px; font-weight: 700; font-variant-numeric: tabular-nums;">
                R$ <?= number_format($sep['pagar']['valor'] + $sep['receber']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 11px; opacity: 0.75; margin-top: 2px;">
                Saldo previsto: R$ <?= number_format($sep['receber']['valor'] - $sep['pagar']['valor'], 2, ',', '.') ?>
            </div>
        </div>
    </div>

<?php endif; ?>
