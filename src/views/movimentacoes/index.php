<?php
/** @var array $conta */
/** @var array $movs */
/** @var array $filtros */
/** @var float $saldoAtual */
/** @var float $saldoPeriodo */
/** @var float $totalEntradas */
/** @var float $totalSaidas */
?>
<div class="page-header">
    <h1>📊 Extrato: <?= htmlspecialchars($conta['descricao']) ?></h1>
    <div>
        <a href="movimentacao_form.php?conta_id=<?= (int)$conta['id'] ?>" class="btn btn-primary">+ Lançamento Manual</a>
        <a href="contas_bancarias.php" class="btn">← Voltar</a>
    </div>
</div>

<!-- Cards resumo -->
<div class="cards-grid">
    <div class="card card-primary">
        <div class="card-title">Saldo Atual</div>
        <div class="card-value">R$ <?= number_format($saldoAtual, 2, ',', '.') ?></div>
    </div>
    <div class="card card-info">
        <div class="card-title">Saldo em <?= dataIsoParaBr($filtros['data_fim']) ?></div>
        <div class="card-value">R$ <?= number_format($saldoPeriodo, 2, ',', '.') ?></div>
    </div>
    <div class="card card-success">
        <div class="card-title">Entradas (período)</div>
        <div class="card-value">R$ <?= number_format($totalEntradas, 2, ',', '.') ?></div>
    </div>
    <div class="card card-danger">
        <div class="card-title">Saídas (período)</div>
        <div class="card-value">R$ <?= number_format($totalSaidas, 2, ',', '.') ?></div>
    </div>
</div>

<!-- Filtros -->
<form method="get" class="filters-bar">
    <input type="hidden" name="conta_id" value="<?= (int)$conta['id'] ?>">
    <div class="form-group">
        <label>Data início</label>
        <input type="date" name="data_inicio" value="<?= htmlspecialchars($filtros['data_inicio']) ?>">
    </div>
    <div class="form-group">
        <label>Data fim</label>
        <input type="date" name="data_fim" value="<?= htmlspecialchars($filtros['data_fim']) ?>">
    </div>
    <div class="form-group">
        <label>Tipo</label>
        <select name="tipo">
            <option value="">Todos</option>
            <option value="entrada" <?= $filtros['tipo'] === 'entrada' ? 'selected' : '' ?>>Entrada</option>
            <option value="saida"   <?= $filtros['tipo'] === 'saida'   ? 'selected' : '' ?>>Saída</option>
        </select>
    </div>
    <div class="form-group">
        <label>Origem</label>
        <select name="origem">
            <option value="">Todas</option>
            <option value="manual"         <?= $filtros['origem'] === 'manual' ? 'selected' : '' ?>>Manual</option>
            <option value="conta_pagar"    <?= $filtros['origem'] === 'conta_pagar' ? 'selected' : '' ?>>Conta a Pagar</option>
            <option value="conta_receber"  <?= $filtros['origem'] === 'conta_receber' ? 'selected' : '' ?>>Conta a Receber</option>
            <option value="transferencia"  <?= $filtros['origem'] === 'transferencia' ? 'selected' : '' ?>>Transferência</option>
        </select>
    </div>
    <button type="submit" class="btn btn-primary">Filtrar</button>
</form>

<!-- Tabela -->
<table class="table">
    <thead>
        <tr>
            <th>Data</th>
            <th>Descrição</th>
            <th>Tipo</th>
            <th>Origem</th>
            <th>Usuário</th>
            <th class="text-right">Valor</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($movs)): ?>
            <tr><td colspan="7" class="muted center">Nenhuma movimentação no período.</td></tr>
        <?php else: foreach ($movs as $m): ?>
            <tr>
                <td><?= dataIsoParaBr($m['data_movimento']) ?></td>
                <td><?= htmlspecialchars($m['descricao']) ?></td>
                <td>
                    <span class="badge badge-<?= $m['tipo'] === 'entrada' ? 'success' : 'danger' ?>">
                        <?= $m['tipo'] === 'entrada' ? '↗ Entrada' : '↘ Saída' ?>
                    </span>
                </td>
                <td><small><?= htmlspecialchars($m['origem']) ?></small></td>
                <td><small><?= htmlspecialchars($m['usuario_nome']) ?></small></td>
                <td class="text-right <?= $m['tipo'] === 'entrada' ? 'text-success' : 'text-danger' ?>">
                    <?= $m['tipo'] === 'entrada' ? '+' : '-' ?> R$ <?= number_format((float)$m['valor'], 2, ',', '.') ?>
                </td>
                <td class="actions">
                    <?php if ($m['origem'] === 'manual' && Permissao::tem('criar')): ?>
                        <a href="movimentacao_form.php?conta_id=<?= (int)$conta['id'] ?>&id=<?= (int)$m['id'] ?>" class="btn btn-sm">Editar</a>
                    <?php endif; ?>
                    <?php if ($m['origem'] === 'manual' && Permissao::tem('excluir')): ?>
                        <form method="post" action="movimentacao_acao.php" style="display:inline" onsubmit="return confirm('Excluir lançamento?')">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <input type="hidden" name="conta_id" value="<?= (int)$conta['id'] ?>">
                            <input type="hidden" name="acao" value="excluir">
                            <button type="submit" class="btn btn-sm btn-danger">Excluir</button>
                        </form>
                    <?php else: ?>
                        <small class="muted">(automática)</small>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>