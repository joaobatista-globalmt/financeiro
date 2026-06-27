<?php
/** @var array|null $rec */
/** @var array $fornecedores */
/** @var array $categorias */
?>
<div class="page-header">
    <h1><?= $rec ? 'Editar' : 'Nova' ?> Recorrência (Pagar)</h1>
    <a href="recorrencia_pagar.php" class="btn">Voltar</a>
</div>

<form method="post" action="recorrencia_pagar_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($rec['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-8">
            <label>Descrição *</label>
            <input type="text" name="descricao" required maxlength="255"
                   value="<?= htmlspecialchars($rec['descricao'] ?? '') ?>"
                   placeholder="Ex: Aluguel mensal, Internet...">
        </div>
        <div class="form-group col-4">
            <label>Valor *</label>
            <input type="number" step="0.01" min="0.01" name="valor" required
                   value="<?= htmlspecialchars($rec['valor'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>Fornecedor *</label>
            <select name="fornecedor_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($fornecedores as $f): ?>
                    <option value="<?= (int)$f['id'] ?>" <?= ($rec['fornecedor_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['razao_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group col-6">
            <label>Categoria *</label>
            <select name="categoria_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= ($rec['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-3">
            <label>Dia de Vencimento *</label>
            <input type="number" min="1" max="31" name="dia_vencimento" required
                   value="<?= htmlspecialchars($rec['dia_vencimento'] ?? 5) ?>">
            <small class="muted">1-31</small>
        </div>
        <div class="form-group col-3">
            <label>Data Início *</label>
            <input type="date" name="data_inicio" required
                   value="<?= htmlspecialchars($rec['data_inicio'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group col-3">
            <label>Data Fim (opcional)</label>
            <input type="date" name="data_fim"
                   value="<?= htmlspecialchars($rec['data_fim'] ?? '') ?>">
        </div>
        <div class="form-group col-3">
            <label>Forma Pagamento</label>
            <select name="forma_pagamento">
                <?php
                $fp = $rec['forma_pagamento'] ?? 'boleto';
                foreach (['boleto','pix','transferencia','dinheiro','cartao','cheque','outros'] as $f): ?>
                    <option value="<?= $f ?>" <?= $fp === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($rec['observacoes'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativa" value="1" <?= (!$rec || $rec['ativa']) ? 'checked' : '' ?>>
            Ativa
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="recorrencia_pagar.php" class="btn">Cancelar</a>
    </div>
</form>