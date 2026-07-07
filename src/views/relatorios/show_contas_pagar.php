<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
/** @var string $tipo */
/** @var array $statusFiltro */

$headers = $dados['headers'];
$rows = $dados['rows'];
$subtotaisPorData = $dados['subtotais_por_data'] ?? [];
$totais = $dados['totais'] ?? ['qtd' => 0, 'valor' => 0, 'pago' => 0, 'pendente' => 0];
$totaisPorStatus = $dados['totais_por_status'] ?? [];

// Indexa rows por data pra iteração
$rowsPorData = [];
foreach ($rows as $idx => $row) {
    $raw = $row['__raw__'] ?? null;
    if (!$raw) continue;
    $dataIso = $raw['data_vencimento'];
    if (!isset($rowsPorData[$dataIso])) $rowsPorData[$dataIso] = [];
    $rowsPorData[$dataIso][] = $row;
}
ksort($rowsPorData);

// Status options
$statusOptions = [
    'pendente'  => 'Pendente',
    'aprovada'  => 'Aprovada',
    'paga'      => 'Paga',
    'cancelada' => 'Cancelada',
];

// Monta query string preservando filtros já aplicados
$queryExport = http_build_query([
    'tipo'        => $tipo,
    'formato'     => 'csv', // será sobrescrito
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'status'      => $statusFiltro,
]);
$queryExportBase = preg_replace('/formato=[^&]+/', 'formato=', $queryExport);
?>
<div class="page-header">
    <h1>🔴 <?= htmlspecialchars($dados['titulo']) ?></h1>
    <div>
        <a href="relatorio_exportar.php?<?= $queryExportBase ?>csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>&status[]=<?= implode('&status[]=', array_map('urlencode', $statusFiltro)) ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?<?= $queryExportBase ?>pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>&status[]=<?= implode('&status[]=', array_map('urlencode', $statusFiltro)) ?>" class="btn">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<form method="get" class="filters-bar">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">

    <div class="form-group">
        <label>Tipo</label>
        <select name="tipo_fixo" disabled style="background: #f3f4f6; cursor: not-allowed;">
            <option selected>🔴 PAGAR (fixo neste relatório)</option>
        </select>
    </div>

    <div class="form-group">
        <label>Status (vazio = todos)</label>
        <div style="display: flex; gap: 12px; flex-wrap: wrap; padding: 6px 0;">
            <?php foreach ($statusOptions as $val => $label): ?>
                <label style="display: flex; align-items: center; gap: 4px; font-weight: normal; cursor: pointer;">
                    <input type="checkbox" name="status[]" value="<?= htmlspecialchars($val) ?>"
                        <?= in_array($val, $statusFiltro) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

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

<?php
$temRegistros = $totais['qtd'] > 0;
$filtroStatusLabel = empty($statusFiltro) ? 'TODOS' : strtoupper(implode(', ', $statusFiltro));
?>

<?php if (!$temRegistros): ?>
    <div class="alert alert-info" style="margin: 20px 0;">
        Nenhuma conta a pagar encontrada para o período <?= htmlspecialchars(dataIsoParaBr($dataInicio)) ?> a <?= htmlspecialchars(dataIsoParaBr($dataFim)) ?>
        com status <strong><?= htmlspecialchars($filtroStatusLabel) ?></strong>.
    </div>
