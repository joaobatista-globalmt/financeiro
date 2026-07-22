<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
/** @var array $statusFiltro */
/** @var string $nomeFiltro */
$titulo = $dados['titulo'] ?? 'Por Fornecedor';
$headers = $dados['headers'] ?? [];
$rows = $dados['rows'] ?? [];
$grupos = $dados['grupos'] ?? [];

// Como este relatorio eh SOMA das contas a pagar por fornecedor
$valorPagar = 0.0;
$qtdPagar = 0;
$valorReceber = 0.0;
$qtdReceber = 0;
foreach ($grupos as $g) {
    $valorPagar += (float)($g['valor'] ?? 0);
    $qtdPagar   += (int)($g['qtd'] ?? 0);
}
$saldo = $valorReceber - $valorPagar;
$temRegistros = $qtdPagar > 0;
?>
<div class="page-header">
    <h1><?= htmlspecialchars($titulo) ?></h1>
    <div>
        <a href="relatorio_exportar.php?tipo=fornecedor&formato=csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>&status=<?= urlencode(implode(',', $statusFiltro)) ?>&nome=<?= urlencode($nomeFiltro) ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?tipo=fornecedor&formato=pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>&status=<?= urlencode(implode(',', $statusFiltro)) ?>&nome=<?= urlencode($nomeFiltro) ?>" class="btn" target="_blank">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<form method="get" class="filters-bar">
    <input type="hidden" name="tipo" value="fornecedor">
    <div class="form-group" style="min-width: 260px;">
        <label>Status (vazio = todos)</label>
        <div style="display: flex; gap: 12px; flex-wrap: wrap; padding: 4px 0;">
            <?php
            $statusOptions = [
                'pendente'  => 'Pendente',
                'aprovada'  => 'Aprovada',
                'paga'      => 'Paga',
                'cancelada' => 'Cancelada',
            ];
            foreach ($statusOptions as $val => $label): ?>
                <label style="display: flex; align-items: center; gap: 4px; font-weight: normal; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="status[]" value="<?= htmlspecialchars($val) ?>"
                        <?= in_array($val, $statusFiltro) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="form-group">
        <label>Fornecedor (Razão Social)</label>
        <input type="text" name="nome" value="<?= htmlspecialchars($nomeFiltro) ?>" placeholder="Razão Social">
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
    <a href="relatorio_show.php?tipo=fornecedor" class="btn">Limpar</a>
</form>

<?php if (!$temRegistros): ?>
    <div class="alert alert-info" style="margin: 20px 0;">Nenhum fornecedor encontrado no período selecionado.</div>
<?php else: ?>

    <!-- Cards de resumo -->
    <div class="report-cards-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div class="report-total-card" style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #991b1b; text-transform: uppercase; font-weight: 600;">🔴 A Pagar (fornecedores)</div>
            <div style="font-size: 22px; font-weight: 700; color: #dc2626; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                R$ <?= number_format($valorPagar, 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $qtdPagar ?> conta(s) &middot; <?= count($grupos) ?> fornecedor(es)
            </div>
        </div>
        <div class="report-total-card" style="background: #f9fafb; border-left: 4px solid #9ca3af; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;">🟢 A Receber</div>
            <div style="font-size: 22px; font-weight: 700; color: #9ca3af; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">—</div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">n/a (relatório de fornecedores)</div>
        </div>
        <div class="report-total-card" style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #1e40af; text-transform: uppercase; font-weight: 600;">💰 Saldo Previsto</div>
            <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                R$ <?= number_format($saldo, 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">Receber - Pagar</div>
        </div>
    </div>

    <!-- Ranking de fornecedores -->
    <h2 style="margin: 8px 0 12px 0; font-size: 16px; color: #111827; font-weight: 700;">📊 Ranking de Fornecedores</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; margin-bottom: 24px;">
        <?php foreach ($grupos as $g): ?>
            <div style="background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 14px;">
                <h3 style="margin: 0 0 6px 0; font-size: 14px; color: #111827;"><?= htmlspecialchars($g['nome']) ?></h3>
                <p style="margin: 2px 0; font-size: 12px; color: #6b7280;">CNPJ/CPF: <?= htmlspecialchars($g['doc'] ?? '—') ?></p>
                <p style="margin: 4px 0; font-size: 12px; color: #6b7280;"><strong><?= $g['qtd'] ?></strong> conta(s)</p>
                <p style="margin: 2px 0; font-size: 13px; color: #111827;">Total: <strong>R$ <?= number_format((float)($g['valor'] ?? 0), 2, ',', '.') ?></strong></p>
                <p style="margin: 2px 0; font-size: 12px; color: #16a34a;">Pago: R$ <?= number_format((float)($g['pago'] ?? 0), 2, ',', '.') ?></p>
                <p style="margin: 2px 0; font-size: 12px; color: #dc2626;">Pendente: R$ <?= number_format((float)($g['pendente'] ?? 0), 2, ',', '.') ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabela detalhada -->
    <h2 style="margin: 8px 0 12px 0; font-size: 16px; color: #111827; font-weight: 700;">📋 Detalhes</h2>
    <table class="table">
        <colgroup>
            <col style="width: 2.5cm;">
            <col style="width: 7cm;">
            <col style="width: 4cm;">
            <col style="width: 3cm;">
            <col style="width: 2.8cm;">
            <col style="width: 3cm;">
            <col style="width: 2cm;">
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
        <tfoot>
            <tr class="report-total-row" style="background: #1e40af; color: white;">
                <th colspan="4" style="text-align: right; padding: 10px 12px; font-size: 14px;">TOTAL GERAL (<?= count($grupos) ?> fornecedores):</th>
                <th style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($valorPagar, 2, ',', '.') ?>
                </th>
                <th style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format(array_sum(array_map(fn($g) => (float)($g['pago'] ?? 0), $grupos)), 2, ',', '.') ?>
                </th>
                <th style="padding: 10px 12px; font-size: 12px;"><?= $qtdPagar ?> contas</th>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
