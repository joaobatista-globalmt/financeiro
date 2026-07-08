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
?>
<style>
/* Layout clean: sem cores, bordas cinza, total com borda preta 2px */
.filtros-bar-clean {
    background: #ffffff;
    border: 1px solid #d1d5db;
    padding: 12px 16px;
    margin-bottom: 16px;
    border-radius: 4px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
}
.filtros-bar-clean .form-group { margin-bottom: 0; }
.cards-clean {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}
.card-clean {
    background: #ffffff;
    border: 1px solid #d1d5db;
    padding: 14px 16px;
    border-radius: 4px;
}
.card-clean .label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.card-clean .valor {
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin-top: 4px;
}
.card-clean .sub {
    font-size: 12px;
    color: #6b7280;
    margin-top: 2px;
}
/* Tabela clean */
.tbl-clean { width: 100%; border-collapse: collapse; }
.tbl-clean thead th {
    background: #ffffff;
    border-bottom: 2px solid #9ca3af;
    padding: 8px 10px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #111827;
}
.tbl-clean tbody td {
    border-bottom: 1px solid #e5e7eb;
    padding: 6px 10px;
    font-size: 13px;
    color: #1f2937;
}
.tbl-clean tr.data-header td {
    background: #ffffff;
    border-top: 1px solid #9ca3af;
    border-bottom: 1px solid #9ca3af;
    font-weight: 700;
    color: #111827;
    padding: 6px 10px;
}
.tbl-clean tr.subtotal td {
    background: #ffffff;
    border-top: 1px solid #9ca3af;
    border-bottom: 1px solid #9ca3af;
    font-weight: 700;
    color: #111827;
}
.tbl-clean tfoot th {
    background: #ffffff;
    border-top: 2px solid #000000;
    border-bottom: 2px solid #000000;
    padding: 10px 12px;
    font-size: 14px;
    font-weight: 700;
    color: #000000;
}
</style>

