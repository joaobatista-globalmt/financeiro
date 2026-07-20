<?php require __DIR__ . '/layout/header.php'; ?>

<h1>Relatorio de Faturas Geradas</h1>
<p style="color: #6b7280; margin: 4px 0 20px;">Lista todas as faturas geradas no periodo, com totalizadores por status.</p>

<!-- Form de filtro -->
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
            <a href="relatorio_faturas.php" class="btn">Limpar</a>
        </div>
    </div>
</form>

<!-- Cards de totalizadores -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 20px 0;">
    <div style="padding: 16px; background: #e0f2fe; border-radius: 8px; border-left: 4px solid #0284c7;">
        <div style="font-size: 11px; color: #075985; text-transform: uppercase; letter-spacing: 0.5px;">Qtd Faturas</div>
        <div style="font-size: 28px; font-weight: 700; color: #075985; margin-top: 4px;"><?= count($faturas) ?></div>
    </div>
    <div style="padding: 16px; background: #dcfce7; border-radius: 8px; border-left: 4px solid #16a34a;">
        <div style="font-size: 11px; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">Valor Total</div>
        <div style="font-size: 22px; font-weight: 700; color: #166534; margin-top: 4px;">R$ <?= number_format($totalGeral, 2, ',', '.') ?></div>
    </div>
    <div style="padding: 16px; background: #d1fae5; border-radius: 8px; border-left: 4px solid #059669;">
        <div style="font-size: 11px; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px;">Recebido</div>
        <div style="font-size: 22px; font-weight: 700; color: #065f46; margin-top: 4px;">R$ <?= number_format($totalPago, 2, ',', '.') ?></div>
    </div>
    <div style="padding: 16px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #ca8a04;">
        <div style="font-size: 11px; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">Pendente</div>
        <div style="font-size: 22px; font-weight: 700; color: #92400e; margin-top: 4px;">R$ <?= number_format($totalPendente, 2, ',', '.') ?></div>
    </div>
</div>

<!-- Tabela de resultados -->
<table class="table" style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <thead>
        <tr style="background: #f3f4f6; text-align: left; border-bottom: 2px solid #d1d5db;">
            <th style="padding: 12px 10px;">ID</th>
            <th style="padding: 12px 10px;">Data Emissao</th>
            <th style="padding: 12px 10px;">Mes Ref.</th>
            <th style="padding: 12px 10px;">Cliente</th>
            <th style="padding: 12px 10px; text-align: right;">Valor</th>
            <th style="padding: 12px 10px;">Status</th>
            <th style="padding: 12px 10px;">Vencimento</th>
            <th style="padding: 12px 10px;">Acoes</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($faturas)): ?>
            <tr><td colspan="8" style="padding: 24px; text-align: center; color: #6b7280;">
                Nenhuma fatura encontrada com os filtros aplicados.
                <br><small>Ajuste as datas ou limpe o filtro de mes para ver mais resultados.</small>
            </td></tr>
        <?php else: ?>
            <?php foreach ($faturas as $f): ?>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 10px; font-family: monospace;">#<?= (int)$f['id'] ?></td>
                    <td style="padding: 10px;"><?= date('d/m/Y', strtotime($f['data_emissao'])) ?></td>
                    <td style="padding: 10px; font-family: monospace;"><?= htmlspecialchars($f['mes_referencia']) ?></td>
                    <td style="padding: 10px;"><?= htmlspecialchars($f['cliente_nome']) ?></td>
                    <td style="padding: 10px; text-align: right; font-family: monospace; font-weight: 500;">
                        R$ <?= number_format((float)$f['valor_total'], 2, ',', '.') ?>
                    </td>
                    <td style="padding: 10px;">
                        <?php
                            $badge_color = match($f['status']) {
                                'paga'      => '#dcfce7; color: #166534',
                                'aberta'    => '#fef3c7; color: #92400e',
                                'vencida'   => '#fee2e2; color: #991b1b',
                                'parcial'   => '#dbeafe; color: #1e40af',
                                'cancelada' => '#f3f4f6; color: #6b7280',
                                default     => '#f3f4f6; color: #374151',
                            };
                        ?>
                        <span style="background: <?= $badge_color ?>; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: 500;">
                            <?= htmlspecialchars($f['status']) ?>
                        </span>
                    </td>
                    <td style="padding: 10px; font-size: 13px; color: #6b7280;">
                        <?= date('d/m/Y', strtotime($f['data_vencimento'])) ?>
                    </td>
                    <td style="padding: 10px;">
                        <a href="fatura_acao.php?acao=show&id=<?= (int)$f['id'] ?>" style="color: #2563eb; text-decoration: none;">detalhe →</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($faturas)): ?>
    <div style="margin-top: 20px; padding: 12px; background: #f9fafb; border-radius: 6px; font-size: 13px; color: #6b7280;">
        <strong>Distribuicao por status:</strong>
        <?php
            $parts = [];
            foreach ($countPorStatus as $st => $q) {
                $parts[] = "<strong>$q</strong> $st";
            }
            echo implode(' &middot; ', $parts);
        ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/layout/footer.php'; ?>
