<?php
/** @var array $conta */
/** @var array $contasBanco */
?>
<div class="page-header">
    <h1>✏️ Editar Recebimento</h1>
    <a href="conta_receber_detalhe.php?id=<?= (int)$conta['id'] ?>" class="btn">← Voltar</a>
</div>

<div class="alert alert-info" style="margin-bottom: 16px;">
    <strong>ℹ️ O que esta tela altera:</strong>
    <ul style="margin: 6px 0 0 16px;">
        <li>Esta conta a receber (data_recebimento, valor_recebido, conta_bancaria_id, forma_recebimento)</li>
        <li>A movimentação bancária vinculada (atualizada com os mesmos valores)</li>
        <li>O saldo da conta bancária <strong>não</strong> é recalculado retroativamente — o que já entrou/saiu do extrato continua</li>
    </ul>
    <strong style="display:block; margin-top: 6px;">Se errou o valor e o saldo precisa mudar:</strong>
    Estorne o recebimento (volta para aprovada) e receba de novo com o valor correto.
</div>

<div class="card" style="padding: 16px; margin-bottom: 16px; background: #f9fafb;">
    <h3 style="margin-top: 0;">Conta a receber</h3>
    <p>
        <strong><?= htmlspecialchars($conta['descricao']) ?></strong>
        (Cliente: <?= htmlspecialchars($conta['cliente_nome']) ?>)
        <br>
        Valor original: <strong>R$ <?= number_format((float)$conta['valor'], 2, ',', '.') ?></strong>
        &middot; Vencimento: <?= dataIsoParaBr($conta['data_vencimento']) ?>
    </p>
</div>

<form method="post" action="editar_recebimento.php" class="form">
    <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">

    <div class="row">
        <div class="form-group col-3">
            <label>Data Recebimento *</label>
            <input type="date" name="data_recebimento" required
                   value="<?= htmlspecialchars($conta['data_recebimento']) ?>">
        </div>
        <div class="form-group col-3">
            <label>Valor Recebido *</label>
            <input type="number" step="0.01" min="0.01" name="valor_recebido" required
                   value="<?= htmlspecialchars($conta['valor_recebido']) ?>">
        </div>
        <div class="form-group col-3">
            <label>Conta Bancária *</label>
            <select name="conta_bancaria_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($contasBanco as $cb): ?>
                    <option value="<?= (int)$cb['id'] ?>" <?= $cb['id'] == $conta['conta_bancaria_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cb['descricao']) ?>
                        <?= $cb['banco'] ? '(' . htmlspecialchars($cb['banco']) . ')' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group col-3">
            <label>Forma de Recebimento *</label>
            <select name="forma_recebimento" required>
                <?php
                $formas = ['boleto','pix','transferencia','dinheiro','cartao','cheque','outros'];
                $fpAtual = $conta['forma_recebimento'] ?? 'boleto';
                foreach ($formas as $f): ?>
                    <option value="<?= $f ?>" <?= $fpAtual === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Salvar Edição</button>
        <a href="conta_receber_detalhe.php?id=<?= (int)$conta['id'] ?>" class="btn">Cancelar</a>
    </div>
</form>
