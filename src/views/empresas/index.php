<?php /** @var array $empresas */ ?>
<div class="page-header">
    <h1>🏢 Empresas</h1>
    <?php if (Permissao::tem('gerenciar_empresas')): ?>
        <a href="empresa_form.php" class="btn btn-primary">+ Nova Empresa</a>
    <?php endif; ?>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Razão Social</th>
            <th>Nome Fantasia</th>
            <th>CNPJ</th>
            <th>Cidade/UF</th>
            <th class="text-right">Usuários</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($empresas)): ?>
            <tr><td colspan="7" class="muted center">Nenhuma empresa cadastrada.</td></tr>
        <?php else: foreach ($empresas as $e): ?>
            <tr>
                <td><strong><?= htmlspecialchars($e['razao_social']) ?></strong></td>
                <td><?= htmlspecialchars($e['nome_fantasia'] ?? '-') ?></td>
                <td><?= htmlspecialchars($e['cnpj'] ?? '-') ?></td>
                <td><?= htmlspecialchars(($e['cidade'] ?? '-') . '/' . ($e['uf'] ?? '-')) ?></td>
                <td class="text-right"><?= (int)$e['total_usuarios'] ?></td>
                <td>
                    <span class="badge badge-<?= $e['ativo'] ? 'success' : 'secondary' ?>">
                        <?= $e['ativo'] ? 'Ativa' : 'Inativa' ?>
                    </span>
                </td>
                <td class="actions">
                    <?php if (Permissao::tem('gerenciar_empresas')): ?>
                        <a href="empresa_form.php?id=<?= (int)$e['id'] ?>" class="btn btn-sm">Editar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>