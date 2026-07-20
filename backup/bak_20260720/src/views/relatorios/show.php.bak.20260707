<?php /** @var string $tipo */ /** @var array $dados */ /** @var string $dataInicio */ /** @var string $dataFim */ ?>
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

<table class="table">
    <thead>
        <tr>
            <?php foreach ($dados['headers'] as $h): ?>
                <th><?= htmlspecialchars($h) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($dados['rows'])): ?>
            <tr><td colspan="<?= count($dados['headers']) ?>" class="muted center">Nenhum registro encontrado.</td></tr>
        <?php else: foreach ($dados['rows'] as $row): ?>
            <tr>
                <?php foreach ($row as $cell): ?>
                    <td><?= htmlspecialchars((string)$cell) ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($dados['totais'])): ?>
    <tfoot>
        <tr class="report-total-row">
            <?php
            $cells = $dados['totais']['cells'];
            $label = $dados['totais']['label'];
            // Remove o label "TOTAL" do in\u00edcio
            array_shift($cells);
            // 1\u00aa string n\u00e3o-vazia \u00e9 subLabel (geralmente "Σ X categorias")
            // O 1\u00ba NUM\u00c9RICO \u00e9 o ponto de virada: tudo antes dele vai pro colspan
            $subLabel = '';
            $values = [];
            $firstValueIdx = -1;
            foreach ($cells as $i => $c) {
                $cv = (string)$c;
                if ($cv === '') continue;
                if ($firstValueIdx === -1) {
                    if (is_numeric(str_replace([',','.',' '], '', $cv))) {
                        $firstValueIdx = $i;
                        $values[] = $cv;
                    } else {
                        $subLabel = $cv;
                    }
                } else {
                    $values[] = $cv;
                }
            }
            // colspan = (headers - values count) garante alinhamento perfeito
            $colspan = count($dados['headers']) - count($values);
            $colspan = max(1, $colspan);
            ?>
            <th class="report-total-cell" colspan="<?= $colspan ?>" style="text-align:left; padding-left:12px;">
                <?= htmlspecialchars($label) ?>
                <?php if ($subLabel !== ''): ?>
                    <span style="opacity:.75; font-weight:400; font-size:11px; margin-left:6px;"><?= htmlspecialchars($subLabel) ?></span>
                <?php endif; ?>
            </th>
            <?php foreach ($values as $v): ?>
                <th class="report-total-cell" style="text-align:right;"><?= htmlspecialchars($v) ?></th>
            <?php endforeach; ?>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<?php if (!empty($dados['totais'])): ?>
<div class="report-total-cards">
    <?php
    $label = $dados['totais']['label'];
    $cards = [];
    foreach ($dados['totais']['cells'] as $idx => $cell) {
        $cell = (string)$cell;
        if ($cell === '' || $cell === null) continue;
        // Pula o label "TOTAL" (cells[0])
        if ($idx === 0 && $cell === $label) continue;
        // Pula strings "Σ X" (sub-resumo, já fica no colspan)
        if (preg_match('/^Σ /', $cell)) continue;
        // Pula "TOTAL" duplicado em qualquer posição
        if (strtoupper($cell) === 'TOTAL') continue;
        // Pula "máx:" (tá no rodapé)
        if (strpos($cell, 'máx:') === 0) continue;
        // Só mostra cards com valores numéricos ou formatados (R$, etc)
        $header = $dados['headers'][$idx] ?? '';
        $cards[] = ['header' => $header, 'value' => $cell];
    }
    ?>
    <?php foreach ($cards as $c): ?>
        <div class="report-total-card">
            <div class="report-total-card-label"><?= htmlspecialchars($c['header'] ?: $label) ?></div>
            <div class="report-total-card-value"><?= htmlspecialchars($c['value']) ?></div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
