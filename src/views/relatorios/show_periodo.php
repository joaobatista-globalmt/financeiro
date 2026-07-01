<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
/** @var string $tipo */

$headers = $dados['headers'];
$rows = $dados['rows'];
$subtotaisPorData = $dados['subtotais_por_data'] ?? [];
$totaisSeparados = $dados['totais_separados'] ?? ['pagar' => ['qtd'=>0,'valor'=>0,'pago'=>0], 'receber' => ['qtd'=>0,'valor'=>0,'pago'=>0]];
$totais = $dados['totais'] ?? null;

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
?>
<div class="page-header">
    <h1><?= htmlspecialchars($dados['titulo']) ?></h1>
    <div>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn">📄 PDF</a>
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
</form>

<?php
$temRegistros = ($totaisSeparados['pagar']['qtd'] + $totaisSeparados['receber']['qtd']) > 0;
?>

<?php if (!$temRegistros): ?>
    <div class="alert alert-info" style="margin: 20px 0;">Nenhuma conta encontrada no período selecionado.</div>
<?php else: ?>

    <!-- Cards de resumo Pagar / Receber -->
    <div class="report-cards-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div class="report-total-card" style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #991b1b; text-transform: uppercase; font-weight: 600;">🔴 A Pagar</div>
            <div style="font-size: 22px; font-weight: 700; color: #dc2626; margin-top: 4px;">
                R$ <?= number_format($totaisSeparados['pagar']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $totaisSeparados['pagar']['qtd'] ?> conta(s) &middot;
                Pago: R$ <?= number_format($totaisSeparados['pagar']['pago'], 2, ',', '.') ?>
            </div>
        </div>
        <div class="report-total-card" style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #166534; text-transform: uppercase; font-weight: 600;">🟢 A Receber</div>
            <div style="font-size: 22px; font-weight: 700; color: #16a34a; margin-top: 4px;">
                R$ <?= number_format($totaisSeparados['receber']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $totaisSeparados['receber']['qtd'] ?> conta(s) &middot;
                Recebido: R$ <?= number_format($totaisSeparados['receber']['pago'], 2, ',', '.') ?>
            </div>
        </div>
        <div class="report-total-card" style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #1e40af; text-transform: uppercase; font-weight: 600;">💰 Saldo Previsto</div>
            <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin-top: 4px;">
                R$ <?= number_format($totaisSeparados['receber']['valor'] - $totaisSeparados['pagar']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                Receber - Pagar &middot;
                <?= ($totaisSeparados['pagar']['qtd'] + $totaisSeparados['receber']['qtd']) ?> conta(s) total
            </div>
        </div>
    </div>

    <!-- Tabela agrupada por data -->
    <table class="table">
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
                <!-- Cabeçalho da data (linha agrupadora) -->
                <tr style="background: #f3f4f6;">
                    <td colspan="<?= count($headers) ?>" style="padding: 8px 12px; font-weight: 700; color: #374151; border-top: 2px solid #d1d5db;">
                        📅 <?= dataIsoParaBr($dataIso) ?> (<?= $sub['qtd'] ?> conta(s))
                    </td>
                </tr>
                <!-- Linhas da data -->
                <?php foreach ($rowsData as $row): ?>
                    <tr>
                        <?php
                        // Remove o __raw__ do array pra mostrar só as colunas visíveis
                        $rowShow = $row;
                        unset($rowShow['__raw__']);
                        $rowShow[0] = ''; // Vencimento vazio (já mostrado no cabeçalho)
                        foreach ($rowShow as $cell): ?>
                            <td><?= htmlspecialchars((string)$cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <!-- Subtotal da data -->
                <?php if ($sub): ?>
                <tr style="background: #fef9c3; font-weight: 600;">
                    <td colspan="5" style="text-align: right; padding: 6px 12px; color: #854d0e; border-bottom: 1px solid #facc15;">
                        Subtotal <?= dataIsoParaBr($dataIso) ?>:
                    </td>
                    <td style="text-align: right; padding: 6px 12px; color: #854d0e; font-variant-numeric: tabular-nums; border-bottom: 1px solid #facc15;">
                        R$ <?= number_format($sub['valor'], 2, ',', '.') ?>
                    </td>
                    <td style="text-align: right; padding: 6px 12px; color: #854d0e; font-variant-numeric: tabular-nums; border-bottom: 1px solid #facc15;">
                        R$ <?= number_format($sub['pago'], 2, ',', '.') ?>
                    </td>
                    <td style="border-bottom: 1px solid #facc15;"></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <?php if ($totais): ?>
        <tfoot>
            <tr class="report-total-row" style="background: #1e40af; color: white;">
                <th colspan="5" style="text-align: right; padding: 10px 12px; font-size: 14px;">TOTAL GERAL (Pagar + Receber):</th>
                <th style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($totaisSeparados['pagar']['valor'] + $totaisSeparados['receber']['valor'], 2, ',', '.') ?>
                </th>
                <th style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($totaisSeparados['pagar']['pago'] + $totaisSeparados['receber']['pago'], 2, ',', '.') ?>
                </th>
                <th style="padding: 10px 12px; font-size: 12px;">
                    <?= ($totaisSeparados['pagar']['qtd'] + $totaisSeparados['receber']['qtd']) ?> contas
                </th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
<?php endif; ?>
