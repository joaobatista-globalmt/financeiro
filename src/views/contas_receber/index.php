<?php
/** @var array $contas */
/** @var array $clientes */
/** @var array $categorias */
/** @var array $resumo */
/** @var array $filtros */
?>
<div class="page-header">
    <h1>💰 Contas a Receber</h1>
    <div>
        <a href="conta_receber_form.php" class="btn btn-primary">+ Nova Conta</a>
        <a href="recorrencia_receber.php" class="btn">Recorrências</a>
    </div>
</div>

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
        <div class="card-title">Recebido no Mês</div>
        <div class="card-value">R$ <?= number_format((float)($resumo['recebido_mes'] ?? 0), 2, ',', '.') ?></div>
    </div>
</div>

<form method="get" class="filters-bar">
    <div class="form-group">
        <label>Status</label>
        <select name="status">
            <option value="">Todos</option>
            <option value="pendente"  <?= $filtros['status'] === 'pendente'  ? 'selected' : '' ?>>Pendente</option>
            <option value="aprovada"  <?= $filtros['status'] === 'aprovada'  ? 'selected' : '' ?>>Aprovada</option>
            <option value="recebida"  <?= $filtros['status'] === 'recebida'  ? 'selected' : '' ?>>Recebida</option>
            <option value="cancelada" <?= $filtros['status'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
        </select>
    </div>
    <div class="form-group">
        <label>Cliente</label>
        <select name="cliente_id">
            <option value="0">Todos</option>
            <?php foreach ($clientes as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $filtros['cliente_id'] == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['razao_social']) ?>
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
    <a href="contas_receber.php" class="btn">Limpar</a>
</form>

<table class="table">
    <thead>
        <tr>
            <th>Vencimento</th>
            <th>Descrição</th>
            <th>Cliente</th>
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
                $statusBadge = ['pendente'=>'warning','aprovada'=>'info','recebida'=>'success','cancelada'=>'secondary'];
        ?>
            <tr class="<?= $atrasada ? 'row-danger' : '' ?>">
                <td>
                    <?= dataIsoParaBr($c['data_vencimento']) ?>
                    <?php if ($atrasada): ?>
                        <small class="text-danger">(atrasada)</small>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="conta_receber_detalhe.php?id=<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['descricao']) ?></a>
                    <?php if ($c['parcelas'] > 1): ?>
                        <small class="muted">parcela <?= (int)$c['parcela_atual'] ?>/<?= (int)$c['parcelas'] ?></small>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($c['cliente_nome']) ?></td>
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
                    <a href="conta_receber_detalhe.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm">Ver</a>
                    <?php if ($c['status'] !== 'recebida' && $c['status'] !== 'cancelada' && Permissao::tem('criar')): ?>
                        <a href="conta_receber_form.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm" title="Editar dados da conta (descrição, valor, cliente, datas, etc)">✏️ Editar</a>
                    <?php endif; ?>
                    <?php if ($c['status'] === 'pendente' && Permissao::tem('aprovar')): ?>
                        <form method="post" action="conta_receber_acao.php" style="display:inline">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <input type="hidden" name="acao" value="aprovar">
                            <button type="submit" class="btn btn-sm">Aprovar</button>
                        </form>
                    <?php endif; ?>
                    <?php if (in_array($c['status'], ['pendente', 'aprovada'], true) && Permissao::tem('receber')): ?>
                        <a href="conta_receber_form.php?id=<?= (int)$c['id'] ?>#receber" class="btn btn-sm btn-success">Receber</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>