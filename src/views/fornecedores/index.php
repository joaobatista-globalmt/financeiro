<?php /** @var array $fornecedores */ ?>
<div class="page-header">
    <h1>🏭 Fornecedores</h1>
    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
        <a href="fornecedor_form.php" class="btn btn-primary">+ Novo Fornecedor</a>
    <?php endif; ?>
</div>

<?php
/** @var array $filtros */
/** @var bool $filtrosAplicados */
/** @var int $totalGeral */
$filtros = $filtros ?? [];
$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
?>

<!-- Form de filtro -->
<form method="get" class="form-filtros" style="margin: 16px 0; padding: 12px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label>Razão Social</label>
            <input type="text" name="razao_social" value="<?= htmlspecialchars($filtros['razao_social'] ?? '') ?>" placeholder="Buscar por razão social...">
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 180px;">
            <label>Nome Fantasia</label>
            <input type="text" name="nome_fantasia" value="<?= htmlspecialchars($filtros['nome_fantasia'] ?? '') ?>" placeholder="Buscar por nome fantasia...">
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 150px;">
            <label>CNPJ</label>
            <input type="text" name="cnpj" value="<?= htmlspecialchars($filtros['cnpj'] ?? '') ?>" placeholder="00.000.000/0000-00">
        </div>
        <div class="form-group" style="margin: 0; flex: 1; min-width: 130px;">
            <label>Cidade</label>
            <input type="text" name="cidade" value="<?= htmlspecialchars($filtros['cidade'] ?? '') ?>" placeholder="Cidade...">
        </div>
        <div class="form-group" style="margin: 0; min-width: 80px;">
            <label>UF</label>
            <select name="uf">
                <option value="">Todas</option>
                <?php foreach ($ufs as $uf): ?>
                    <option value="<?= $uf ?>" <?= ($filtros['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin: 0; min-width: 100px;">
            <label>Status</label>
            <select name="ativo">
                <option value="">Todos</option>
                <option value="1" <?= ($filtros['ativo'] ?? '') === '1' ? 'selected' : '' ?>>Ativo</option>
                <option value="0" <?= ($filtros['ativo'] ?? '') === '0' ? 'selected' : '' ?>>Inativo</option>
            </select>
        </div>
        <div style="display: flex; gap: 6px;">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <?php if ($filtrosAplicados): ?>
                <a href="fornecedores.php" class="btn">✕ Limpar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Contador -->
<div style="margin: 8px 0; color: #6b7280; font-size: 13px;">
    <?php
    $qtd = count($fornecedores);
    if ($filtrosAplicados) {
        echo "Exibindo <strong>$qtd</strong> de <strong>$totalGeral</strong> fornecedor(es) (filtro aplicado)";
    } else {
        echo "Exibindo <strong>$qtd</strong> fornecedor(es)";
    }
    ?>
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