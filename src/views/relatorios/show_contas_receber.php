<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
/** @var array $statusFiltro */

$headers = $dados['headers'] ?? [];
$rows = $dados['rows'] ?? [];
$totais = $dados['totais'] ?? [];

// Como este relatorio eh SOMENTE contas a receber, o card "A Pagar" fica zerado
$valorReceber = (float)($totais['valor'] ?? 0);
$qtdReceber = (int)($totais['qtd'] ?? 0);
$valorPagar = 0.0;
$qtdPagar = 0;
$saldo = $valorReceber - $valorPagar; // sempre positivo aqui
$temRegistros = $qtdReceber > 0;
?>
<div class="page-header">
    <h1><?= htmlspecialchars($dados['titulo'] ?? 'Contas a Receber') ?></h1>
    <div>
        <a href="relatorio_exportar.php?tipo=contas_receber&formato=csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>&status=<?= urlencode(implode(',', $statusFiltro)) ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?tipo=contas_receber&formato=pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>&status=<?= urlencode(implode(',', $statusFiltro)) ?>" class="btn" target="_blank">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<form method="get" class="filters-bar">
    <input type="hidden" name="tipo" value="contas_receber">
    <div class="form-group" style="min-width: 260px;">
        <label>Status (vazio = todos)</label>
        <div style="display: flex; gap: 12px; flex-wrap: wrap; padding: 4px 0;">
            <?php
            $statusOptions = [
                'pendente'  => 'Pendente',
                'aprovada'  => 'Aprovada',
                'recebida'  => 'Recebida',
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
        <label>De</label>
        <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
    </div>
    <div class="form-group">
        <label>Até</label>
        <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Aplicar</button>
    <a href="relatorio_show.php?tipo=contas_receber" class="btn">Limpar</a>
</form>

<?php if (!$temRegistros): ?>
    <div class="alert alert-info" style="margin: 20px 0;">Nenhuma conta a receber encontrada no período selecionado.</div>
<?php else: ?>

    <!-- Cards de resumo Pagar / Receber / Saldo -->
    <div class="report-cards-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div class="report-total-card" style="background: #f9fafb; border-left: 4px solid #9ca3af; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;">🔴 A Pagar</div>
            <div style="font-size: 22px; font-weight: 700; color: #9ca3af; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                —
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                Fornecedor (n/a)
            </div>
        </div>
        <div class="report-total-card" style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #166534; text-transform: uppercase; font-weight: 600;">🟢 A Receber</div>
            <div style="font-size: 22px; font-weight: 700; color: #16a34a; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                R$ <?= number_format($valorReceber, 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $qtdReceber ?> conta(s) &middot;
                Recebido: R$ <?= number_format((float)($totais['recebido'] ?? 0), 2, ',', '.') ?>
            </div>
        </div>
        <div class="report-total-card" style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #1e40af; text-transform: uppercase; font-weight: 600;">💰 Saldo Previsto</div>
            <div style="font-size: 22px; font-weight: 700; color: #2563eb; margin-top: 4px; text-align: right; font-variant-numeric: tabular-nums;">
                R$ <?= number_format($saldo, 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                Receber - Pagar
            </div>
        </div>
    </div>

    <!-- Tabela -->
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
                <th colspan="4" style="text-align: right; padding: 10px 12px; font-size: 14px;">TOTAL GERAL:</th>
                <th style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format($valorReceber, 2, ',', '.') ?>
                </th>
                <th style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums;">
                    R$ <?= number_format((float)($totais['recebido'] ?? 0), 2, ',', '.') ?>
                </th>
                <th style="padding: 10px 12px; font-size: 12px;">
                    <?= $qtdReceber ?> contas
                </th>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
