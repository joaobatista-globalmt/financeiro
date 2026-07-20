<?php
/** @var array $fatura */
/** @var array $itens */
$isPaga   = $fatura['status'] === 'paga';
$isCancel = $fatura['status'] === 'cancelada';
?>
<div class="page-header">
    <h1>Fatura #<?= (int)$fatura['id'] ?> - <?= htmlspecialchars($fatura['cliente_nome']) ?></h1>
    <div>
        <a href="faturas.php?mes=<?= urlencode($fatura['mes_referencia']) ?>" class="btn">Voltar</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_sucesso'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_sucesso']) ?></div>
    <?php unset($_SESSION['flash_sucesso']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_erro'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_erro']) ?></div>
    <?php unset($_SESSION['flash_erro']); ?>
<?php endif; ?>

<div class="fatura-grid">
    <div>
        <h3>Cliente</h3>
        <p>
            <a href="cliente_form.php?id=<?= (int)$fatura['cliente_id'] ?>">
                <?= htmlspecialchars($fatura['cliente_nome']) ?>
            </a><br>
            <?php if (!empty($fatura['cliente_doc'])): ?>
                <small class="muted"><?= htmlspecialchars($fatura['cliente_doc']) ?></small><br>
            <?php endif; ?>
            <?php if (!empty($fatura['cliente_email'])): ?>
                <small class="muted"><?= htmlspecialchars($fatura['cliente_email']) ?></small>
            <?php endif; ?>
        </p>
    </div>
    <div>
        <h3>Referencia</h3>
        <p>
            <strong>Mes:</strong> <?= htmlspecialchars($fatura['mes_referencia']) ?><br>
            <strong>Emissao:</strong> <?= date('d/m/Y', strtotime($fatura['data_emissao'])) ?><br>
            <strong>Vencimento:</strong> <?= date('d/m/Y', strtotime($fatura['data_vencimento'])) ?>
        </p>
    </div>
    <div>
        <h3>Status</h3>
        <p>
            <span class="badge badge-<?= htmlspecialchars($fatura['status']) ?>">
                <?= htmlspecialchars(ucfirst($fatura['status'])) ?>
            </span>
            <?php if ($fatura['data_pagamento']): ?>
                <br><small>Pago em <?= date('d/m/Y', strtotime($fatura['data_pagamento'])) ?>
                (R$ <?= number_format((float)$fatura['valor_pago'], 2, ',', '.') ?>)</small>
            <?php endif; ?>
        </p>
    </div>
</div>

<h3>Itens da Fatura</h3>
<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Descricao</th>
                <th>Tipo</th>
                <th>Valor Unitario</th>
                <th>Valor Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($itens as $i): ?>
                <tr>
                    <td><?= (int)$i['id'] ?></td>
                    <td><?= htmlspecialchars($i['descricao']) ?></td>
                    <td><small class="muted"><?= htmlspecialchars($i['tipo_cobranca'] ?? '-') ?></small></td>
                    <td style="text-align:right;">R$ <?= number_format((float)$i['valor_unitario'], 2, ',', '.') ?></td>
                    <td style="text-align:right;">R$ <?= number_format((float)$i['valor_total'], 2, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;"><strong>Total</strong></td>
                <td style="text-align:right;"><strong>R$ <?= number_format((float)$fatura['valor_total'], 2, ',', '.') ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php if (!empty($fatura['observacoes'])): ?>
    <h3>Observacoes</h3>
    <pre style="background:#f9fafb; padding:12px; border-radius:6px; white-space:pre-wrap;"><?= htmlspecialchars($fatura['observacoes']) ?></pre>
<?php endif; ?>

        <?php if (!empty($podeGerarReceber) && !$isPaga && !$isCancel): ?>
        <form method="post" action="fatura_acao.php?acao=gerar_receber" style="display:inline;"
              onsubmit="return confirm('Gerar conta a receber a partir desta fatura? (acao idempotente: se ja existir, sera bloqueado)');">
            <input type="hidden" name="id" value="<?= (int)$fatura['id'] ?>">
            <button type="submit" class="btn btn-primary">🔁 Gerar Conta a Receber</button>
        </form>
        <?php elseif (!$isPaga && !$isCancel): ?>
        <?php
            // Mostra qual conta a receber foi gerada (para audit)
            // Opcional: mostrar info rapida "Conta ja gerada #N"
        ?>
        <?php endif; ?>

<!-- Acoes -->
<div class="form-actions" style="margin-top: 24px; display:flex; gap: 12px; flex-wrap: wrap;">
    <?php if (!$isPaga && !$isCancel): ?>
        <form method="post" action="fatura_acao.php?acao=pagar" style="display:flex; gap:8px; align-items:flex-end; flex-wrap: wrap;">
            <input type="hidden" name="id" value="<?= (int)$fatura['id'] ?>">
            <div class="form-group" style="margin:0;">
                <label>Data pgto</label>
                <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Valor pago (R$)</label>
                <input type="text" name="valor_pago" inputmode="decimal"
                       value="<?= number_format((float)$fatura['valor_total'], 2, ',', '.') ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Marcar como Paga</button>
        </form>

        <form method="post" action="fatura_acao.php?acao=cancelar" style="display:inline;"
              onsubmit="return confirm('Cancelar esta fatura?');">
            <input type="hidden" name="id" value="<?= (int)$fatura['id'] ?>">
            <input type="hidden" name="motivo" value="Cancelada pelo usuario">
            <button type="submit" class="btn btn-danger">Cancelar Fatura</button>
        </form>
    <?php endif; ?>

    <?php if (in_array($fatura['status'], ['aberta', 'cancelada', 'vencida'], true)): ?>
        <form method="post" action="fatura_acao.php?acao=excluir" style="display:inline;"
              onsubmit="return confirm('Excluir esta fatura? Esta acao nao pode ser desfeita.');">
            <input type="hidden" name="id" value="<?= (int)$fatura['id'] ?>">
            <button type="submit" class="btn">Excluir</button>
        </form>
    <?php endif; ?>
</div>

<style>
.fatura-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin: 16px 0; }
.fatura-grid h3 { margin: 0 0 8px 0; font-size: 14px; color: #6b7280; text-transform: uppercase; }
.badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.badge-aberta { background:#fef3c7; color:#92400e; }
.badge-paga { background:#d1fae5; color:#065f46; }
.badge-parcial { background:#dbeafe; color:#1e40af; }
.badge-vencida { background:#fee2e2; color:#991b1b; }
.badge-cancelada { background:#e5e7eb; color:#374151; }
@media (max-width: 768px) { .fatura-grid { grid-template-columns: 1fr; } }
</style>
