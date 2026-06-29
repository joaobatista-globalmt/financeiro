<?php
/** @var array $contas */
/** @var int $contaId */
/** @var string $dataInicio */
/** @var string $dataFim */
?>
<div class="page-header">
    <h1>📋 Extrato de Conta Bancária</h1>
    <a href="relatorios.php" class="btn">← Voltar</a>
</div>

<div class="card">
    <p class="muted">
        Selecione a conta bancária e o período para gerar o relatório de extrato.
        O relatório mostra todas as movimentações (manuais e automáticas), saldo anterior,
        entradas, saídas e saldo final do período.
    </p>

    <?php if (empty($contas)): ?>
        <div class="flash flash-erro">Você não possui contas bancárias ativas. Cadastre uma conta primeiro em <a href="contas_bancarias.php">Contas Bancárias</a>.</div>
    <?php else: ?>
        <form method="get" action="relatorio_show.php" class="form">
            <input type="hidden" name="tipo" value="extrato_conta">

            <div class="row">
                <div class="form-group col-12">
                    <label>Conta Bancária *</label>
                    <select name="conta_id" required autofocus>
                        <option value="">— Selecione —</option>
                        <?php foreach ($contas as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= $contaId === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['descricao']) ?>
                                <?php if (!empty($c['banco'])): ?>
                                    (<?= htmlspecialchars($c['banco']) ?>
                                    <?php if (!empty($c['agencia'])): ?>
                                        / Ag. <?= htmlspecialchars($c['agencia']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($c['numero_conta'])): ?>
                                        / Conta <?= htmlspecialchars($c['numero_conta']) ?>-<?= htmlspecialchars($c['digito'] ?? '') ?>
                                    <?php endif; ?>
                                    )
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="form-group col-6">
                    <label>Data início</label>
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
                </div>
                <div class="form-group col-6">
                    <label>Data fim</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Gerar Relatório</button>
                <a href="relatorios.php" class="btn">Cancelar</a>
            </div>
        </form>
    <?php endif; ?>
</div>