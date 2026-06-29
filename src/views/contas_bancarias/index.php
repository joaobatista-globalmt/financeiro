<?php /** @var array $contas */ /** @var float $saldoTotal */ ?>
<div class="page-header">
    <h1>💳 Contas Bancárias</h1>
    <?php if (Permissao::tem('criar')): ?>
        <div class="dropdown page-action-dropdown">
            <button type="button" class="btn btn-primary dropdown-toggle">+ Novo ▾</button>
            <ul class="dropdown-menu dropdown-menu-right">
                <li><a href="conta_bancaria_form.php">💳 Nova Conta Bancária</a></li>
                <li><a href="movimentacao_selecionar_conta.php">💸 Lançamento Manual</a></li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<div class="card card-primary card-large">
    <div class="card-title">Saldo Consolidado</div>
    <div class="card-value">R$ <?= number_format($saldoTotal, 2, ',', '.') ?></div>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Descrição</th>
            <th>Tipo</th>
            <th>Banco</th>
            <th>Agência/Conta</th>
            <th>Titular</th>
            <th class="text-right">Saldo Inicial</th>
            <th class="text-right">Saldo Atual</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($contas)): ?>
            <tr><td colspan="9" class="muted center">Nenhuma conta cadastrada.</td></tr>
        <?php else: foreach ($contas as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['descricao']) ?></strong></td>
                <td><?= htmlspecialchars($c['tipo']) ?></td>
                <td><?= htmlspecialchars($c['banco'] ?? '-') ?></td>
                <td>
                    <?= htmlspecialchars($c['agencia'] ?? '-') ?> /
                    <?= htmlspecialchars($c['numero_conta'] ?? '-') ?>-<?= htmlspecialchars($c['digito'] ?? '') ?>
                </td>
                <td><?= htmlspecialchars($c['titular'] ?? '-') ?></td>
                <td class="text-right">R$ <?= number_format((float)$c['saldo_inicial'], 2, ',', '.') ?></td>
                <td class="text-right <?= (float)$c['saldo_atual'] < 0 ? 'text-danger' : 'text-success' ?>">
                    <strong>R$ <?= number_format((float)$c['saldo_atual'], 2, ',', '.') ?></strong>
                </td>
                <td>
                    <span class="badge badge-<?= $c['ativo'] ? 'success' : 'secondary' ?>">
                        <?= $c['ativo'] ? 'Ativa' : 'Inativa' ?>
                    </span>
                </td>
                <td class="actions">
                    <a href="movimentacoes.php?conta_id=<?= (int)$c['id'] ?>" class="btn btn-sm">Extrato</a>
                    <?php if (Permissao::tem('criar')): ?>
                        <a href="conta_bancaria_form.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm">Editar</a>
                    <?php endif; ?>
                    <?php if (Permissao::tem('excluir')): ?>
                        <form method="post" action="conta_bancaria_acao.php" style="display:inline" onsubmit="return confirm('Excluir conta? Se houver movimentações, será apenas inativada.')">
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