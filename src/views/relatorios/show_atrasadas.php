<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
/** @var string $tipo */

$headers = $dados['headers'] ?? [];
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
    <h1><?= htmlspecialchars($dados['titulo'] ?? 'Contas Atrasadas') ?></h1>
    <div>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn" target="_blank">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<form method="get" class="filters-bar">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <div class="form-group">
        <label>De</label>
        <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
    </div>
    <div class="form-group">
        <label>Até</label>
        <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Aplicar</button>
    <a href="relatorio_show.php?tipo=<?= urlencode($tipo) ?>" class="btn">Limpar</a>
</form>

<div style="background: #fffbeb; border: 1px solid #fbbf24; padding: 10px 14px; margin-bottom: 16px; border-radius: 4px; font-size: 13px;">
    ⚠️ Exibindo contas com vencimento anterior a hoje (<strong><?= dataIsoParaBr(date('Y-m-d')) ?></strong>) e status pendente ou aprovada.
</div>

<?php if (!$temRegistros): ?>
    <div class="alert alert-success" style="margin: 20px 0;">✅ Nenhuma conta atrasada. Tudo em dia!</div>
<?php else: ?>

    <!-- Cards de resumo Pagar / Receber / Saldo -->
    <div class="report-cards-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div class="report-total-card" style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #991b1b; text-transform: uppercase; font-weight: 600;">🔴 Atrasadas a Pagar</div>
            <div style="font-size: 22px; font-weight: 700; color: #dc2626; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                R$ <?= number_format($sep['pagar']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $sep['pagar']['qtd'] ?> conta(s) &middot; Maior atraso: <?= $sep['pagar']['max_atraso'] ?> dia(s)
            </div>
        </div>
        <div class="report-total-card" style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #166534; text-transform: uppercase; font-weight: 600;">🟢 Atrasadas a Receber</div>
            <div style="font-size: 22px; font-weight: 700; color: #16a34a; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                R$ <?= number_format($sep['receber']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $sep['receber']['qtd'] ?> conta(s) &middot; Maior atraso: <?= $sep['receber']['max_atraso'] ?> dia(s)
            </div>
        </div>
        <div class="report-total-card" style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #1e40af; text-transform: uppercase; font-weight: 600;">💰 Saldo (Receber - Pagar)</div>
            <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                R$ <?= number_format($sep['receber']['valor'] - $sep['pagar']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $totalQtd ?> conta(s) &middot; <?= max($sep['pagar']['max_atraso'], $sep['receber']['max_atraso']) ?> dia(s) maior atraso
            </div>
        </div>
    </div>

    <!-- Tabela CONTAS A PAGAR ATRASADAS -->
    <h2 style="margin: 24px 0 8px 0; font-size: 16px; color: #111827; font-weight: 700;">🔴 Contas a Pagar Atrasadas</h2>
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
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h === 'Entidade' ? 'Fornecedor' : $h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rowsPagar)): ?>
                <tr><td colspan="<?= count($headers) ?>" style="text-align: center; color: #6b7280; padding: 18px; font-style: italic;">Nenhuma conta a pagar atrasada.</td></tr>
            <?php else: foreach ($rowsPagar as $row):
                $rowShow = $row;
                unset($rowShow['__raw__']); ?>
                <tr>
                    <?php foreach ($rowShow as $cell): ?>
                        <td><?= htmlspecialchars((string)$cell) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rowsPagar)): ?>
        <tfoot>
            <tr class="report-total-row" style="background: #1e40af; color: white;">
                <th colspan="4" style="text-align: right; padding: 10px 12px; font-size: 13px;">SUBTOTAL A PAGAR (atrasado):</th>
                <th style="text-align: right; padding: 10px 12px; font-size: 13px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($sep['pagar']['valor'], 2, ',', '.') ?>
                </th>
                <th style="padding: 10px 12px; font-size: 12px;"><?= $sep['pagar']['qtd'] ?> conta(s)</th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <!-- Tabela CONTAS A RECEBER ATRASADAS -->
    <h2 style="margin: 24px 0 8px 0; font-size: 16px; color: #111827; font-weight: 700;">🟢 Contas a Receber Atrasadas</h2>
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
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h === 'Entidade' ? 'Cliente' : $h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rowsReceber)): ?>
                <tr><td colspan="<?= count($headers) ?>" style="text-align: center; color: #6b7280; padding: 18px; font-style: italic;">Nenhuma conta a receber atrasada.</td></tr>
            <?php else: foreach ($rowsReceber as $row):
                $rowShow = $row;
                unset($rowShow['__raw__']); ?>
                <tr>
                    <?php foreach ($rowShow as $cell): ?>
                        <td><?= htmlspecialchars((string)$cell) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rowsReceber)): ?>
        <tfoot>
            <tr class="report-total-row" style="background: #1e40af; color: white;">
                <th colspan="4" style="text-align: right; padding: 10px 12px; font-size: 13px;">SUBTOTAL A RECEBER (atrasado):</th>
                <th style="text-align: right; padding: 10px 12px; font-size: 13px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($sep['receber']['valor'], 2, ',', '.') ?>
                </th>
                <th style="padding: 10px 12px; font-size: 12px;"><?= $sep['receber']['qtd'] ?> conta(s)</th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
<?php endif; ?>
