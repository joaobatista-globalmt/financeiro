<?php
/** @var array|null $fornecedor */

// Suporte a retorno para tela de origem (ex: conta_form.php) com seleção automática
// Aceita nomes como "conta_form", "conta_receber_form", "fornecedores" (com ou sem .php)
$returnTo      = preg_match('/^[a-z0-9_]+$/', (string)($_GET['return'] ?? '')) ? $_GET['return'] : '';
$returnSelect  = preg_match('/^[a-z0-9_]+$/', (string)($_GET['select'] ?? '')) ? $_GET['select'] : '';
$tipoCategoria = preg_match('/^[a-z]+$/', (string)($_GET['tipo'] ?? '')) ? $_GET['tipo'] : '';

// Propaga ?return=...&select=... no action do form
$actionForm = 'fornecedor_salvar.php' . ($returnTo ? '?return=' . rawurlencode($returnTo) . '&select=' . rawurlencode($returnSelect) : '');
?>
<div class="page-header">
    <h1><?= $fornecedor ? 'Editar' : 'Novo' ?> Fornecedor</h1>
    <a href="<?= $returnTo ?: 'fornecedores.php' ?>" class="btn">Voltar</a>
</div>

<?php if ($returnTo): ?>
    <div class="alert alert-info" style="margin-bottom:12px;">
        <strong>Modo criação rápida.</strong> Ao salvar, você voltará automaticamente para a tela de origem com este fornecedor selecionado.
    </div>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($actionForm) ?>" class="form">
    <input type="hidden" name="id" value="<?= (int)($fornecedor['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-8">
            <label>Razão Social *</label>
            <input type="text" name="razao_social" required maxlength="200"
                   value="<?= htmlspecialchars($fornecedor['razao_social'] ?? '') ?>">
        </div>
        <div class="form-group col-4">
            <label>Nome Fantasia</label>
            <input type="text" name="nome_fantasia" maxlength="100"
                   value="<?= htmlspecialchars($fornecedor['nome_fantasia'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>CNPJ</label>
            <input type="text" name="cnpj" maxlength="20"
                   value="<?= htmlspecialchars($fornecedor['cnpj'] ?? '') ?>">
        </div>
        <div class="form-group col-6">
            <label>Inscrição Estadual</label>
            <input type="text" name="inscricao_estadual" maxlength="20"
                   value="<?= htmlspecialchars($fornecedor['inscricao_estadual'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-3">
            <label>CEP</label>
            <input type="text" name="cep" maxlength="10"
                   value="<?= htmlspecialchars($fornecedor['cep'] ?? '') ?>">
        </div>
        <div class="form-group col-7">
            <label>Endereço</label>
            <input type="text" name="endereco" maxlength="255"
                   value="<?= htmlspecialchars($fornecedor['endereco'] ?? '') ?>">
        </div>
        <div class="form-group col-2">
            <label>UF</label>
            <input type="text" name="uf" maxlength="2"
                   value="<?= htmlspecialchars($fornecedor['uf'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-8">
            <label>Cidade</label>
            <input type="text" name="cidade" maxlength="100"
                   value="<?= htmlspecialchars($fornecedor['cidade'] ?? '') ?>">
        </div>
        <div class="form-group col-4">
            <label>Telefone</label>
            <input type="text" name="telefone" maxlength="20"
                   value="<?= htmlspecialchars($fornecedor['telefone'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-6">
            <label>E-mail</label>
            <input type="email" name="email" maxlength="150"
                   value="<?= htmlspecialchars($fornecedor['email'] ?? '') ?>">
        </div>
        <div class="form-group col-6">
            <label>Contato</label>
            <input type="text" name="contato" maxlength="100"
                   value="<?= htmlspecialchars($fornecedor['contato'] ?? '') ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($fornecedor['observacoes'] ?? '') ?></textarea>
    </div>

    <fieldset class="form-section">
        <legend>💠 Chave PIX</legend>
        <div class="row">
            <div class="form-group col-3">
                <label>Tipo da Chave</label>
                <select name="pix_tipo">
                    <?php $pixTipoAtual = $fornecedor['pix_tipo'] ?? ''; ?>
                    <option value="">— Nenhuma —</option>
                    <option value="cpf"       <?= $pixTipoAtual === 'cpf'       ? 'selected' : '' ?>>CPF</option>
                    <option value="cnpj"      <?= $pixTipoAtual === 'cnpj'      ? 'selected' : '' ?>>CNPJ</option>
                    <option value="email"     <?= $pixTipoAtual === 'email'     ? 'selected' : '' ?>>E-mail</option>
                    <option value="telefone"  <?= $pixTipoAtual === 'telefone'  ? 'selected' : '' ?>>Telefone</option>
                    <option value="aleatoria" <?= $pixTipoAtual === 'aleatoria' ? 'selected' : '' ?>>Chave aleatória</option>
                </select>
            </div>
            <div class="form-group col-9">
                <label>Chave</label>
                <input type="text" name="pix_chave" maxlength="255"
                       value="<?= htmlspecialchars($fornecedor['pix_chave'] ?? '') ?>"
                       placeholder="Ex: 123.456.789-00, email@exemplo.com, (65) 99999-9999, ou UUID">
            </div>
        </div>
        <small class="muted">Informe o tipo e a chave PIX para receber pagamentos por transferência instantânea.</small>
    </fieldset>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativo" value="1" <?= (!$fornecedor || $fornecedor['ativo']) ? 'checked' : '' ?>>
            Ativo
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="fornecedores.php" class="btn">Cancelar</a>
    </div>
</form>