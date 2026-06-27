<?php
/** @var array|null $rec */
/** @var array $clientes */
/** @var array $categorias */
?>
<div class="page-header">
    <h1><?= $rec ? 'Editar' : 'Nova' ?> Recorrência (Receber)</h1>
    <a href="recorrencia_receber.php" class="btn">Voltar</a>
</div>

<form method="post" action="recorrencia_receber_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($rec['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-8">
            <label>Descrição *</label>
            <input type="text" name="descricao" required maxlength="255"
                   value="<?= htmlspecialchars($rec['descricao'] ?? '') ?>"
                   placeholder="Ex: Mensalidade, Aluguel recebido...">
        </div>
        <div class="form-group col-4">
            <label>Valor *</label>
            <input type="number" step="0.01" min="0.01" name="valor" required
                   value="<?= htmlspecialchars($rec['valor'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>Cliente *</label>
            <select name="cliente_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($rec['cliente_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['razao_social']) ?>
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
            <label>Forma Recebimento</label>
            <select name="forma_recebimento">
                <?php
                $fp = $rec['forma_recebimento'] ?? 'boleto';
                foreach (['boleto','pix','transferencia','dinheiro','cartao','cheque','deposito','outros'] as $f): ?>
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
        <a href="recorrencia_receber.php" class="btn">Cancelar</a>
    </div>
</form>