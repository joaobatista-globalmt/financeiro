<?php
/** @var array $mov */
/** @var array $contasBanco */
?>
<div class="page-header">
    <h1>🔧 Corrigir Movimentação Automática</h1>
    <a href="movimentacoes.php?conta_id=<?= (int)$mov['conta_bancaria_id'] ?>" class="btn">← Voltar</a>
</div>

<div class="alert alert-info" style="margin-bottom: 16px;">
    <strong>ℹ️ Por que não posso editar valor/tipo/data?</strong><br>
    Esta movimentação foi gerada automaticamente a partir de
    <?= $mov['origem'] === 'conta_pagar' ? 'uma <strong>conta a pagar</strong>' : 'uma <strong>conta a receber</strong>' ?>.
    O valor, tipo e data vêm da conta de origem (não dá pra mexer aqui sem
    ficar inconsistente).<br>
    Se você errou <strong>a conta bancária</strong> ou quer ajustar a
    <strong>descrição</strong>, tá aqui. Se errou o <strong>valor</strong> ou a
    <strong>data</strong>, use o botão <strong>Estornar</strong> (volta a conta pra
    "aprovada" e você pode pagar de novo com os dados certos).
</div>

<div class="card" style="padding: 16px; margin-bottom: 16px; background: #f9fafb;">
    <h3 style="margin-top: 0;">Dados atuais (somente leitura)</h3>
    <div class="row">
        <div class="form-group col-3">
            <label>Data</label>
            <input type="date" disabled value="<?= htmlspecialchars($mov['data_movimento']) ?>">
        </div>
        <div class="form-group col-3">
            <label>Tipo</label>
            <input type="text" disabled value="<?= $mov['tipo'] === 'entrada' ? '↗ Entrada' : '↘ Saída' ?>">
        </div>
        <div class="form-group col-3">
            <label>Valor</label>
            <input type="text" disabled value="R$ <?= number_format((float)$mov['valor'], 2, ',', '.') ?>">
        </div>
        <div class="form-group col-3">
            <label>Origem</label>
            <input type="text" disabled value="<?= htmlspecialchars($mov['origem']) ?>">
        </div>
    </div>
    <?php if ($mov['origem'] === 'conta_pagar' && $mov['conta_pagar_id']): ?>
        <p class="muted">Conta a pagar: <a href="conta_detalhe.php?id=<?= (int)$mov['conta_pagar_id'] ?>">#<?= (int)$mov['conta_pagar_id'] ?></a></p>
    <?php elseif ($mov['origem'] === 'conta_receber' && $mov['conta_receber_id']): ?>
        <p class="muted">Conta a receber: <a href="conta_receber_detalhe.php?id=<?= (int)$mov['conta_receber_id'] ?>">#<?= (int)$mov['conta_receber_id'] ?></a></p>
    <?php endif; ?>
</div>

<form method="post" action="corrigir_movimentacao.php" class="form">
    <input type="hidden" name="id" value="<?= (int)$mov['id'] ?>">

    <div class="form-group">
        <label>Conta Bancária *</label>
        <select name="conta_bancaria_id" required>
            <option value="">Selecione...</option>
            <?php foreach ($contasBanco as $cb): ?>
                <option value="<?= (int)$cb['id'] ?>" <?= $cb['id'] == $mov['conta_bancaria_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cb['descricao']) ?>
                    <?= $cb['banco'] ? '(' . htmlspecialchars($cb['banco']) . ')' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <small class="muted">Se a conta certa não está aqui, cadastre-a em "Contas Bancárias" primeiro.</small>
    </div>

    <div class="form-group">
        <label>Descrição *</label>
        <input type="text" name="descricao" required maxlength="255"
               value="<?= htmlspecialchars($mov['descricao']) ?>">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Salvar Correção</button>
        <a href="movimentacoes.php?conta_id=<?= (int)$mov['conta_bancaria_id'] ?>" class="btn">Cancelar</a>

        <?php if (Permissao::tem('excluir')): ?>
            <span style="margin-left: auto;">
                <form method="get" action="estornar_movimentacao.php" style="display: inline;"
                      onsubmit="return confirm('ATENÇÃO: Estornar vai CRIAR uma movimentação inversa (entrada/saída oposta) e VOLTAR a conta de origem para o status APROVADA (desfaz o pagamento). Continuar?')">
                    <input type="hidden" name="id" value="<?= (int)$mov['id'] ?>">
                    <button type="submit" class="btn btn-danger">↩️ Estornar (reverter tudo)</button>
                </form>
            </span>
        <?php endif; ?>
    </div>
</form>
