<?php /** @var array $conta */ /** @var array|null $mov */ ?>
<div class="page-header">
    <h1><?= $mov ? 'Editar' : 'Novo' ?> Lançamento</h1>
    <a href="movimentacoes.php?conta_id=<?= (int)$conta['id'] ?>" class="btn">← Voltar</a>
</div>

<p class="muted">Conta: <strong><?= htmlspecialchars($conta['descricao']) ?></strong></p>

<form method="post" action="movimentacao_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($mov['id'] ?? 0) ?>">
    <input type="hidden" name="conta_bancaria_id" value="<?= (int)$conta['id'] ?>">

    <div class="row">
        <div class="form-group col-4">
            <label>Data *</label>
            <input type="date" name="data_movimento" required
                   value="<?= htmlspecialchars($mov['data_movimento'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group col-4">
            <label>Tipo *</label>
            <select name="tipo" required>
                <?php $tipoAtual = $mov['tipo'] ?? 'entrada'; ?>
                <option value="entrada" <?= $tipoAtual === 'entrada' ? 'selected' : '' ?>>↗ Entrada</option>
                <option value="saida"   <?= $tipoAtual === 'saida'   ? 'selected' : '' ?>>↘ Saída</option>
            </select>
        </div>
        <div class="form-group col-4">
            <label>Valor *</label>
            <input type="number" step="0.01" min="0.01" name="valor" required
                   value="<?= htmlspecialchars($mov['valor'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Descrição *</label>
        <input type="text" name="descricao" required maxlength="255"
               value="<?= htmlspecialchars($mov['descricao'] ?? '') ?>"
               placeholder="Ex: Tarifa bancária, transferência, juros...">
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="movimentacoes.php?conta_id=<?= (int)$conta['id'] ?>" class="btn">Cancelar</a>
    </div>
</form>