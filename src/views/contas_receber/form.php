<?php
/** @var array|null $conta */
/** @var array $clientes */
/** @var array $categorias */
/** @var array $contasBanco */

$status = $conta['status'] ?? 'pendente';
$ehRecebido = $status === 'recebida';
?>
<div class="page-header">
    <h1><?= $conta ? 'Editar Conta a Receber' : 'Nova Conta a Receber' ?></h1>
    <a href="contas_receber.php" class="btn">Voltar</a>
</div>

<form method="post" action="conta_receber_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($conta['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-8">
            <label>Descrição *</label>
            <input type="text" name="descricao" required maxlength="255"
                   value="<?= htmlspecialchars($conta['descricao'] ?? '') ?>"
                   placeholder="Ex: Mensalidade junho, Venda produto X...">
        </div>
        <div class="form-group col-4">
            <label>Nº do Documento</label>
            <input type="text" name="numero_documento" maxlength="100"
                   value="<?= htmlspecialchars($conta['numero_documento'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>Cliente *</label>
            <select name="cliente_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($conta['cliente_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
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
                    <option value="<?= (int)$cat['id'] ?>" <?= ($conta['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-3">
            <label>Valor *</label>
            <input type="number" step="0.01" min="0.01" name="valor" required
                   value="<?= htmlspecialchars($conta['valor'] ?? '') ?>">
        </div>
        <div class="form-group col-3">
            <label>Data Emissão</label>
            <input type="date" name="data_emissao"
                   value="<?= htmlspecialchars($conta['data_emissao'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group col-3">
            <label>Vencimento *</label>
            <input type="date" name="data_vencimento" required
                   value="<?= htmlspecialchars($conta['data_vencimento'] ?? '') ?>">
        </div>
        <div class="form-group col-3">
            <label>Parcelas</label>
            <input type="number" min="1" max="48" name="parcelas"
                   value="<?= htmlspecialchars($conta['parcelas'] ?? 1) ?>"
                   <?= $conta ? 'readonly' : '' ?>>
        </div>
    </div>

    <div class="form-group col-6">
        <label>Forma de Recebimento</label>
        <select name="forma_recebimento">
            <?php
            $fp = $conta['forma_recebimento'] ?? 'boleto';
            $formas = ['boleto','pix','transferencia','dinheiro','cartao','cheque','deposito','outros'];
            foreach ($formas as $f): ?>
                <option value="<?= $f ?>" <?= $fp === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($conta['observacoes'] ?? '') ?></textarea>
    </div>

    <?php if ($conta && in_array($status, ['pendente', 'aprovada'], true) && Permissao::tem('receber')): ?>
    <fieldset id="receber" class="payment-section">
        <legend>💰 Registrar Recebimento</legend>
        <div class="row">
            <div class="form-group col-4">
                <label>Conta Bancária *</label>
                <select name="conta_bancaria_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($contasBanco as $cb): ?>
                        <option value="<?= (int)$cb['id'] ?>">
                            <?= htmlspecialchars($cb['descricao']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-4">
                <label>Data Recebimento *</label>
                <input type="date" name="data_recebimento" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group col-4">
                <label>Valor Recebido *</label>
                <input type="number" step="0.01" name="valor_recebido" value="<?= htmlspecialchars($conta['valor']) ?>" required>
            </div>
        </div>
    </fieldset>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="contas_receber.php" class="btn">Cancelar</a>
    </div>
</form>

<?php if ($ehRecebido): ?>
<div class="alert alert-info">
    <strong>Conta recebida em <?= dataIsoParaBr($conta['data_recebimento']) ?>.</strong>
    Valor: R$ <?= number_format((float)$conta['valor_recebido'], 2, ',', '.') ?>.
</div>
<?php endif; ?>