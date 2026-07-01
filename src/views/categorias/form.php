<?php
/** @var array|null $categoria */

// Suporte a retorno para tela de origem (ex: conta_form.php) com seleção automática
// Aceita nomes como "conta_form", "conta_receber_form", "categorias" (com ou sem .php)
$returnTo     = preg_match('/^[a-z0-9_]+$/', (string)($_GET['return'] ?? '')) ? $_GET['return'] : '';
$returnSelect = preg_match('/^[a-z0-9_]+$/', (string)($_GET['select'] ?? '')) ? $_GET['select'] : '';
$tipoInicial  = preg_match('/^[a-z]+$/', (string)($_GET['tipo'] ?? '')) ? $_GET['tipo'] : '';

// Propaga ?return=...&select=... no action do form
$actionForm = 'categoria_salvar.php' . ($returnTo ? '?return=' . rawurlencode($returnTo) . '&select=' . rawurlencode($returnSelect) : '');
?>
<div class="page-header">
    <h1><?= $categoria ? 'Editar' : 'Nova' ?> Categoria</h1>
    <a href="<?= $returnTo ?: 'categorias.php' ?>" class="btn">Voltar</a>
</div>

<?php if ($returnTo): ?>
    <div class="alert alert-info" style="margin-bottom:12px;">
        <strong>Modo criação rápida.</strong> Ao salvar, você voltará automaticamente para a tela de origem com esta categoria selecionada.
    </div>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($actionForm) ?>" class="form">
    <input type="hidden" name="id" value="<?= (int)($categoria['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-6">
            <label>Nome *</label>
            <input type="text" name="nome" required maxlength="100"
                   value="<?= htmlspecialchars($categoria['nome'] ?? '') ?>">
        </div>
        <div class="form-group col-3">
            <label>Tipo *</label>
            <select name="tipo" required>
                <?php $tipo = $categoria['tipo'] ?? ($tipoInicial ?: 'despesa'); ?>
                <option value="despesa" <?= $tipo === 'despesa' ? 'selected' : '' ?>>Despesa (Pagar)</option>
                <option value="receita" <?= $tipo === 'receita' ? 'selected' : '' ?>>Receita (Receber)</option>
                <option value="ambos"   <?= $tipo === 'ambos'   ? 'selected' : '' ?>>Ambos</option>
            </select>
        </div>
        <div class="form-group col-3">
            <label>Cor</label>
            <input type="color" name="cor" value="<?= htmlspecialchars($categoria['cor'] ?? '#6c757d') ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Descrição</label>
        <input type="text" name="descricao" maxlength="255"
               value="<?= htmlspecialchars($categoria['descricao'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativo" value="1" <?= (!$categoria || $categoria['ativo']) ? 'checked' : '' ?>>
            Ativa
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="categorias.php" class="btn">Cancelar</a>
    </div>
</form>