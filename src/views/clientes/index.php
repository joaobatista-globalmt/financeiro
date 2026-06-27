<?php /** @var array $clientes */ ?>
<div class="page-header">
    <h1>👥 Clientes</h1>
    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
        <a href="cliente_form.php" class="btn btn-primary">+ Novo Cliente</a>
    <?php endif; ?>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Razão Social</th>
            <th>Nome Fantasia</th>
            <th>CPF/CNPJ</th>
            <th>Tipo</th>
            <th>Contato</th>
            <th>Cidade/UF</th>
            <th class="text-right">Contas</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($clientes)): ?>
            <tr><td colspan="9" class="muted center">Nenhum cliente cadastrado.</td></tr>
        <?php else: foreach ($clientes as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['razao_social']) ?></strong></td>
                <td><?= htmlspecialchars($c['nome_fantasia'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['cpf_cnpj'] ?? '-') ?></td>
                <td><?= $c['tipo_pessoa'] === 'F' ? 'Física' : 'Jurídica' ?></td>
                <td><?= htmlspecialchars($c['telefone'] ?? '-') ?></td>
                <td><?= htmlspecialchars(($c['cidade'] ?? '-') . '/' . ($c['uf'] ?? '-')) ?></td>
                <td class="text-right"><?= (int)$c['total_contas'] ?></td>
                <td>
                    <span class="badge badge-<?= $c['ativo'] ? 'success' : 'secondary' ?>">
                        <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>
                <td class="actions">
                    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
                        <a href="cliente_form.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm">Editar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>