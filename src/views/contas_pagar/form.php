<?php
/** @var array|null $conta */
/** @var array $fornecedores */
/** @var array $categorias */
/** @var array $contasBanco */

$status = $conta['status'] ?? 'pendente';
$ehPago = $status === 'paga';
?>
<div class="page-header">
    <h1><?= $conta ? 'Editar Conta a Pagar' : 'Nova Conta a Pagar' ?></h1>
    <a href="contas_pagar.php" class="btn">Voltar</a>
</div>

<form method="post" action="conta_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($conta['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-8">
            <label>Descrição *</label>
            <input type="text" name="descricao" required maxlength="255"
                   value="<?= htmlspecialchars($conta['descricao'] ?? '') ?>"
                   placeholder="Ex: Aluguel junho, Energia Elétrica...">
        </div>
        <div class="form-group col-4">
            <label>Nº do Documento</label>
            <input type="text" name="numero_documento" maxlength="100"
                   value="<?= htmlspecialchars($conta['numero_documento'] ?? '') ?>"
                   placeholder="Ex: 12345, NF-001">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>Fornecedor *</label>
            <select name="fornecedor_id" required>
                <option value="">Selecione...</option>
                <?php foreach ($fornecedores as $f): ?>
                    <option value="<?= (int)$f['id'] ?>" <?= ($conta['fornecedor_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>>
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
            <?php if (!$conta): ?><small class="muted">1 = à vista</small><?php endif; ?>
        </div>
    </div>

    <div class="form-group col-6">
        <label>Forma de Pagamento</label>
        <select name="forma_pagamento">
            <?php
            $fp = $conta['forma_pagamento'] ?? 'boleto';
            $formas = ['boleto','pix','transferencia','dinheiro','cartao','cheque','outros'];
            foreach ($formas as $f): ?>
                <option value="<?= $f ?>" <?= $fp === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($conta['observacoes'] ?? '') ?></textarea>
    </div>

    <?php if ($conta && in_array($status, ['pendente', 'aprovada'], true) && Permissao::tem('pagar')): ?>
    <fieldset id="pagar" class="payment-section">
        <legend>💰 Registrar Pagamento</legend>
        <div class="row">
            <div class="form-group col-4">
                <label>Conta Bancária *</label>
                <select name="conta_bancaria_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($contasBanco as $cb): ?>
                        <option value="<?= (int)$cb['id'] ?>">
                            <?= htmlspecialchars($cb['descricao']) ?>
                            (R$ <?= number_format(ContasBancariasController::calcularSaldo((int)$cb['id']), 2, ',', '.') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-4">
                <label>Data Pagamento *</label>
                <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group col-4">
                <label>Valor Pago *</label>
                <input type="number" step="0.01" name="valor_pago" value="<?= htmlspecialchars($conta['valor']) ?>" required>
            </div>
        </div>
    </fieldset>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <?php if ($conta && Permissao::tem('excluir') && $status !== 'paga'): ?>
            <button type="submit" formaction="conta_acao.php" formmethod="post"
                    onclick="return confirm('Excluir esta conta?')"
                    class="btn btn-danger">Excluir</button>
            <input type="hidden" name="_method" value="DELETE">
        <?php endif; ?>
        <a href="contas_pagar.php" class="btn">Cancelar</a>
    </div>
</form>

<?php if ($ehPago): ?>
<div class="alert alert-info">
    <strong>Conta paga em <?= dataIsoParaBr($conta['data_pagamento']) ?>.</strong>
    Valor pago: R$ <?= number_format((float)$conta['valor_pago'], 2, ',', '.') ?>.
    <?php if (!empty($conta['conta_bancaria_descricao'])): ?>
        Lançada em: <?= htmlspecialchars($conta['conta_bancaria_descricao']) ?>.
    <?php endif; ?>
</div>
<?php endif; ?>