<div class="page-header">
    <h1>🔴 <?= htmlspecialchars($dados['titulo']) ?></h1>
    <div>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?><?= !empty($statusFiltro) ? '&status[]=' . implode('&status[]=', array_map('urlencode', $statusFiltro)) : '' ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?><?= !empty($statusFiltro) ? '&status[]=' . implode('&status[]=', array_map('urlencode', $statusFiltro)) : '' ?>" class="btn">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<form method="get" class="filtros-bar-clean">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">

    <div class="form-group">
        <label>Tipo</label>
        <select name="tipo_fixo" disabled>
            <option selected>🔴 PAGAR (fixo neste relatório)</option>
        </select>
    </div>

    <div class="form-group" style="min-width: 260px;">
        <label>Status (vazio = todos)</label>
        <div style="display: flex; gap: 12px; flex-wrap: wrap; padding: 4px 0;">
            <?php foreach ($statusOptions as $val => $label): ?>
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
    <div class="filtros-bar-clean" style="font-size: 13px;">
        <strong>🔍 Filtros aplicados:</strong>
        <span>Tipo: <strong>PAGAR</strong></span> |
        <span>Status: <strong><?= htmlspecialchars($filtroStatusLabel) ?></strong></span> |
        <span>Período: <strong><?= htmlspecialchars(dataIsoParaBr($dataInicio)) ?></strong> a <strong><?= htmlspecialchars(dataIsoParaBr($dataFim)) ?></strong></span>
    </div>

    <!-- Cards de resumo -->
    <div class="cards-clean">
        <div class="card-clean">
            <div class="label">🔴 Total</div>
            <div class="valor valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($totais['valor'], 2, ',', '.') ?></div>
            <div class="sub"><?= $totais['qtd'] ?> conta(s) no período</div>
        </div>
        <div class="card-clean">
            <div class="label">🟢 Pago</div>
            <div class="valor valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($totais['pago'], 2, ',', '.') ?></div>
            <div class="sub"><?= $totaisPorStatus['paga']['qtd'] ?? 0 ?> conta(s) liquidadas</div>
        </div>
        <div class="card-clean">
            <div class="label">🟠 Pendente</div>
            <div class="valor valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($totais['pendente'], 2, ',', '.') ?></div>
            <div class="sub">A pagar ainda</div>
        </div>
        <div class="card-clean">
            <div class="label">🔵 Qtd</div>
            <div class="valor valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;"><?= $totais['qtd'] ?></div>
            <div class="sub">contas no período</div>
        </div>
    </div>

    <!-- Breakdown por status (se filtrou vários status) -->
    <?php if (!empty($statusFiltro) && count($statusFiltro) > 1): ?>
    <div class="filtros-bar-clean" style="font-size: 13px;">
        <strong>📊 Por status:</strong>
        <?php foreach ($statusFiltro as $st):
            $ts = $totaisPorStatus[$st] ?? ['qtd' => 0, 'valor' => 0, 'pago' => 0];
        ?>
            <span style="margin-left: 12px;">
                <strong><?= htmlspecialchars(ucfirst($st)) ?>:</strong>
                <?= $ts['qtd'] ?> conta(s) &middot;
                <span class="valor" style="font-variant-numeric: tabular-nums;">R$ <?= number_format($ts['valor'], 2, ',', '.') ?></span>
            </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tabela agrupada por data -->
    <table class="tbl-clean">
        <colgroup>
            <col style="width: 2.2cm;">
            <col style="width: 4.5cm;">
            <col style="width: 3.5cm;">
            <col style="width: 2.5cm;">
            <col style="width: 2.3cm;">
            <col style="width: 2.3cm;">
            <col style="width: 1.8cm;">
            <col style="width: 1.8cm;">
            <col style="width: 2.3cm;">
        </colgroup>
        <thead>
            <tr>
                <th>Vencimento</th>
                <th>Descrição</th>
                <th>Fornecedor</th>
                <th>Categoria</th>
                <th class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">Valor</th>
                <th class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">Valor Pago</th>
                <th>Status</th>
                <th>Forma Pgto.</th>
                <th>Nº Documento</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rowsPorData as $dataIso => $rowsData):
                $sub = $subtotaisPorData[$dataIso] ?? null;
            ?>
                <tr class="data-header">
                    <td colspan="9">📅 <?= dataIsoParaBr($dataIso) ?> (<?= $sub['qtd'] ?? 0 ?> conta(s))</td>
                </tr>
                <?php foreach ($rowsData as $row): ?>
                    <tr>
                        <?php
                        $rowShow = $row;
                        unset($rowShow['__raw__']);
                        // colunas: 0=Vencimento (vazio pq já no header), 1=Descricao, 2=Fornecedor, 3=Categoria,
                        //          4=Valor, 5=Valor Pago, 6=Status, 7=Forma Pgto, 8=Nº Doc
                        ?>
                        <td></td>
                        <td><?= htmlspecialchars((string)$rowShow[1]) ?></td>
                        <td><?= htmlspecialchars((string)$rowShow[2]) ?></td>
                        <td><?= htmlspecialchars((string)$rowShow[3]) ?></td>
                        <td class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;"><?= htmlspecialchars((string)$rowShow[4]) ?></td>
                        <td class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;"><?= htmlspecialchars((string)$rowShow[5]) ?></td>
                        <td><?= htmlspecialchars((string)$rowShow[6]) ?></td>
                        <td><?= htmlspecialchars((string)$rowShow[7]) ?></td>
                        <td><?= htmlspecialchars((string)$rowShow[8]) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($sub): ?>
                <tr class="subtotal">
                    <td colspan="4" class="valor" align="right" style="text-align: right;">Subtotal <?= dataIsoParaBr($dataIso) ?>:</td>
                    <td class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($sub['valor'], 2, ',', '.') ?></td>
                    <td class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($sub['pago'], 2, ',', '.') ?></td>
                    <td colspan="3"></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" class="valor" align="right" style="text-align: right;">TOTAL GERAL:</th>
                <th class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($totais['valor'], 2, ',', '.') ?></th>
                <th class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($totais['pago'], 2, ',', '.') ?></th>
                <th colspan="3" style="text-align: left;"><?= $totais['qtd'] ?> contas</th>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
