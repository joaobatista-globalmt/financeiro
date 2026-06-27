<?php
/**
 * Layout: navbar superior
 *
 * Mostra menu principal + dropdown de empresa.
 */
$usuario = $usuario ?? Auth::user();
$perfil  = $perfil  ?? Auth::perfilAtual();
$empresaId = (int)($usuario['empresa_id'] ?? 0);
?>
<nav class="navbar">
    <div class="navbar-brand">
        <a href="index.php">💰 Financeiro</a>
    </div>
    <ul class="navbar-menu">
        <li><a href="index.php">Dashboard</a></li>
        <li class="dropdown">
            <a href="#">Contas ▾</a>
            <ul class="dropdown-menu">
                <li><a href="contas_pagar.php">Contas a Pagar</a></li>
                <li><a href="contas_receber.php">Contas a Receber</a></li>
                <li><a href="recorrencia_pagar.php">Recorrências (Pagar)</a></li>
                <li><a href="recorrencia_receber.php">Recorrências (Receber)</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a href="#">Bancos ▾</a>
            <ul class="dropdown-menu">
                <li><a href="contas_bancarias.php">Contas Bancárias</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a href="#">Cadastros ▾</a>
            <ul class="dropdown-menu">
                <li><a href="fornecedores.php">Fornecedores</a></li>
                <li><a href="clientes.php">Clientes</a></li>
                <li><a href="categorias.php">Categorias</a></li>
            </ul>
        </li>
        <li><a href="relatorios.php">Relatórios</a></li>
        <?php if (Permissao::tem('gerenciar_empresas') || Permissao::tem('gerenciar_usuarios')): ?>
        <li class="dropdown">
            <a href="#">Admin ▾</a>
            <ul class="dropdown-menu">
                <?php if (Permissao::tem('gerenciar_empresas')): ?>
                    <li><a href="empresas.php">Empresas</a></li>
                <?php endif; ?>
                <?php if (Permissao::tem('gerenciar_usuarios')): ?>
                    <li><a href="usuarios.php">Usuários</a></li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
    </ul>
    <div class="navbar-right">
        <?php if (!empty($_SESSION['empresas']) && count($_SESSION['empresas']) > 1): ?>
        <form method="post" action="trocar-empresa.php" class="empresa-dropdown">
            <select name="empresa_id" onchange="this.form.submit()">
                <?php foreach ($_SESSION['empresas'] as $emp): ?>
                    <option value="<?= (int)$emp['empresa_id'] ?>"
                        <?= (int)$emp['empresa_id'] === $empresaId ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['nome_fantasia'] ?: $emp['razao_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
        <span class="user-info">
            <?= htmlspecialchars($usuario['nome'] ?? '') ?>
            <small>(<?= htmlspecialchars($perfil) ?>)</small>
        </span>
        <a href="logout.php" class="btn btn-sm">Sair</a>
    </div>
</nav>