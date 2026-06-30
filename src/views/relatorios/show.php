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
            <?php foreach ($dados['totais']['cells'] as $cell): ?>
                <th class="report-total-cell"><?= htmlspecialchars((string)$cell) ?></th>
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
        // Pega o cabeçalho correspondente se possível e faz sentido como card
        $header = $dados['headers'][$idx] ?? '';
        // Não vira card se for o "rótulo" Σ X (resumo) — só valores numéricos formatados
        if (preg_match('/^Σ /', $cell)) continue;
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
