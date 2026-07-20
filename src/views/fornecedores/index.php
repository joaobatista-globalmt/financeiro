<?php /** @var array $fornecedores */ ?>
<div class="page-header">
    <h1>🏭 Fornecedores</h1>
    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
        <a href="fornecedor_form.php" class="btn btn-primary">+ Novo Fornecedor</a>
    <?php endif; ?>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Razão Social</th>
            <th>Nome Fantasia</th>
            <th>CNPJ</th>
            <th>Contato</th>
            <th>Cidade/UF</th>
            <th class="text-right">Contas</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($fornecedores)): ?>
            <tr><td colspan="8" class="muted center">Nenhum fornecedor cadastrado.</td></tr>
        <?php else: foreach ($fornecedores as $f): ?>
            <tr>
                <td><strong><?= htmlspecialchars($f['razao_social']) ?></strong></td>
                <td><?= htmlspecialchars($f['nome_fantasia'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['cnpj'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['telefone'] ?? '-') ?></td>
                <td><?= htmlspecialchars(($f['cidade'] ?? '-') . '/' . ($f['uf'] ?? '-')) ?></td>
                <td class="text-right"><?= (int)$f['total_contas'] ?></td>
                <td>
                    <span class="badge badge-<?= $f['ativo'] ? 'success' : 'secondary' ?>">
                        <?= $f['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>
                <td class="actions">
                    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
                        <a href="fornecedor_form.php?id=<?= (int)$f['id'] ?>" class="btn btn-sm">Editar</a>
                        <?php if ($f['ativo']): ?>
                            <form method="post" action="fornecedor_acao.php" style="display:inline" onsubmit="return confirm('Desativar o fornecedor &quot;<?= htmlspecialchars(addslashes($f['razao_social']), ENT_QUOTES) ?>&quot;?')">
                                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                <input type="hidden" name="acao" value="desativar">
                                <button type="submit" class="btn btn-sm">Desativar</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="fornecedor_acao.php" style="display:inline" onsubmit="return confirm('Ativar o fornecedor &quot;<?= htmlspecialchars(addslashes($f['razao_social']), ENT_QUOTES) ?>&quot;?')">
                                <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                <input type="hidden" name="acao" value="ativar">
                                <button type="submit" class="btn btn-sm">Ativar</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (Permissao::tem('excluir')): ?>
                        <form method="post" action="fornecedor_acao.php" style="display:inline" onsubmit="return confirm('ATENÇÃO: Excluir PERMANENTEMENTE o fornecedor &quot;<?= htmlspecialchars(addslashes($f['razao_social']), ENT_QUOTES) ?>&quot;?\n\nEsta ação NÃO pode ser desfeita. Se houver contas a pagar vinculadas, a exclusão será bloqueada.')">
                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>