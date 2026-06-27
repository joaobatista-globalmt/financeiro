<?php /** @var array $categorias */ ?>
<div class="page-header">
    <h1>🏷️ Categorias</h1>
    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
        <a href="categoria_form.php" class="btn btn-primary">+ Nova Categoria</a>
    <?php endif; ?>
</div>

<table class="table">
    <thead>
        <tr>
            <th></th>
            <th>Nome</th>
            <th>Tipo</th>
            <th>Descrição</th>
            <th class="text-right">Em Contas a Pagar</th>
            <th class="text-right">Em Contas a Receber</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($categorias)): ?>
            <tr><td colspan="8" class="muted center">Nenhuma categoria cadastrada.</td></tr>
        <?php else: foreach ($categorias as $cat): ?>
            <tr>
                <td><span class="color-dot" style="background: <?= htmlspecialchars($cat['cor']) ?>"></span></td>
                <td><strong><?= htmlspecialchars($cat['nome']) ?></strong></td>
                <td>
                    <span class="badge badge-<?= $cat['tipo'] === 'receita' ? 'success' : ($cat['tipo'] === 'despesa' ? 'danger' : 'secondary') ?>">
                        <?= htmlspecialchars($cat['tipo']) ?>
                    </span>
                </td>
                <td><small><?= htmlspecialchars($cat['descricao'] ?? '-') ?></small></td>
                <td class="text-right"><?= (int)$cat['qtd_pagar'] ?></td>
                <td class="text-right"><?= (int)$cat['qtd_receber'] ?></td>
                <td>
                    <span class="badge badge-<?= $cat['ativo'] ? 'success' : 'secondary' ?>">
                        <?= $cat['ativo'] ? 'Ativa' : 'Inativa' ?>
                    </span>
                </td>
                <td class="actions">
                    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
                        <a href="categoria_form.php?id=<?= (int)$cat['id'] ?>" class="btn btn-sm">Editar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>