<?php /** @var array $clientes */ ?>
<div class="page-header">
    <h1>👥 Clientes</h1>
    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
        <a href="cliente_form.php" class="btn btn-primary">+ Novo Cliente</a>
    <?php endif; ?>
</div>

<?php
/** @var array $filtros */
/** @var bool $filtrosAplicados */
/** @var int $totalGeral */
$filtros = $filtros ?? [];
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
            <label>CPF/CNPJ</label>
            <input type="text" name="cpf_cnpj" value="<?= htmlspecialchars($filtros['cpf_cnpj'] ?? '') ?>" placeholder="000.000.000-00 ou 00.000.000/0000-00">
        </div>
        <div class="form-group" style="margin: 0; min-width: 100px;">
            <label>Tipo</label>
            <select name="tipo">
                <option value="">Todos</option>
                <option value="F" <?= ($filtros['tipo'] ?? '') === 'F' ? 'selected' : '' ?>>Física</option>
                <option value="J" <?= ($filtros['tipo'] ?? '') === 'J' ? 'selected' : '' ?>>Jurídica</option>
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
        <div class="form-group" style="margin: 0; min-width: 90px;">
            <label>Dia Venc.</label>
            <input type="number" name="dia_vencimento" min="1" max="31" value="<?= htmlspecialchars($filtros['dia_vencimento'] ?? '') ?>" placeholder="1-31">
        </div>
        <div style="display: flex; gap: 6px;">
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <?php if ($filtrosAplicados): ?>
                <a href="clientes.php" class="btn">✕ Limpar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Contador -->
<div style="margin: 8px 0; color: #6b7280; font-size: 13px;">
    <?php
    $qtd = count($clientes);
    if ($filtrosAplicados) {
        echo "Exibindo <strong>$qtd</strong> de <strong>$totalGeral</strong> cliente(s) (filtro aplicado)";
    } else {
        echo "Exibindo <strong>$qtd</strong> cliente(s)";
    }
    ?>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Razão Social</th>
            <th>Nome Fantasia</th>
            <th>CPF/CNPJ</th>
            <th>Tipo</th>
            <th>Contato</th>
            <th>Vencimento</th>
            <th>Documentos</th>
            <th class="text-right">Contas</th>
            <th>Status</th>
            <th>Maps</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($clientes)): ?>
            <tr><td colspan="11" class="muted center">Nenhum cliente cadastrado.</td></tr>
        <?php else: foreach ($clientes as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['razao_social']) ?></strong></td>
                <td><?= htmlspecialchars($c['nome_fantasia'] ?? '-') ?></td>
                <td><?= htmlspecialchars($c['cpf_cnpj'] ?? '-') ?></td>
                <td><?= $c['tipo_pessoa'] === 'F' ? 'Física' : 'Jurídica' ?></td>
                <td><?= htmlspecialchars($c['telefone'] ?? '-') ?></td>
                <td>
                    <?php if ($c['dia_vencimento']): ?>
                        <span class="badge badge-info" title="<?= htmlspecialchars($c['tipo_vencimento'] ?? '') ?>">
                            Dia <?= (int)$c['dia_vencimento'] ?>
                            <?php if ($c['tipo_vencimento'] === 'mes_corrente'): ?>
                                (mês corrente)
                            <?php elseif ($c['tipo_vencimento'] === 'mes_seguinte'): ?>
                                (mês seguinte)
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span style="color: var(--color-text-muted);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int)$c['emite_nfse'] === 1): ?>
                        <span class="badge badge-success" title="<?= (int)$c['qtd_emails_nfse'] ?> e-mail(s)">📄 NFSe</span>
                    <?php else: ?>
                        <span class="badge badge-default">Sem NFSe</span>
                    <?php endif; ?>
                    <?php if ((int)$c['emite_boleto'] === 1): ?>
                        <span class="badge badge-info" title="<?= (int)$c['qtd_emails_boleto'] ?> e-mail(s)">💰 Boleto</span>
                    <?php else: ?>
                        <span class="badge badge-default">Sem Boleto</span>
                    <?php endif; ?>
                </td>
                <td class="text-right"><?= (int)$c['total_contas'] ?></td>
                <td>
                    <span class="badge badge-<?= $c['ativo'] ? 'success' : 'secondary' ?>">
                        <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
                    </span>
                </td>
                <td>
                    <?php if (!empty($c['endereco_maps'])): ?>
                        <a href="<?= htmlspecialchars($c['endereco_maps']) ?>" target="_blank" rel="noopener noreferrer" title="<?= htmlspecialchars($c['endereco_maps']) ?>" style="text-decoration: none; color: #1e40af; font-weight: 500;">Maps</a>
                    <?php else: ?>
                        <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <?php if (Permissao::tem('gerenciar_cadastros')): ?>
                        <a href="cliente_form.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm">Editar</a>
                    <?php endif; ?>
                    <?php if (Permissao::tem('excluir')): ?>
                        <form method="post" action="cliente_acao.php" style="display:inline" onsubmit="return confirm('Ativar/Inativar o cliente &quot;<?= htmlspecialchars(addslashes($c['razao_social']), ENT_QUOTES) ?>&quot;?')">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="acao" value="toggle">
                            <button type="submit" class="btn btn-sm"><?= $c['ativo'] ? 'Desativar' : 'Ativar' ?></button>
                        </form>
                        <form method="post" action="cliente_acao.php" style="display:inline" onsubmit="return confirm('ATENÇÃO: Excluir PERMANENTEMENTE o cliente &quot;<?= htmlspecialchars(addslashes($c['razao_social']), ENT_QUOTES) ?>&quot;?\n\nEsta ação NÃO pode ser desfeita. Se houver contas a receber ou serviços vinculados, a exclusão será bloqueada.')">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
