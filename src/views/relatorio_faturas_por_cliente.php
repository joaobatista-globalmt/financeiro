<?php require __DIR__ . '/layout/header.php'; ?>

<h1>👥 Faturas por Cliente</h1>
<p style="color: #6b7280; margin: 4px 0 20px;">Lista todos os clientes com faturas no período, agrupados por cliente com totais. Ordenado por valor total (maior primeiro).</p>

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
            <a href="relatorio_faturas_por_cliente.php" class="btn">Limpar</a>
        </div>
    </div>
</form>

<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 20px 0;">
    <div style="padding: 16px; background: #e0f2fe; border-radius: 8px; border-left: 4px solid #0284c7;">
        <div style="font-size: 11px; color: #075985; text-transform: uppercase; letter-spacing: 0.5px;">Clientes com faturas</div>
        <div style="font-size: 28px; font-weight: 700; color: #075985; margin-top: 4px;"><?= $totalGeralClientes ?></div>
    </div>
    <div style="padding: 16px; background: #dcfce7; border-radius: 8px; border-left: 4px solid #16a34a;">
        <div style="font-size: 11px; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">Valor Total</div>
        <div style="font-size: 22px; font-weight: 700; color: #166534; margin-top: 4px;">R$ <?= number_format($totalGeralValor, 2, ',', '.') ?></div>
    </div>
    <div style="padding: 16px; background: #d1fae5; border-radius: 8px; border-left: 4px solid #059669;">
        <div style="font-size: 11px; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px;">Recebido</div>
        <div style="font-size: 22px; font-weight: 700; color: #065f46; margin-top: 4px;">R$ <?= number_format($totalGeralPago, 2, ',', '.') ?></div>
    </div>
    <div style="padding: 16px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #ca8a04;">
        <div style="font-size: 11px; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">Pendente</div>
        <div style="font-size: 22px; font-weight: 700; color: #92400e; margin-top: 4px;">R$ <?= number_format($totalGeralPendente, 2, ',', '.') ?></div>
    </div>
</div>

<table class="table" style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <thead>
        <tr style="background: #f3f4f6; text-align: left; border-bottom: 2px solid #d1d5db;">
            <th style="padding: 12px 10px;">Cliente</th>
            <th style="padding: 12px 10px;">CPF/CNPJ</th>
            <th style="padding: 12px 10px; text-align: center;">Qtd Faturas</th>
            <th style="padding: 12px 10px; text-align: center;">Pagas / Pendentes</th>
            <th style="padding: 12px 10px; text-align: right;">Valor Total</th>
            <th style="padding: 12px 10px; text-align: right;">Recebido</th>
            <th style="padding: 12px 10px; text-align: right;">Pendente</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($clientes)): ?>
            <tr><td colspan="7" style="padding: 24px; text-align: center; color: #6b7280;">
                Nenhum cliente com faturas no período selecionado.
            </td></tr>
        <?php else: ?>
            <?php foreach ($clientes as $c): ?>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 10px;">
                        <strong><?= htmlspecialchars($c['razao_social']) ?></strong>
                    </td>
                    <td style="padding: 10px; font-family: monospace; font-size: 13px;"><?= htmlspecialchars($c['cpf_cnpj'] ?? '-') ?></td>
                    <td style="padding: 10px; text-align: center;">
                        <span style="background: #f3f4f6; padding: 2px 8px; border-radius: 4px; font-weight: 600;"><?= (int)$c['qtd_faturas'] ?></span>
                    </td>
                    <td style="padding: 10px; text-align: center; font-size: 13px;">
                        <span style="color: #16a34a;"><?= (int)$c['qtd_pagas'] ?></span> /
                        <span style="color: #ca8a04;"><?= (int)$c['qtd_pendentes'] ?></span>
                    </td>
                    <td style="padding: 10px; text-align: right; font-family: monospace; font-weight: 600;">
                        R$ <?= number_format((float)$c['valor_total'], 2, ',', '.') ?>
                    </td>
                    <td style="padding: 10px; text-align: right; font-family: monospace; color: #16a34a;">
                        R$ <?= number_format((float)$c['valor_pago'], 2, ',', '.') ?>
                    </td>
                    <td style="padding: 10px; text-align: right; font-family: monospace; color: #ca8a04;">
                        R$ <?= number_format((float)$c['valor_pendente'], 2, ',', '.') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require __DIR__ . '/layout/footer.php'; ?>
