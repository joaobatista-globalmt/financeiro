<?php require __DIR__ . '/layout/header.php'; ?>

<h1>⚠️ Faturas Vencidas</h1>
<p style="color: #6b7280; margin: 4px 0 20px;">Faturas com vencimento passado (ate hoje) e status diferente de 'paga' ou 'cancelada'. Ordenadas por dias de atraso (mais atrasadas primeiro).</p>

<!-- Cards de totalizadores -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 20px 0;">
    <div style="padding: 16px; background: #fee2e2; border-radius: 8px; border-left: 4px solid #dc2626;">
        <div style="font-size: 11px; color: #991b1b; text-transform: uppercase; letter-spacing: 0.5px;">Qtd Vencidas</div>
        <div style="font-size: 28px; font-weight: 700; color: #991b1b; margin-top: 4px;"><?= $totalQtd ?></div>
    </div>
    <div style="padding: 16px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #ca8a04;">
        <div style="font-size: 11px; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px;">Valor Total Vencido</div>
        <div style="font-size: 22px; font-weight: 700; color: #92400e; margin-top: 4px;">R$ <?= number_format($totalValor, 2, ',', '.') ?></div>
    </div>
    <div style="padding: 16px; background: #dcfce7; border-radius: 8px; border-left: 4px solid #16a34a;">
        <div style="font-size: 11px; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">Ja Recebido</div>
        <div style="font-size: 22px; font-weight: 700; color: #166534; margin-top: 4px;">R$ <?= number_format($totalPago, 2, ',', '.') ?></div>
    </div>
    <div style="padding: 16px; background: #fecaca; border-radius: 8px; border-left: 4px solid #991b1b;">
        <div style="font-size: 11px; color: #7f1d1d; text-transform: uppercase; letter-spacing: 0.5px;">Pendente Receber</div>
        <div style="font-size: 22px; font-weight: 700; color: #7f1d1d; margin-top: 4px;">R$ <?= number_format($totalPendente, 2, ',', '.') ?></div>
    </div>
</div>

<!-- Cards de faixa de atraso -->
<div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin: 20px 0;">
    <?php foreach ($faixas as $label => $qtd): ?>
        <div style="padding: 12px; background: white; border: 1px solid #e5e7eb; border-radius: 6px; text-align: center;">
            <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;"><?= $label ?> dias</div>
            <div style="font-size: 22px; font-weight: 700; color: #<?= $label === '90+' ? '991b1b' : ($label === '61-90' ? 'dc2626' : '4b5563') ?>; margin-top: 2px;"><?= $qtd ?></div>
            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">R$ <?= number_format($valoresFaixa[$label], 2, ',', '.') ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Tabela de faturas vencidas -->
<table class="table" style="width: 100%; border-collapse: collapse; background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;">
    <thead>
        <tr style="background: #f3f4f6; text-align: left; border-bottom: 2px solid #d1d5db;">
            <th style="padding: 12px 10px;">Cliente</th>
            <th style="padding: 12px 10px;">Contato</th>
            <th style="padding: 12px 10px; text-align: center;">Vencimento</th>
            <th style="padding: 12px 10px; text-align: center;">Dias Atraso</th>
            <th style="padding: 12px 10px; text-align: right;">Valor</th>
            <th style="padding: 12px 10px; text-align: center;">Status</th>
            <th style="padding: 12px 10px; text-align: center;">CR</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($vencidas)): ?>
            <tr><td colspan="7" style="padding: 24px; text-align: center; color: #16a34a;">
                ✅ Nenhuma fatura vencida no momento!
            </td></tr>
        <?php else: ?>
            <?php foreach ($vencidas as $v): ?>
                <?php
                    $dias = (int)$v['dias_atraso'];
                    $cor_dias = $dias > 90 ? '#991b1b' : ($dias > 60 ? '#dc2626' : ($dias > 30 ? '#ea580c' : '#ca8a04'));
                ?>
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 10px;">
                        <strong><?= htmlspecialchars($v['cliente_nome']) ?></strong>
                        <br><small style="color: #6b7280;">FAT #<?= (int)$v['id'] ?> · <?= htmlspecialchars($v['mes_referencia']) ?></small>
                    </td>
                    <td style="padding: 10px; font-size: 12px;">
                        <?php if (!empty($v['telefone'])): ?>
                            📞 <?= htmlspecialchars($v['telefone']) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($v['email'])): ?>
                            ✉️ <?= htmlspecialchars($v['email']) ?>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px; text-align: center; font-family: monospace;"><?= date('d/m/Y', strtotime($v['data_vencimento'])) ?></td>
                    <td style="padding: 10px; text-align: center; font-weight: 700; color: <?= $cor_dias ?>; font-size: 16px;">
                        <?= $dias ?>d
                    </td>
                    <td style="padding: 10px; text-align: right; font-family: monospace; font-weight: 600;">
                        R$ <?= number_format((float)$v['valor_total'], 2, ',', '.') ?>
                    </td>
                    <td style="padding: 10px; text-align: center;">
                        <span style="background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                            <?= htmlspecialchars($v['status']) ?>
                        </span>
                    </td>
                    <td style="padding: 10px; text-align: center; font-size: 12px;">
                        <?php if (!empty($v['cr_id'])): ?>
                            <a href="conta_receber_detalhe.php?id=<?= (int)$v['cr_id'] ?>" style="color: #2563eb; text-decoration: none;">
                                CR #<?= (int)$v['cr_id'] ?> →
                            </a>
                        <?php else: ?>
                            <span style="color: #6b7280;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php require __DIR__ . '/layout/footer.php'; ?>