<?php else: ?>

    <!-- Filtros aplicados -->
    <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 10px 14px; margin-bottom: 14px; border-radius: 4px; font-size: 13px;">
        <strong style="color: #991b1b;">🔍 Filtros aplicados:</strong>
        Tipo: <strong>PAGAR</strong> |
        Status: <strong><?= htmlspecialchars($filtroStatusLabel) ?></strong> |
        Período: <strong><?= htmlspecialchars(dataIsoParaBr($dataInicio)) ?></strong> a <strong><?= htmlspecialchars(dataIsoParaBr($dataFim)) ?></strong>
    </div>

    <!-- Cards de resumo -->
    <div class="report-cards-row" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
        <div class="report-total-card" style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #991b1b; text-transform: uppercase; font-weight: 600;">🔴 Total</div>
            <div style="font-size: 22px; font-weight: 700; color: #dc2626; margin-top: 4px;">
                R$ <?= number_format($totais['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $totais['qtd'] ?> conta(s) no período
            </div>
        </div>
        <div class="report-total-card" style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #166534; text-transform: uppercase; font-weight: 600;">🟢 Pago</div>
            <div style="font-size: 22px; font-weight: 700; color: #16a34a; margin-top: 4px;">
                R$ <?= number_format($totais['pago'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $totaisPorStatus['paga']['qtd'] ?? 0 ?> conta(s) liquidadas
            </div>
        </div>
        <div class="report-total-card" style="background: #fff7ed; border-left: 4px solid #ea580c; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #9a3412; text-transform: uppercase; font-weight: 600;">🟠 Pendente</div>
            <div style="font-size: 22px; font-weight: 700; color: #ea580c; margin-top: 4px;">
                R$ <?= number_format($totais['pendente'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                A pagar ainda
            </div>
        </div>
        <div class="report-total-card" style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #1e40af; text-transform: uppercase; font-weight: 600;">🔵 Qtd</div>
            <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin-top: 4px;">
                <?= $totais['qtd'] ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                contas no período
            </div>
        </div>
    </div>

    <!-- Breakdown por status (se filtrou vários status, mostra separadamente) -->
    <?php if (!empty($statusFiltro) && count($statusFiltro) > 1): ?>
    <div style="background: #f9fafb; padding: 10px 14px; margin-bottom: 12px; border-radius: 4px; font-size: 13px;">
        <strong>📊 Por status:</strong>
        <?php foreach ($statusFiltro as $st):
            $ts = $totaisPorStatus[$st] ?? ['qtd' => 0, 'valor' => 0, 'pago' => 0];
        ?>
            <span style="margin-left: 12px;">
                <strong><?= htmlspecialchars(ucfirst($st)) ?>:</strong>
                <?= $ts['qtd'] ?> conta(s) &middot;
                R$ <?= number_format($ts['valor'], 2, ',', '.') ?>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tabela agrupada por data -->
    <table class="table">
        <colgroup>
            <col style="width: 2.2cm;">  <!-- Vencimento -->
            <col style="width: 4.5cm;">  <!-- Descrição -->
            <col style="width: 3.5cm;">  <!-- Fornecedor -->
            <col style="width: 2.5cm;">  <!-- Categoria -->
            <col style="width: 2.3cm;">  <!-- Valor -->
            <col style="width: 2.3cm;">  <!-- Valor Pago -->
            <col style="width: 1.8cm;">  <!-- Status -->
            <col style="width: 1.8cm;">  <!-- Forma Pgto -->
            <col style="width: 2.3cm;">  <!-- Nº Documento -->
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rowsPorData as $dataIso => $rowsData):
                $sub = $subtotaisPorData[$dataIso] ?? null;
            ?>
                <tr style="background: #f3f4f6;">
                    <td colspan="<?= count($headers) ?>" style="padding: 8px 12px; font-weight: 700; color: #374151; border-top: 2px solid #d1d5db;">
                        📅 <?= dataIsoParaBr($dataIso) ?> (<?= $sub['qtd'] ?? 0 ?> conta(s))
                    </td>
                </tr>
                <?php foreach ($rowsData as $row): ?>
                    <tr>
                        <?php
                        $rowShow = $row;
                        unset($rowShow['__raw__']);
                        $rowShow[0] = '';
                        foreach ($rowShow as $cell): ?>
                            <td><?= htmlspecialchars((string)$cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($sub): ?>
                <tr style="background: #fef9c3; font-weight: 600;">
                    <td colspan="4" style="text-align: right; padding: 6px 12px; color: #854d0e; border-bottom: 1px solid #facc15;">
                        Subtotal <?= dataIsoParaBr($dataIso) ?>:
                    </td>
                    <td style="text-align: right; padding: 6px 12px; color: #854d0e; font-variant-numeric: tabular-nums; border-bottom: 1px solid #facc15;">
                        R$ <?= number_format($sub['valor'], 2, ',', '.') ?>
                    </td>
                    <td style="text-align: right; padding: 6px 12px; color: #854d0e; font-variant-numeric: tabular-nums; border-bottom: 1px solid #facc15;">
                        R$ <?= number_format($sub['pago'], 2, ',', '.') ?>
                    </td>
                    <td colspan="3" style="border-bottom: 1px solid #facc15;"></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="report-total-row" style="background: #1e40af; color: white;">
                <th colspan="4" style="text-align: right; padding: 10px 12px; font-size: 14px;">TOTAL GERAL:</th>
                <th style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($totais['valor'], 2, ',', '.') ?>
                </th>
                <th style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($totais['pago'], 2, ',', '.') ?>
                </th>
                <th colspan="3" style="padding: 10px 12px; font-size: 12px;">
                    <?= $totais['qtd'] ?> contas
                </th>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
