<?php
/** @var array $dados */
/** @var string $dataInicio */
/** @var string $dataFim */
/** @var string $tipo */

$headers = $dados['headers'];
$rowsPagar   = $dados['rows_pagar']   ?? [];
$rowsReceber = $dados['rows_receber'] ?? [];
$sep = $dados['totais_separados'] ?? [
    'pagar'   => ['qtd' => 0, 'valor' => 0.0, 'max_atraso' => 0],
    'receber' => ['qtd' => 0, 'valor' => 0.0, 'max_atraso' => 0],
];
$totalQtd = $sep['pagar']['qtd'] + $sep['receber']['qtd'];
$temRegistros = $totalQtd > 0;
?>
<style>
/* Layout clean: sem cores, bordas cinza, total com borda preta 2px */
.cards-clean {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 20px;
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
.tbl-clean { width: 100%; border-collapse: collapse; }
.tbl-clean thead th {
    background: #ffffff;
    border-top: 1px solid #9ca3af;
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
.tbl-clean tfoot th {
    background: #ffffff;
    border-top: 2px solid #9ca3af;
    border-bottom: 2px solid #9ca3af;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 700;
    color: #111827;
}
.tbl-clean tfoot tr.total-geral th {
    border-top: 2px solid #000;
    border-bottom: 2px solid #000;
    font-size: 14px;
    padding: 10px 12px;
}
.section-title {
    margin: 24px 0 8px 0;
    font-size: 16px;
    color: #111827;
    font-weight: 700;
}
.total-geral-box {
    margin-top: 24px;
    padding: 16px;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-top: 2px solid #000;
    border-bottom: 2px solid #000;
    border-radius: 4px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>

<div class="page-header">
    <h1><?= htmlspecialchars($dados['titulo']) ?></h1>
    <div>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=csv&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn">📥 CSV</a>
        <a href="relatorio_exportar.php?tipo=<?= urlencode($tipo) ?>&formato=pdf&data_inicio=<?= urlencode($dataInicio) ?>&data_fim=<?= urlencode($dataFim) ?>" class="btn">📄 PDF</a>
        <a href="relatorios.php" class="btn">← Voltar</a>
    </div>
</div>

<div style="background: #ffffff; border: 1px solid #d1d5db; padding: 10px 14px; margin-bottom: 16px; border-radius: 4px; font-size: 13px;">
    Exibindo contas com vencimento anterior a hoje (<strong><?= dataIsoParaBr(date('Y-m-d')) ?></strong>) e status pendente ou aprovada.
</div>

<?php if (!$temRegistros): ?>
    <div class="alert alert-success" style="margin: 20px 0;">✅ Nenhuma conta atrasada. Tudo em dia!</div>
<?php else: ?>

    <!-- Cards de resumo Pagar / Receber / Saldo -->
    <div class="cards-clean">
        <div class="card-clean">
            <div class="label">A Pagar (atrasado)</div>
            <div class="valor valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($sep['pagar']['valor'], 2, ',', '.') ?></div>
            <div class="sub"><?= $sep['pagar']['qtd'] ?> conta(s) &middot; Maior atraso: <?= $sep['pagar']['max_atraso'] ?> dia(s)</div>
        </div>
        <div class="card-clean">
            <div class="label">A Receber (atrasado)</div>
            <div class="valor valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($sep['receber']['valor'], 2, ',', '.') ?></div>
            <div class="sub"><?= $sep['receber']['qtd'] ?> conta(s) &middot; Maior atraso: <?= $sep['receber']['max_atraso'] ?> dia(s)</div>
        </div>
        <div class="card-clean">
            <div class="label">Saldo (Receber - Pagar)</div>
            <div class="valor valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($sep['receber']['valor'] - $sep['pagar']['valor'], 2, ',', '.') ?></div>
            <div class="sub"><?= $totalQtd ?> conta(s) total &middot; <?= max($sep['pagar']['max_atraso'], $sep['receber']['max_atraso']) ?> dia(s) de maior atraso</div>
        </div>
    </div>

    <!-- Tabela CONTAS A PAGAR -->
    <h2 class="section-title">Contas a Pagar Atrasadas</h2>
    <table class="tbl-clean">
        <colgroup>
            <col style="width: 2.5cm;">
            <col style="width: 7cm;">
            <col style="width: 4cm;">
            <col style="width: 3cm;">
            <col style="width: 2.8cm;">
            <col style="width: 1.8cm;">
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h === 'Entidade' ? 'Fornecedor' : $h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rowsPagar)): ?>
                <tr><td colspan="<?= count($headers) ?>" style="text-align: center; color: #6b7280; padding: 18px; font-style: italic;">Nenhuma conta a pagar atrasada.</td></tr>
            <?php else: foreach ($rowsPagar as $row): ?>
                <?php
                $rowShow = $row;
                unset($rowShow['__raw__']);
                // colunas: 0=Vencimento, 1=Descrição, 2=Entidade, 3=Categoria, 4=Valor, 5=Dias Atraso
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$rowShow[0]) ?></td>
                    <td><?= htmlspecialchars((string)$rowShow[1]) ?></td>
                    <td><?= htmlspecialchars((string)$rowShow[2]) ?></td>
                    <td><?= htmlspecialchars((string)$rowShow[3]) ?></td>
                    <td class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;"><?= htmlspecialchars((string)$rowShow[4]) ?></td>
                    <td class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;"><?= htmlspecialchars((string)$rowShow[5]) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rowsPagar)): ?>
        <tfoot>
            <tr>
                <th colspan="4" class="valor" align="right" style="text-align: right;">SUBTOTAL A PAGAR (atrasado):</th>
                <th class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($sep['pagar']['valor'], 2, ',', '.') ?></th>
                <th style="text-align: left;"><?= $sep['pagar']['qtd'] ?> conta(s)</th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <!-- Tabela CONTAS A RECEBER -->
    <h2 class="section-title">Contas a Receber Atrasadas</h2>
    <table class="tbl-clean">
        <colgroup>
            <col style="width: 2.5cm;">
            <col style="width: 7cm;">
            <col style="width: 4cm;">
            <col style="width: 3cm;">
            <col style="width: 2.8cm;">
            <col style="width: 1.8cm;">
        </colgroup>
        <thead>
            <tr>
                <?php foreach ($headers as $h): ?>
                    <th><?= htmlspecialchars($h === 'Entidade' ? 'Cliente' : $h) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rowsReceber)): ?>
                <tr><td colspan="<?= count($headers) ?>" style="text-align: center; color: #6b7280; padding: 18px; font-style: italic;">Nenhuma conta a receber atrasada.</td></tr>
            <?php else: foreach ($rowsReceber as $row): ?>
                <?php
                $rowShow = $row;
                unset($rowShow['__raw__']);
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$rowShow[0]) ?></td>
                    <td><?= htmlspecialchars((string)$rowShow[1]) ?></td>
                    <td><?= htmlspecialchars((string)$rowShow[2]) ?></td>
                    <td><?= htmlspecialchars((string)$rowShow[3]) ?></td>
                    <td class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;"><?= htmlspecialchars((string)$rowShow[4]) ?></td>
                    <td class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;"><?= htmlspecialchars((string)$rowShow[5]) ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
        <?php if (!empty($rowsReceber)): ?>
        <tfoot>
            <tr>
                <th colspan="4" class="valor" align="right" style="text-align: right;">SUBTOTAL A RECEBER (atrasado):</th>
                <th class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums;">R$ <?= number_format($sep['receber']['valor'], 2, ',', '.') ?></th>
                <th style="text-align: left;"><?= $sep['receber']['qtd'] ?> conta(s)</th>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>

    <!-- TOTAL GERAL (borda preta 2px) -->
    <div class="total-geral-box">
        <div>
            <div style="font-size: 12px; text-transform: uppercase; color: #6b7280; font-weight: 600; letter-spacing: 0.5px;">TOTAL GERAL ATRASADO</div>
            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                <?= $sep['pagar']['qtd'] ?> a pagar + <?= $sep['receber']['qtd'] ?> a receber = <?= $totalQtd ?> conta(s)
            </div>
        </div>
        <div style="text-align: right;">
            <div class="valor" align="right" style="text-align: right; font-variant-numeric: tabular-nums; font-size: 24px; font-weight: 700; color: #000000;">
                R$ <?= number_format($sep['pagar']['valor'] + $sep['receber']['valor'], 2, ',', '.') ?>
            </div>
            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">
                Saldo previsto: R$ <?= number_format($sep['receber']['valor'] - $sep['pagar']['valor'], 2, ',', '.') ?>
            </div>
        </div>
    </div>

<?php endif; ?>
