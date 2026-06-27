<?php /** @var array|null $conta */ ?>
<div class="page-header">
    <h1><?= $conta ? 'Editar' : 'Nova' ?> Conta Bancária</h1>
    <a href="contas_bancarias.php" class="btn">Voltar</a>
</div>

<form method="post" action="conta_bancaria_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($conta['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-8">
            <label>Descrição *</label>
            <input type="text" name="descricao" required maxlength="100"
                   value="<?= htmlspecialchars($conta['descricao'] ?? '') ?>"
                   placeholder="Ex: Banco do Brasil - CC 12345-6">
        </div>

        <div class="form-group col-4">
            <label>Tipo *</label>
            <select name="tipo" required>
                <?php
                $tipos = [
                    'conta_corrente' => 'Conta Corrente',
                    'poupanca'       => 'Poupança',
                    'caixa_fisico'   => 'Caixa Físico',
                    'cartao'         => 'Cartão',
                    'investimento'   => 'Investimento',
                ];
                $tipoAtual = $conta['tipo'] ?? 'conta_corrente';
                foreach ($tipos as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $tipoAtual === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>Banco</label>
            <input type="text" name="banco" maxlength="100"
                   value="<?= htmlspecialchars($conta['banco'] ?? '') ?>"
                   placeholder="Ex: Banco do Brasil">
        </div>
        <div class="form-group col-3">
            <label>Agência</label>
            <input type="text" name="agencia" maxlength="20"
                   value="<?= htmlspecialchars($conta['agencia'] ?? '') ?>">
        </div>
        <div class="form-group col-3">
            <label>Número da Conta</label>
            <div class="inline-inputs">
                <input type="text" name="numero_conta" maxlength="30"
                       value="<?= htmlspecialchars($conta['numero_conta'] ?? '') ?>">
                <input type="text" name="digito" maxlength="5" placeholder="Dígito"
                       value="<?= htmlspecialchars($conta['digito'] ?? '') ?>" style="width: 80px">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-8">
            <label>Titular</label>
            <input type="text" name="titular" maxlength="200"
                   value="<?= htmlspecialchars($conta['titular'] ?? '') ?>">
        </div>
        <div class="form-group col-4">
            <label>CPF/CNPJ do Titular</label>
            <input type="text" name="cpf_cnpj_titular" maxlength="20"
                   value="<?= htmlspecialchars($conta['cpf_cnpj_titular'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>Saldo Inicial *</label>
            <input type="number" step="0.01" name="saldo_inicial" required
                   value="<?= htmlspecialchars($conta['saldo_inicial'] ?? '0.00') ?>">
            <small class="muted">Pode ser negativo (ex: cheque especial).</small>
        </div>
        <div class="form-group col-6">
            <label>Data do Saldo Inicial *</label>
            <input type="date" name="data_saldo_inicial" required
                   value="<?= htmlspecialchars($conta['data_saldo_inicial'] ?? date('Y-m-d')) ?>">
            <small class="muted">A partir desta data as movimentações são consideradas.</small>
        </div>
    </div>

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($conta['observacoes'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativo" value="1" <?= (!$conta || $conta['ativo']) ? 'checked' : '' ?>>
            Ativa
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="contas_bancarias.php" class="btn">Cancelar</a>
    </div>
</form>