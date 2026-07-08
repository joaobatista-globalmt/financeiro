<?php
/**
 * View genérica para relatórios por Fornecedor ou Cliente.
 *
 * Layout clean (sem cores):
 *  - Filtros no topo
 *  - 3 cards de resumo (sem cor de fundo, só bordas)
 *  - Tabela agrupada por entidade:
 *      - Linha simples ABAIXO do nome do cliente/fornecedor (cabeçalho do grupo)
 *      - Linhas de títulos
 *      - Linha simples ACIMA do subtotal de cada entidade
 *      - Subtotal
 *      - Linha simples ACIMA do total geral
 *  - Colunas monetárias alinhadas à direita
 */

$isPagar = ($tipo === 'fornecedor');
$entLabel  = $isPagar ? 'Fornecedor' : 'Cliente';
$entPlural = $isPagar ? 'fornecedores' : 'clientes';
$entIcon   = $isPagar ? '🏢' : '👤';
$docLabel  = $isPagar ? 'CNPJ'      : 'CPF/CNPJ';
$subLabel  = $isPagar ? 'Pago'      : 'Recebido';
$subVar    = $isPagar ? 'pago'      : 'recebido';

$headers = $dados['headers'];
$rows    = $dados['rows'];
$grupos  = $dados['grupos'] ?? [];

// Filtro de status (vem do controller; $status é array)
$statusFiltro = isset($status) && is_array($status) ? $status : [];
$statusOptions = ['pendente', 'aprovada'];
if ($isPagar) {
    $statusOptions[] = 'paga';
} else {
    $statusOptions[] = 'recebida';
}
$statusOptions[] = 'cancelada';

// Calcula totais globais
$totalQtd = 0; $totalValor = 0.0; $totalSub = 0.0; $totalPendente = 0.0;
foreach ($grupos as $g) {
    $totalQtd      += $g['qtd'];
    $totalValor    += $g['valor'];
    $totalSub      += $g[$subVar];
    $totalPendente += $g['pendente'];
}

// Ordena grupos por nome (alfabético)
uasort($grupos, function ($a, $b) { return strcmp($a['nome'], $b['nome']); });

// URL params pra preservar nos botões de export
$exportParams = http_build_query([
    'tipo'        => $tipo,
    'data_inicio' => $dataInicio,
    'data_fim'    => $dataFim,
    'nome'        => $nome,
]);
// Adiciona status[]= à query string (formato array)
if (!empty($statusFiltro)) {
    foreach ($statusFiltro as $s) {
        $exportParams .= '&status%5B%5D=' . urlencode($s);
    }
}
?>
<div class="page-header">
    <h1><?= htmlspecialchars($dados['titulo']) ?></h1>
    <div>
        <a href="relatorio_exportar.php?<?= htmlspecialchars($exportParams) ?>&formato=csv" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?<?= htmlspecialchars($exportParams) ?>&formato=pdf" class="btn">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<form method="get" class="filters-bar">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <div class="form-group" style="flex: 1; min-width: 240px;">
        <label><?= htmlspecialchars($entLabel) ?> (busca parcial)</label>
        <input type="text" name="nome" value="<?= htmlspecialchars($nome) ?>" placeholder="Digite parte do nome..." autocomplete="off">
    </div>
    <div class="form-group" style="min-width: 200px;">
        <label>Status</label>
        <select name="status[]" multiple size="4" style="height: auto; padding: 4px;">
            <?php foreach ($statusOptions as $sOpt): ?>
                <option value="<?= htmlspecialchars($sOpt) ?>" <?= in_array($sOpt, $statusFiltro, true) ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($sOpt)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small style="color: #6b7280; font-size: 10px;">Ctrl/Shift pra múltiplos</small>
    </div>
    <div class="form-group">
        <label>De</label>
        <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
    </div>
    <div class="form-group">
        <label>Até</label>
        <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
    </div>
    <div class="form-group" style="align-self: flex-end;">
        <button type="submit" class="btn btn-primary">Aplicar</button>
        <?php if ($nome !== '' || !empty($statusFiltro)): ?>
            <a href="relatorio_show.php?tipo=<?= urlencode($tipo) ?>&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn btn-secondary">Limpar filtros</a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($grupos)): ?>
    <div class="alert alert-info" style="margin: 20px 0;">
        <?php if ($nome !== '' || !empty($statusFiltro)): ?>
            Nenhum título encontrado com os filtros aplicados
            <?php if ($nome !== ''): ?> (nome: <strong>"<?= htmlspecialchars($nome) ?>"</strong>)<?php endif; ?>
            <?php if (!empty($statusFiltro)): ?> (status: <strong><?= htmlspecialchars(implode(', ', $statusFiltro)) ?></strong>)<?php endif; ?>
            no período selecionado.
        <?php else: ?>
            Nenhum título encontrado no período selecionado.
        <?php endif; ?>
    </div>
