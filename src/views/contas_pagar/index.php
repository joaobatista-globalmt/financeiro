<?php
/** @var array $contas */
/** @var array $fornecedores */
/** @var array $categorias */
/** @var array $resumo */
/** @var array $filtros */
?>
<div class="page-header">
    <h1>💸 Contas a Pagar</h1>
    <div>
        <a href="conta_form.php" class="btn btn-primary">+ Nova Conta</a>
        <a href="recorrencia_pagar.php" class="btn">Recorrências</a>
    </div>
</div>

<!-- Cards resumo -->
<div class="cards-grid">
    <div class="card card-danger">
        <div class="card-title">Atrasadas</div>
        <div class="card-value">R$ <?= number_format((float)($resumo['atrasadas'] ?? 0), 2, ',', '.') ?></div>
    </div>
    <div class="card card-warning">
        <div class="card-title">Próx. 7 dias</div>
        <div class="card-value">R$ <?= number_format((float)($resumo['proximos_7_dias'] ?? 0), 2, ',', '.') ?></div>
    </div>
    <div class="card card-secondary">
        <div class="card-title">Total Pendente</div>
        <div class="card-value">R$ <?= number_format((float)($resumo['total_pendente'] ?? 0), 2, ',', '.') ?></div>
    </div>
    <div class="card card-success">
        <div class="card-title">Pago no Mês</div>
        <div class="card-value">R$ <?= number_format((float)($resumo['pago_mes'] ?? 0), 2, ',', '.') ?></div>
    </div>
</div>

<!-- Filtros -->
<form method="get" class="filters-bar">
    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <option value="">Todos</option>
            <option value="pendente"  <?= $filtros['status'] === 'pendente'  ? 'selected' : '' ?>>Pendente</option>
            <option value="aprovada"  <?= $filtros['status'] === 'aprovada'  ? 'selected' : '' ?>>Aprovada</option>
            <option value="paga"      <?= $filtros['status'] === 'paga'      ? 'selected' : '' ?>>Paga</option>
            <option value="cancelada" <?= $filtros['status'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
        </select>
    </div>
    <div class="form-group">
        <label>Fornecedor</label>
        <select name="fornecedor_id">
            <option value="0">Todos</option>
            <?php foreach ($fornecedores as $f): ?>
                <option value="<?= (int)$f['id'] ?>" <?= $filtros['fornecedor_id'] == $f['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['razao_social']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>Categoria</label>
        <select name="categoria_id">
            <option value="0">Todas</option>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>" <?= $filtros['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>De</label>
        <input type="date" name="data_inicio" value="<?= htmlspecialchars($filtros['data_inicio']) ?>">
    </div>
    <div class="form-group">
        <label>Até</label>
        <input type="date" name="data_fim" value="<?= htmlspecialchars($filtros['data_fim']) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="contas_pagar.php" class="btn">Limpar</a>
</form>

<!-- Tabela -->
<table class="table">
    <thead>
        <tr>
            <th>Vencimento</th>
            <th>Descrição</th>
            <th>Fornecedor</th>
            <th>Categoria</th>
            <th class="text-right">Valor</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($contas)): ?>
            <tr><td colspan="7" class="muted center">Nenhuma conta encontrada.</td></tr>
        <?php else:
            $hoje = date('Y-m-d');
            foreach ($contas as $c):
                $atrasada = in_array($c['status'], ['pendente', 'aprovada'], true) && $c['data_vencimento'] < $hoje;
                $statusBadge = [
                    'pendente'  => 'warning',
                    'aprovada'  => 'info',
                    'paga'      => 'success',
                    'cancelada' => 'secondary',
                ];
        ?>
            <tr class="<?= $atrasada ? 'row-danger' : '' ?>">
                <td>
                    <?= dataIsoParaBr($c['data_vencimento']) ?>
                    <?php if ($atrasada): ?>
                        <small class="text-danger">(atrasada)</small>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="conta_detalhe.php?id=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['descricao']) ?></a>
                    <?php if ($c['parcelas'] > 1): ?>
                        <small class="muted">parcela <?= (int)$c['parcela_atual'] ?>/<?= (int)$c['parcelas'] ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($c['fornecedor_nome']) ?></td>
                <td>
                    <span class="badge" style="background: <?= htmlspecialchars($c['categoria_cor']) ?>; color: #fff;">
                        <?= htmlspecialchars($c['categoria_nome']) ?>
                    </span>
                </td>
                <td class="text-right">R$ <?= number_format((float)$c['valor'], 2, ',', '.') ?></td>
                <td>
                    <span class="badge badge-<?= $statusBadge[$c['status']] ?? 'secondary' ?>">
                        <?= htmlspecialchars($c['status']) ?>
                    </span>
                </td>
                <td class="actions">
                    <a href="conta_detalhe.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm">Ver</a>
                    <?php if ($c['status'] !== 'paga' && $c['status'] !== 'cancelada' && Permissao::tem('criar')): ?>
                        <a href="conta_form.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm" title="Editar dados da conta (descrição, valor, fornecedor, datas, etc)">✏️ Editar</a>
                    <?php endif; ?>
                    <?php if ($c['status'] === 'pendente' && Permissao::tem('aprovar')): ?>
                        <form method="post" action="conta_acao.php" style="display:inline">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="acao" value="aprovar">
                            <button type="submit" class="btn btn-sm">Aprovar</button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($c['status'], ['pendente', 'aprovada'], true) && Permissao::tem('pagar')): ?>
                        <a href="conta_form.php?id=<?= (int)$c['id'] ?>#pagar" class="btn btn-sm btn-success">Pagar</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>