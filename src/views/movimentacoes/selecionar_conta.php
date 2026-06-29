<?php
/** @var array $contas */
?>
<div class="page-header">
    <h1>💸 Lançamento Manual — Selecionar Conta</h1>
    <a href="contas_bancarias.php" class="btn">← Voltar</a>
</div>

<div class="card">
    <p class="muted">
        Escolha a conta bancária que receberá o lançamento. Após escolher,
        você poderá informar tipo (entrada/saída), valor, data e descrição.
    </p>

    <?php if (empty($contas)): ?>
        <div class="flash flash-erro">Você não possui contas bancárias cadastradas. <a href="conta_bancaria_form.php">Cadastre uma conta primeiro.</a></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Conta</th>
                    <th>Tipo</th>
                    <th>Banco / Agência / Conta</th>
                    <th>Titular</th>
                    <th class="text-right">Saldo Atual</th>
                    <th class="text-right">Movs</th>
                    <th>Status</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contas as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['descricao']) ?></strong></td>
                        <td>
                            <?php
                            $tipos = [
                                'conta_corrente' => 'CC',
                                'poupanca'       => 'Poupança',
                                'caixa_fisico'   => 'Caixa',
                                'cartao'         => 'Cartão',
                                'investimento'   => 'Invest.',
                            ];
                            echo htmlspecialchars($tipos[$c['tipo']] ?? $c['tipo']);
                            ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($c['banco'] ?? '-') ?>
                            <?php if (!empty($c['agencia'])): ?>
                                / Ag. <?= htmlspecialchars($c['agencia']) ?>
                            <?php endif; ?>
                            <?php if (!empty($c['numero_conta'])): ?>
                                / <?= htmlspecialchars($c['numero_conta']) ?>-<?= htmlspecialchars($c['digito'] ?? '') ?>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($c['titular'] ?? '-') ?></td>
                        <td class="text-right <?= (float)$c['saldo_atual'] < 0 ? 'text-danger' : 'text-success' ?>">
                            <strong>R$ <?= number_format((float)$c['saldo_atual'], 2, ',', '.') ?></strong>
                        </td>
                        <td class="text-right"><?= (int)$c['qtd_movs'] ?></td>
                        <td>
                            <span class="badge badge-<?= $c['ativo'] ? 'success' : 'secondary' ?>">
                                <?= $c['ativo'] ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </td>
                        <td class="actions">
                            <?php if ($c['ativo'] && Permissao::tem('criar')): ?>
                                <a href="movimentacao_form.php?conta_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary">
                                    + Lançar nesta conta
                                </a>
                            <?php else: ?>
                                <small class="muted"><?= !$c['ativo'] ? 'inativa' : 'sem permissão' ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>