<?php else: ?>

    <!-- Filtros aplicados (info pill) -->
    <?php if (!empty($statusFiltro)): ?>
        <div style="margin-bottom: 12px; font-size: 12px; color: #6b7280;">
            Filtro de status ativo:
            <?php foreach ($statusFiltro as $s): ?>
                <span style="display: inline-block; padding: 2px 8px; background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 12px; margin-right: 4px; font-size: 11px;"><?= htmlspecialchars(ucfirst($s)) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Cards de resumo (limpos, sem cor de fundo) -->
    <div class="report-cards-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div class="report-total-card" style="border: 1px solid #d1d5db; border-radius: 6px; padding: 14px 16px; background: #ffffff;">
            <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;">Total Geral</div>
            <div style="font-size: 22px; font-weight: 700; color: #111827; margin-top: 4px;">
                R$ <?= number_format($totalValor, 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                <?= $totalQtd ?> título(s) &middot; <?= count($grupos) ?> <?= htmlspecialchars($entPlural) ?>
            </div>
        </div>
        <div class="report-total-card" style="border: 1px solid #d1d5db; border-radius: 6px; padding: 14px 16px; background: #ffffff;">
            <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;"><?= htmlspecialchars($subLabel) ?></div>
            <div style="font-size: 22px; font-weight: 700; color: #111827; margin-top: 4px;">
                R$ <?= number_format($totalSub, 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                Valor liquidado no período
            </div>
        </div>
        <div class="report-total-card" style="border: 1px solid #d1d5db; border-radius: 6px; padding: 14px 16px; background: #ffffff;">
            <div style="font-size: 12px; color: #6b7280; text-transform: uppercase; font-weight: 600;">Pendente</div>
            <div style="font-size: 22px; font-weight: 700; color: #111827; margin-top: 4px;">
                R$ <?= number_format($totalPendente, 2, ',', '.') ?>
            </div>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                A <?= $isPagar ? 'pagar' : 'receber' ?> no período
            </div>
        </div>
    </div>

    <!-- Tabela agrupada por entidade -->
    <table class="table">
        <colgroup>
            <col style="width: 2.5cm;">  <!-- Vencimento -->
            <col style="width: 6.5cm;">  <!-- Descrição -->
            <col style="width: 3.5cm;">  <!-- Categoria -->
            <col style="width: 2cm;">    <!-- Nº Doc. -->
            <col style="width: 2.5cm;">  <!-- Valor -->
            <col style="width: 2.5cm;">  <!-- Pago/Recebido -->
            <col style="width: 2.5cm;">  <!-- Pendente -->
            <col style="width: 2cm;">    <!-- Status -->
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $entId => $g): ?>
                <!-- Cabeçalho da entidade (nome) - borda inferior simples -->
                <tr>
                    <td colspan="<?= count($headers) ?>" style="padding: 14px 12px 6px 12px; font-weight: 700; color: #111827; font-size: 13px; background: transparent;">
                        <?= $entIcon ?> <?= htmlspecialchars($g['nome']) ?>
                        <?php if (!empty($g['doc']) && $g['doc'] !== ''): ?>
                            <span style="color: #6b7280; font-weight: 400; font-size: 12px; margin-left:6px;">(<?= htmlspecialchars($docLabel) ?>: <?= htmlspecialchars($g['doc']) ?>)</span>
                        <?php endif; ?>
                        <span style="color: #6b7280; font-weight: 400; font-size: 11px; margin-left:8px;">
                            <?= $g['qtd'] ?> título(s) &middot;
                            <?= htmlspecialchars($subLabel) ?>: R$ <?= number_format($g[$subVar], 2, ',', '.') ?> &middot;
                            Pendente: R$ <?= number_format($g['pendente'], 2, ',', '.') ?>
                        </span>
                    </td>
                </tr>
                <!-- Linha simples ABAIXO do nome -->
                <tr>
                    <td colspan="<?= count($headers) ?>" style="padding: 0; border-top: 1px solid #9ca3af; border-bottom: 0;"></td>
                </tr>
                <!-- Linhas de títulos (em ordem cronológica) -->
                <?php foreach ($g['rows_idx'] as $idx): ?>
                    <?php $row = $rows[$idx]; ?>
                    <tr>
                        <?php
                        $rowShow = $row;
                        unset($rowShow['__raw__']);
                        // Colunas monetárias: 4=Valor, 5=Pago/Recebido, 6=Pendente (índices 4,5,6 = 0-based)
                        // Alinhamento à direita: align (HTML) + style inline = compatível com qualquer motor
                        foreach ($rowShow as $colIdx => $cell): ?>
                            <td<?= in_array($colIdx, [4, 5, 6], true) ? ' align="right" style="text-align: right; font-variant-numeric: tabular-nums;"' : '' ?>>
                                <?= htmlspecialchars((string)$cell) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <!-- Linha simples ACIMA do subtotal -->
                <tr>
                    <td colspan="<?= count($headers) ?>" style="padding: 0; border-top: 1px solid #9ca3af; border-bottom: 0;"></td>
                </tr>
                <!-- Subtotal da entidade -->
                <tr>
                    <td colspan="4" align="right" style="text-align: right; padding: 6px 12px; font-weight: 600; color: #111827;">
                        Subtotal <?= htmlspecialchars($g['nome']) ?>:
                    </td>
                    <td align="right" style="text-align: right; padding: 6px 12px; font-weight: 600; color: #111827; font-variant-numeric: tabular-nums;">
                        R$ <?= number_format($g['valor'], 2, ',', '.') ?>
                    </td>
                    <td align="right" style="text-align: right; padding: 6px 12px; font-weight: 600; color: #111827; font-variant-numeric: tabular-nums;">
                        R$ <?= number_format($g[$subVar], 2, ',', '.') ?>
                    </td>
                    <td align="right" style="text-align: right; padding: 6px 12px; font-weight: 600; color: #111827; font-variant-numeric: tabular-nums;">
                        R$ <?= number_format($g['pendente'], 2, ',', '.') ?>
                    </td>
                    <td style="color: #6b7280;"><?= $g['qtd'] ?> título(s)</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <!-- Linha simples ACIMA do total geral -->
            <tr>
                <td colspan="<?= count($headers) ?>" style="padding: 0; border-top: 2px solid #111827; border-bottom: 0;"></td>
            </tr>
            <!-- Total geral -->
            <tr>
                <th colspan="4" align="right" style="text-align: right; padding: 10px 12px; font-size: 14px; color: #111827; font-weight: 700;">
                    TOTAL GERAL (<?= count($grupos) ?> <?= htmlspecialchars($entPlural) ?>):
                </th>
                <th align="right" style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums; color: #111827; font-weight: 700;">
                    R$ <?= number_format($totalValor, 2, ',', '.') ?>
                </th>
                <th align="right" style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums; color: #111827; font-weight: 700;">
                    R$ <?= number_format($totalSub, 2, ',', '.') ?>
                </th>
                <th align="right" style="text-align: right; padding: 10px 12px; font-size: 14px; font-variant-numeric: tabular-nums; color: #111827; font-weight: 700;">
                    R$ <?= number_format($totalPendente, 2, ',', '.') ?>
                </th>
                <th style="padding: 10px 12px; font-size: 12px; color: #6b7280;">
                    <?= $totalQtd ?> título(s)
                </th>
            </tr>
        </tfoot>
    </table>
<?php endif; ?>
