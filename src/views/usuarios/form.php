<?php
/** @var array|null $usuario */
/** @var array $vinculos */
/** @var array $empresas */
?>
<div class="page-header">
    <h1><?= $usuario ? 'Editar' : 'Novo' ?> Usuário</h1>
    <a href="usuarios.php" class="btn">Voltar</a>
</div>

<form method="post" action="usuario_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($usuario['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-6">
            <label>Nome *</label>
            <input type="text" name="nome" required maxlength="150"
                   value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
        </div>
        <div class="form-group col-6">
            <label>E-mail *</label>
            <input type="email" name="email" required maxlength="150"
                   value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-4">
            <label>Perfil Padrão *</label>
            <select name="perfil_padrao" required>
                <?php $pp = $usuario['perfil_padrao'] ?? 'operador'; ?>
                <?php foreach (Permissao::PERFIS as $perfil): ?>
                    <option value="<?= $perfil ?>" <?= $pp === $perfil ? 'selected' : '' ?>>
                        <?= ucfirst($perfil) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="muted">Perfil quando não há vínculo específico com a empresa.</small>
        </div>
        <div class="form-group col-4">
            <label>Senha <?= $usuario ? '(deixe em branco para manter)' : '*' ?></label>
            <input type="password" name="senha" minlength="6" <?= $usuario ? '' : 'required' ?>>
        </div>
        <div class="form-group col-4">
            <label>Confirmar Senha</label>
            <input type="password" name="confirma_senha" minlength="6">
        </div>
    </div>

    <h3>Empresas e Perfis por Empresa</h3>
    <p class="muted">Defina em quais empresas o usuário tem acesso e qual perfil assume em cada uma.</p>

    <table class="table">
        <thead><tr><th>Empresa</th><th>Perfil na Empresa</th><th>Ativo</th></tr></thead>
        <tbody>
            <?php foreach ($empresas as $emp):
                $vinculoAtual = null;
                foreach ($vinculos as $v) {
                    if ((int)$v['empresa_id'] === (int)$emp['id']) {
                        $vinculoAtual = $v;
                        break;
                    }
                }
            ?>
                <tr>
                    <td><?= htmlspecialchars($emp['razao_social']) ?></td>
                    <td>
                        <select name="vinculos[<?= (int)$emp['id'] ?>]">
                            <option value="">— Sem acesso —</option>
                            <?php $perfilVinc = $vinculoAtual['perfil_na_empresa'] ?? ''; ?>
                            <?php foreach (Permissao::PERFIS as $perfil): ?>
                                <option value="<?= $perfil ?>" <?= $perfilVinc === $perfil ? 'selected' : '' ?>>
                                    <?= ucfirst($perfil) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <?php if ($vinculoAtual): ?>
                            <span class="badge badge-<?= $vinculoAtual['ativo'] ? 'success' : 'secondary' ?>">
                                <?= $vinculoAtual['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="usuarios.php" class="btn">Cancelar</a>
    </div>
</form>