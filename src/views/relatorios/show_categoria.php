<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
/** @var string $tipo */

$headers = $dados['headers'];
$rows = $dados['rows'];
$grupos = $dados['grupos'] ?? [];
$totaisSeparados = $dados['totais_separados'] ?? ['pagar' => ['qtd'=>0,'valor'=>0,'pago'=>0], 'receber' => ['qtd'=>0,'valor'=>0,'pago'=>0]];
$totais = $dados['totais'] ?? null;

// Ordena grupos por nome (alfabético)
uasort($grupos, function ($a, $b) {
    return strcmp($a['nome'], $b['nome']);
});
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
$totalCategorias = count($grupos);
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
                <?= $totalCategorias ?> categoria(s)
            </div>
        </div>
    </div>

    <!-- Tabela agrupada por categoria -->
    <table class="table">
        <colgroup>
            <col style="width: 2.5cm;">  <!-- Vencimento -->
            <col style="width: 7cm;">     <!-- Descrição -->
            <col style="width: 4cm;">     <!-- Entidade -->
            <col style="width: 2cm;">     <!-- Tipo -->
            <col style="width: 2.8cm;">   <!-- Valor -->
            <col style="width: 3cm;">     <!-- Valor Pago/Recebido -->
            <col style="width: 2cm;">     <!-- Status -->
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grupos as $catId => $g):
                // Cor da categoria (fundo suave, cor de borda/texto forte)
                $corFundo = '#f9fafb'; // default
                $corBorda = '#9ca3af';
                $corTexto = '#1f2937';
                if (!empty($g['cor']) && preg_match('/^#[0-9A-Fa-f]{6}$/', $g['cor'])) {
                    // Cria fundo bem claro a partir da cor
                    $r = hexdec(substr($g['cor'], 1, 2));
                    $gr = hexdec(substr($g['cor'], 3, 2));
                    $b = hexdec(substr($g['cor'], 5, 2));
                    $corFundo = sprintf('#%02x%02x%02x', $r, $gr, $b);
                    $corBorda = $g['cor'];
                    // Texto: escuro se fundo claro, branco se escuro
                    $luminancia = (0.299 * $r + 0.587 * $gr + 0.114 * $b) / 255;
                    $corTexto = $luminancia > 0.6 ? '#1f2937' : '#ffffff';
                }
            ?>
                <!-- Cabeçalho da categoria (linha agrupadora) -->
                <tr>
                    <td colspan="<?= count($headers) ?>" style="padding: 8px 12px; font-weight: 700; color: <?= $corTexto ?>; background: <?= $corBorda ?>; border-top: 2px solid <?= $corBorda ?>;">
                        🏷️ <?= htmlspecialchars($g['nome']) ?>
                        <span style="opacity:.85; font-weight:400; font-size:11px; margin-left:8px;">
                            (<?= $g['qtd'] ?> conta(s) &middot;
                            Pagar: R$ <?= number_format($g['pagar'], 2, ',', '.') ?> &middot;
                            Receber: R$ <?= number_format($g['receber'], 2, ',', '.') ?>)
                        </span>
                    </td>
                </tr>
                <!-- Linhas da categoria (em ordem cronológica) -->
                <?php foreach ($g['rows_idx'] as $idx): ?>
                    <?php $row = $rows[$idx]; ?>
                    <tr style="background: <?= $corFundo ?>;">
                        <?php
                        $rowShow = $row;
                        unset($rowShow['__raw__']);
                        foreach ($rowShow as $cell): ?>
                            <td><?= htmlspecialchars((string)$cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <!-- Subtotal da categoria -->
                <tr style="background: #fef9c3; font-weight: 600;">
                    <td colspan="4" style="text-align: right; padding: 6px 12px; color: #854d0e; border-bottom: 1px solid #facc15;">
                        Subtotal <?= htmlspecialchars($g['nome']) ?>:
                    </td>
                    <td style="text-align: right; padding: 6px 12px; color: #854d0e; font-variant-numeric: tabular-nums; border-bottom: 1px solid #facc15;">
                        R$ <?= number_format($g['valor'], 2, ',', '.') ?>
                    </td>
                    <td style="text-align: right; padding: 6px 12px; color: #854d0e; font-variant-numeric: tabular-nums; border-bottom: 1px solid #facc15;">
                        R$ <?= number_format($g['pago'], 2, ',', '.') ?>
                    </td>
                    <td style="border-bottom: 1px solid #facc15;"></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <?php if ($totais): ?>
        <tfoot>
            <tr class="report-total-row" style="background: #1e40af; color: white;">
                <th colspan="4" style="text-align: right; padding: 10px 12px; font-size: 14px;">TOTAL GERAL (<?= $totalCategorias ?> categorias):</th>
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
