<?php require __DIR__ . '/layout/header.php'; ?>

<h1>📊 Faturas por Status</h1>
<p style="color: #6b7280; margin: 4px 0 20px;">Distribuicao de faturas por status no periodo, com totais. Util pra ver o volume de cada situacao.</p>

<form method="get" class="form" style="margin: 20px 0; padding: 16px; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
        <div>
            <label>Data inicial (emissao)</label>
            <input type="date" name="data_inicial" value="<?= htmlspecialchars($dataInicial) ?>">
        </div>
        <div>
            <label>Data final (emissao)</label>
            <input type="date" name="data_final" value="<?= htmlspecialchars($dataFinal) ?>">
        </div>
        <div>
            <label>Mes de referencia</label>
            <select name="mes_referencia">
                <option value="">-- Todos --</option>
                <?php foreach ($mesesDisponiveis as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= $m === $mesRef ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="relatorio_faturas_por_status.php" class="btn">Limpar</a>
        </div>
    </div>
</form>

<table class="table" style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <thead>
        <tr style="background: #f3f4f6; text-align: left; border-bottom: 2px solid #d1d5db;">
            <th style="padding: 12px 10px;">Status</th>
            <th style="padding: 12px 10px; text-align: center;">Qtd Faturas</th>
            <th style="padding: 12px 10px; text-align: center;">% do Total</th>
            <th style="padding: 12px 10px; text-align: right;">Valor Total</th>
            <th style="padding: 12px 10px; text-align: right;">Valor Recebido</th>
            <th style="padding: 12px 10px; text-align: right;">Saldo</th>
        </tr>
    </thead>
    <tbody>
        <?php
            $status_colors = [
                'aberta'    => '#fef3c7; color: #92400e',
                'paga'      => '#dcfce7; color: #166534',
                'vencida'   => '#fee2e2; color: #991b1b',
                'parcial'   => '#dbeafe; color: #1e40af',
                'cancelada' => '#f3f4f6; color: #6b7280',
            ];
        ?>
        <?php if (empty($stats)): ?>
            <tr><td colspan="6" style="padding: 24px; text-align: center; color: #6b7280;">
                Nenhuma fatura encontrada com os filtros aplicados.
            </td></tr>
        <?php else: ?>
            <?php foreach ($stats as $s): ?>
                <?php
                    $pct = $totalQtd > 0 ? round(((int)$s['qtd'] / $totalQtd) * 100, 1) : 0;
                    $saldo = (float)$s['valor_total'] - (float)$s['valor_pago'];
                    $cor = $status_colors[$s['status']] ?? '#f3f4f6; color: #374151';
                ?>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 10px;">
                        <span style="background: <?= $cor ?>; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 600;">
                            <?= htmlspecialchars($s['status']) ?>
                        </span>
                    </td>
                    <td style="padding: 10px; text-align: center; font-weight: 600;"><?= (int)$s['qtd'] ?></td>
                    <td style="padding: 10px; text-align: center;">
                        <div style="background: #e5e7eb; height: 8px; border-radius: 4px; position: relative; max-width: 200px; margin: 0 auto;">
                            <div style="background: #3b82f6; height: 100%; border-radius: 4px; width: <?= $pct ?>%;"></div>
                        </div>
                        <small style="color: #6b7280;"><?= $pct ?>%</small>
                    </td>
                    <td style="padding: 10px; text-align: right; font-family: monospace; font-weight: 600;">
                        R$ <?= number_format((float)$s['valor_total'], 2, ',', '.') ?>
                    </td>
                    <td style="padding: 10px; text-align: right; font-family: monospace; color: #16a34a;">
                        R$ <?= number_format((float)$s['valor_pago'], 2, ',', '.') ?>
                    </td>
                    <td style="padding: 10px; text-align: right; font-family: monospace; color: #<?= $saldo > 0 ? 'ca8a04' : '16a34a' ?>;">
                        R$ <?= number_format($saldo, 2, ',', '.') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr style="background: #f9fafb; font-weight: 700; border-top: 2px solid #d1d5db;">
                <td style="padding: 12px 10px;">TOTAL</td>
                <td style="padding: 12px 10px; text-align: center;"><?= $totalQtd ?></td>
                <td style="padding: 12px 10px; text-align: center;">100%</td>
                <td style="padding: 12px 10px; text-align: right; font-family: monospace;">
                    R$ <?= number_format($totalValor, 2, ',', '.') ?>
                </td>
                <td style="padding: 12px 10px; text-align: right; font-family: monospace; color: #16a34a;">
                    R$ <?= number_format(array_sum(array_column($stats, 'valor_pago')), 2, ',', '.') ?>
                </td>
                <td style="padding: 12px 10px; text-align: right; font-family: monospace;">
                    R$ <?= number_format($totalValor - array_sum(array_column($stats, 'valor_pago')), 2, ',', '.') ?>
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php require __DIR__ . '/layout/footer.php'; ?>
