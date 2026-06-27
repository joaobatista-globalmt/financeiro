<?php
/** @var array $conta */
/** @var array $anexos */
/** @var array $parcelas */
?>
<div class="page-header">
    <h1>Conta a Receber #<?= (int)$conta['id'] ?></h1>
    <a href="contas_receber.php" class="btn">← Voltar</a>
</div>

<div class="detail-grid">
    <div class="card">
        <div class="card-title">Descrição</div>
        <p><?= htmlspecialchars($conta['descricao']) ?></p>
    </div>
    <div class="card">
        <div class="card-title">Status</div>
        <p>
            <?php
            $statusBadge = ['pendente'=>'warning','aprovada'=>'info','recebida'=>'success','cancelada'=>'secondary'];
            ?>
            <span class="badge badge-<?= $statusBadge[$conta['status']] ?? 'secondary' ?>">
                <?= htmlspecialchars($conta['status']) ?>
            </span>
        </p>
    </div>
    <div class="card">
        <div class="card-title">Valor</div>
        <p>R$ <?= number_format((float)$conta['valor'], 2, ',', '.') ?></p>
    </div>
</div>

<div class="row">
    <div class="col">
        <h3>📋 Informações</h3>
        <table class="table table-compact">
            <tr><th>Cliente</th><td><?= htmlspecialchars($conta['cliente_nome']) ?> (<?= htmlspecialchars($conta['cliente_doc'] ?? '-') ?>)</td></tr>
            <tr><th>Categoria</th><td><?= htmlspecialchars($conta['categoria_nome']) ?></td></tr>
            <tr><th>Nº Documento</th><td><?= htmlspecialchars($conta['numero_documento'] ?? '-') ?></td></tr>
            <tr><th>Emissão</th><td><?= dataIsoParaBr($conta['data_emissao']) ?></td></tr>
            <tr><th>Vencimento</th><td><?= dataIsoParaBr($conta['data_vencimento']) ?></td></tr>
            <tr><th>Forma Recebimento</th><td><?= htmlspecialchars($conta['forma_recebimento']) ?></td></tr>
        </table>
    </div>

    <div class="col">
        <h3>💰 Recebimento</h3>
        <?php if ($conta['status'] === 'recebida'): ?>
            <table class="table table-compact">
                <tr><th>Data Recebimento</th><td><?= dataIsoParaBr($conta['data_recebimento']) ?></td></tr>
                <tr><th>Valor Recebido</th><td>R$ <?= number_format((float)$conta['valor_recebido'], 2, ',', '.') ?></td></tr>
                <tr><th>Conta Bancária</th><td><?= htmlspecialchars($conta['conta_bancaria_descricao'] ?? '-') ?></td></tr>
                <tr><th>Recebido por</th><td><?= htmlspecialchars($conta['usuario_recebimento_nome'] ?? '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="muted">Conta ainda não recebida.</p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($parcelas)): ?>
<h3>📦 Parcelas</h3>
<table class="table">
    <thead><tr><th>Parcela</th><th>Vencimento</th><th>Valor</th><th>Status</th></tr></thead>
    <tbody>
        <?php foreach ($parcelas as $p): ?>
            <tr>
                <td><?= (int)$p['parcela_atual'] ?>/<?= (int)$p['parcelas'] ?></td>
                <td><?= dataIsoParaBr($p['data_vencimento']) ?></td>
                <td>R$ <?= number_format((float)$p['valor'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($p['status']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="actions-bar">
    <?php if ($conta['status'] === 'pendente' && Permissao::tem('aprovar')): ?>
        <form method="post" action="conta_receber_acao.php" style="display:inline">
            <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
            <input type="hidden" name="acao" value="aprovar">
            <button class="btn">Aprovar</button>
        </form>
    <?php endif; ?>

    <?php if (in_array($conta['status'], ['pendente', 'aprovada'], true) && Permissao::tem('receber')): ?>
        <a href="conta_receber_form.php?id=<?= (int)$conta['id'] ?>#receber" class="btn btn-success">💰 Receber</a>
    <?php endif; ?>

    <?php if ($conta['status'] !== 'recebida' && $conta['status'] !== 'cancelada'): ?>
        <form method="post" action="conta_receber_acao.php" style="display:inline" onsubmit="return confirm('Cancelar esta conta?')">
            <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
            <input type="hidden" name="acao" value="cancelar">
            <button class="btn">Cancelar</button>
        </form>
    <?php endif; ?>

    <form method="post" action="anexo_upload.php" enctype="multipart/form-data" style="display:inline">
        <input type="hidden" name="tipo_origem" value="conta_receber">
        <input type="hidden" name="origem_id" value="<?= (int)$conta['id'] ?>">
        <input type="file" name="arquivo" accept="application/pdf" required>
        <button type="submit" class="btn">📎 Anexar Recibo PDF</button>
    </form>
</div>