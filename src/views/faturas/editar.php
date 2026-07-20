<?php require __DIR__ . '/../layout/header.php'; ?>

<h1>✏️ Editar Fatura #<?= (int)$fatura['id'] ?></h1>
<p style="color: #6b7280; margin: 4px 0 20px;">
    Cliente: <strong><?= htmlspecialchars($fatura['cliente_nome'] ?? 'N/A') ?></strong>
    &nbsp;·&nbsp; Status: <strong><?= htmlspecialchars($fatura['status']) ?></strong>
    &nbsp;·&nbsp; Mês: <strong><?= htmlspecialchars($fatura['mes_referencia']) ?></strong>
</p>

<?php if ($fatura['status'] === 'paga'): ?>
    <div style="padding: 14px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 6px; color: #991b1b; margin: 16px 0;">
        <strong>⚠️ Atenção:</strong> Esta fatura está com status <strong>paga</strong> e não pode ser editada.
        Para corrigir, estorne a Conta a Receber correspondente primeiro.
    </div>
<?php else: ?>

<form method="post" action="fatura_acao.php?acao=salvar_edicao" class="form" style="max-width: 700px; background: white; padding: 24px; border-radius: 8px; border: 1px solid #e5e7eb;">
    <input type="hidden" name="id" value="<?= (int)$fatura['id'] ?>">

    <div style="background: #fef3c7; border: 1px solid #fbbf24; border-radius: 6px; padding: 10px 14px; margin-bottom: 20px; font-size: 13px; color: #92400e;">
        <strong>⚠️ Atenção:</strong> Alterações no valor/vencimento podem afetar o relatório e (se já houver CR) a
        movimentação bancária. Proceda com cuidado.
    </div>

    <div class="form-group" style="margin-bottom: 16px;">
        <label>Valor total (R$) *</label>
        <input type="text" name="valor_total" required
               value="<?= htmlspecialchars(number_format((float)$fatura['valor_total'], 2, ',', '.')) ?>"
               style="width: 200px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace; font-size: 16px;">
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 16px;">
        <div>
            <label>Desconto (R$)</label>
            <input type="text" name="valor_desconto"
                   value="<?= htmlspecialchars(number_format((float)$fatura['valor_desconto'], 2, ',', '.')) ?>"
                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace;">
        </div>
        <div>
            <label>Juros (R$)</label>
            <input type="text" name="valor_juros"
                   value="<?= htmlspecialchars(number_format((float)$fatura['valor_juros'], 2, ',', '.')) ?>"
                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace;">
        </div>
        <div>
            <label>Multa (R$)</label>
            <input type="text" name="valor_multa"
                   value="<?= htmlspecialchars(number_format((float)$fatura['valor_multa'], 2, ',', '.')) ?>"
                   style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: monospace;">
        </div>
    </div>

    <div class="form-group" style="margin-bottom: 16px;">
        <label>Data de vencimento *</label>
        <input type="date" name="data_vencimento" required
               value="<?= htmlspecialchars($fatura['data_vencimento']) ?>"
               style="width: 200px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
    </div>

    <div class="form-group" style="margin-bottom: 20px;">
        <label>Observações</label>
        <textarea name="observacoes" rows="4" maxlength="2000"
                  style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px; font-family: inherit; font-size: 14px;"><?= htmlspecialchars($fatura['observacoes'] ?? '') ?></textarea>
    </div>

    <div style="display: flex; gap: 10px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
        <button type="submit" class="btn btn-primary" onclick="return confirm('Salvar alteracoes na fatura #<?= (int)$fatura['id'] ?>?');">💾 Salvar Alterações</button>
        <a href="fatura_acao.php?acao=show&id=<?= (int)$fatura['id'] ?>" class="btn">Cancelar</a>
    </div>
</form>

<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
