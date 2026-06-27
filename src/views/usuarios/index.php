<?php /** @var array $usuarios */ ?>
<div class="page-header">
    <h1>👤 Usuários</h1>
    <?php if (Permissao::tem('gerenciar_usuarios')): ?>
        <a href="usuario_form.php" class="btn btn-primary">+ Novo Usuário</a>
    <?php endif; ?>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Perfil Padrão</th>
            <th>Empresas Vinculadas</th>
            <th>Último Acesso</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($usuarios)): ?>
            <tr><td colspan="7" class="muted center">Nenhum usuário.</td></tr>
        <?php else: foreach ($usuarios as $u): ?>
            <tr>
                <td><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="badge"><?= htmlspecialchars($u['perfil_padrao']) ?></span></td>
                <td><small><?= htmlspecialchars($u['empresas_vinculadas'] ?? '-') ?></small></td>
                <td><small><?= $u['ultimo_acesso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acesso'])) : 'Nunca' ?></small></td>
                <td>
                    <span class="badge badge-<?= $u['ativo'] ? 'success' : 'secondary' ?>">
                        <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>
                <td class="actions">
                    <?php if (Permissao::tem('gerenciar_usuarios')): ?>
                        <a href="usuario_form.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm">Editar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>