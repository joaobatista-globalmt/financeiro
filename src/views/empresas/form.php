<?php /** @var array|null $empresa */ ?>
<div class="page-header">
    <h1><?= $empresa ? 'Editar' : 'Nova' ?> Empresa</h1>
    <a href="empresas.php" class="btn">Voltar</a>
</div>

<form method="post" action="empresa_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($empresa['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-8">
            <label>Razão Social *</label>
            <input type="text" name="razao_social" required maxlength="200"
                   value="<?= htmlspecialchars($empresa['razao_social'] ?? '') ?>">
        </div>
        <div class="form-group col-4">
            <label>Nome Fantasia</label>
            <input type="text" name="nome_fantasia" maxlength="100"
                   value="<?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>CNPJ</label>
            <input type="text" name="cnpj" maxlength="20"
                   value="<?= htmlspecialchars($empresa['cnpj'] ?? '') ?>">
        </div>
        <div class="form-group col-6">
            <label>Inscrição Estadual</label>
            <input type="text" name="inscricao_estadual" maxlength="20"
                   value="<?= htmlspecialchars($empresa['inscricao_estadual'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-3">
            <label>CEP</label>
            <input type="text" name="cep" maxlength="10"
                   value="<?= htmlspecialchars($empresa['cep'] ?? '') ?>">
        </div>
        <div class="form-group col-7">
            <label>Endereço</label>
            <input type="text" name="endereco" maxlength="255"
                   value="<?= htmlspecialchars($empresa['endereco'] ?? '') ?>">
        </div>
        <div class="form-group col-2">
            <label>UF</label>
            <input type="text" name="uf" maxlength="2"
                   value="<?= htmlspecialchars($empresa['uf'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-8">
            <label>Cidade</label>
            <input type="text" name="cidade" maxlength="100"
                   value="<?= htmlspecialchars($empresa['cidade'] ?? '') ?>">
        </div>
        <div class="form-group col-4">
            <label>Telefone</label>
            <input type="text" name="telefone" maxlength="20"
                   value="<?= htmlspecialchars($empresa['telefone'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label>E-mail</label>
        <input type="email" name="email" maxlength="150"
               value="<?= htmlspecialchars($empresa['email'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativo" value="1" <?= (!$empresa || $empresa['ativo']) ? 'checked' : '' ?>>
            Ativa
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="empresas.php" class="btn">Cancelar</a>
    </div>
</form>