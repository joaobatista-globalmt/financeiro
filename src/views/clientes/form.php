<?php /** @var array|null $cliente */ ?>
<div class="page-header">
    <h1><?= $cliente ? 'Editar' : 'Novo' ?> Cliente</h1>
    <a href="clientes.php" class="btn">Voltar</a>
</div>

<form method="post" action="cliente_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($cliente['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-8">
            <label>Razão Social / Nome *</label>
            <input type="text" name="razao_social" required maxlength="200"
                   value="<?= htmlspecialchars($cliente['razao_social'] ?? '') ?>">
        </div>
        <div class="form-group col-4">
            <label>Tipo de Pessoa *</label>
            <select name="tipo_pessoa" required>
                <?php $tp = $cliente['tipo_pessoa'] ?? 'J'; ?>
                <option value="J" <?= $tp === 'J' ? 'selected' : '' ?>>Jurídica (CNPJ)</option>
                <option value="F" <?= $tp === 'F' ? 'selected' : '' ?>>Física (CPF)</option>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-8">
            <label>Nome Fantasia / Apelido</label>
            <input type="text" name="nome_fantasia" maxlength="100"
                   value="<?= htmlspecialchars($cliente['nome_fantasia'] ?? '') ?>">
        </div>
        <div class="form-group col-4">
            <label>CPF/CNPJ</label>
            <input type="text" name="cpf_cnpj" maxlength="20"
                   value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-3">
            <label>CEP</label>
            <input type="text" name="cep" maxlength="10"
                   value="<?= htmlspecialchars($cliente['cep'] ?? '') ?>">
        </div>
        <div class="form-group col-7">
            <label>Endereço</label>
            <input type="text" name="endereco" maxlength="255"
                   value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
        </div>
        <div class="form-group col-2">
            <label>UF</label>
            <input type="text" name="uf" maxlength="2"
                   value="<?= htmlspecialchars($cliente['uf'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-8">
            <label>Cidade</label>
            <input type="text" name="cidade" maxlength="100"
                   value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>">
        </div>
        <div class="form-group col-4">
            <label>Telefone</label>
            <input type="text" name="telefone" maxlength="20"
                   value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>E-mail</label>
            <input type="email" name="email" maxlength="150"
                   value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
        </div>
        <div class="form-group col-6">
            <label>Contato</label>
            <input type="text" name="contato" maxlength="100"
                   value="<?= htmlspecialchars($cliente['contato'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($cliente['observacoes'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativo" value="1" <?= (!$cliente || $cliente['ativo']) ? 'checked' : '' ?>>
            Ativo
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="clientes.php" class="btn">Cancelar</a>
    </div>
</form>