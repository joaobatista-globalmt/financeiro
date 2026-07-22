<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
$titulo = $dados['titulo'] ?? 'Fluxo de Caixa';
$headers = $dados['headers'] ?? [];
$rows = $dados['rows'] ?? [];
$totais = $dados['totais'] ?? [];

// Como fluxo de caixa eh misto (pagar + receber), extrair totais separados
$valorPagar = 0.0;
$qtdPagar = 0;
$valorReceber = 0.0;
$qtdReceber = 0;
if (!empty($totais['cells']) && is_array($totais['cells'])) {
    foreach ($totais['cells'] as $cell) {
        // cells eh array de strings tipo ['Entradas:', 'R$ X', 'Saidas:', 'R$ Y', 'Saldo:', 'R$ Z']
        // Nada pratico de extrair aqui. Mantemos zerado.
    }
}
$saldo = $valorReceber - $valorPagar;
$temRegistros = !empty($rows);
?>
<div class="page-header">
    <h1><?= htmlspecialchars($titulo) ?></h1>
    <div>
        <a href="relatorio_exportar.php?tipo=fluxo_caixa&formato=csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?tipo=fluxo_caixa&formato=pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn" target="_blank">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<form method="get" class="filters-bar">
    <input type="hidden" name="tipo" value="fluxo_caixa">
    <div class="form-group">
        <label>De</label>
        <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
    </div>
    <div class="form-group">
        <label>Até</label>
        <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Aplicar</button>
    <a href="relatorio_show.php?tipo=fluxo_caixa" class="btn">Limpar</a>
</form>

<?php if (!$temRegistros): ?>
    <div class="alert alert-info" style="margin: 20px 0;">Nenhuma movimentação encontrada no período selecionado.</div>
<?php else: ?>

    <!-- Cards de resumo Pagar / Receber / Saldo -->
    <div class="report-cards-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div class="report-total-card" style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #991b1b; text-transform: uppercase; font-weight: 600;">🔴 Saídas (Pagar)</div>
            <div style="font-size: 22px; font-weight: 700; color: #dc2626; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                —
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                ver tabela abaixo
            </div>
        </div>
        <div class="report-total-card" style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #166534; text-transform: uppercase; font-weight: 600;">🟢 Entradas (Receber)</div>
            <div style="font-size: 22px; font-weight: 700; color: #16a34a; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                —
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                ver tabela abaixo
            </div>
        </div>
        <div class="report-total-card" style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #1e40af; text-transform: uppercase; font-weight: 600;">💰 Saldo de Caixa</div>
            <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                —
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                Entradas - Saídas (ver rodapé)
            </div>
        </div>
    </div>

    <!-- Tabela cronológica -->
    <table class="table">
        <colgroup>
            <col style="width: 2.5cm;">
            <col style="width: 2cm;">
            <col style="width: 7cm;">
            <col style="width: 4cm;">
            <col style="width: 3cm;">
            <col style="width: 2.8cm;">
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
                $rowShow = $row;
                unset($rowShow['__raw__']); ?>
                <tr>
                    <?php foreach ($rowShow as $cell): ?>
                        <td><?= htmlspecialchars((string)$cell) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if (!empty($totais['cells'])): ?>
        <tfoot>
            <tr class="report-total-row" style="background: #1e40af; color: white;">
                <?php foreach ($totais['cells'] as $i => $cell): ?>
                    <th <?= $i === 0 ? 'colspan="2"' : '' ?> style="text-align: <?= $i === 0 ? 'right' : 'right' ?>; padding: 10px 12px; font-size: 13px; font-variant-numeric: tabular-nums;">
                        <?= htmlspecialchars((string)$cell) ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
<?php endif; ?>
