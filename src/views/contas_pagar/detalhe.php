<?php
/** @var array $conta */
/** @var array $anexos */
/** @var array $parcelas */
?>
<div class="page-header">
    <h1>Conta a Pagar #<?= (int)$conta['id'] ?></h1>
    <a href="contas_pagar.php" class="btn">← Voltar</a>
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
            $statusBadge = ['pendente'=>'warning','aprovada'=>'info','paga'=>'success','cancelada'=>'secondary'];
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
            <tr><th>Fornecedor</th><td><?= htmlspecialchars($conta['fornecedor_nome']) ?> (<?= htmlspecialchars($conta['fornecedor_doc'] ?? '-') ?>)</td></tr>
            <tr><th>Categoria</th><td><?= htmlspecialchars($conta['categoria_nome']) ?></td></tr>
            <tr><th>Nº Documento</th><td><?= htmlspecialchars($conta['numero_documento'] ?? '-') ?></td></tr>
            <tr><th>Emissão</th><td><?= dataIsoParaBr($conta['data_emissao']) ?></td></tr>
            <tr><th>Vencimento</th><td><?= dataIsoParaBr($conta['data_vencimento']) ?></td></tr>
            <tr><th>Forma Pagamento</th><td><?= htmlspecialchars($conta['forma_pagamento']) ?></td></tr>
        </table>
    </div>

    <div class="col">
        <h3>💰 Pagamento</h3>
        <?php if ($conta['status'] === 'paga'): ?>
            <table class="table table-compact">
                <tr><th>Data Pagamento</th><td><?= dataIsoParaBr($conta['data_pagamento']) ?></td></tr>
                <tr><th>Valor Pago</th><td>R$ <?= number_format((float)$conta['valor_pago'], 2, ',', '.') ?></td></tr>
                <tr><th>Conta Bancária</th><td><?= htmlspecialchars($conta['conta_bancaria_descricao'] ?? '-') ?></td></tr>
                <tr><th>Pago por</th><td><?= htmlspecialchars($conta['usuario_pagamento_nome'] ?? '-') ?></td></tr>
            </table>
        <?php elseif ($conta['status'] === 'cancelada'): ?>
            <p class="muted">Conta cancelada.</p>
        <?php else: ?>
            <p class="muted">Conta ainda não paga.</p>
        <?php endif; ?>
    </div>
</div>

<h3>👥 Usuários</h3>
<table class="table table-compact">
    <tr><th>Criado por</th><td><?= htmlspecialchars($conta['usuario_criacao_nome']) ?> em <?= htmlspecialchars($conta['data_criacao']) ?></td></tr>
    <?php if ($conta['usuario_aprovacao_nome']): ?>
        <tr><th>Aprovado por</th><td><?= htmlspecialchars($conta['usuario_aprovacao_nome']) ?> em <?= htmlspecialchars($conta['data_aprovacao']) ?></td></tr>
    <?php endif; ?>
</table>

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

<?php if (!empty($anexos)): ?>
<h3>📎 Anexos</h3>
<ul>
    <?php foreach ($anexos as $a): ?>
        <li>
            <a href="anexo_download.php?tipo=conta_pagar&id=<?= (int)$a['id'] ?>">
                <?= htmlspecialchars($a['nome_original']) ?>
            </a>
            <small class="muted">(<?= round($a['tamanho'] / 1024, 1) ?> KB)</small>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif; ?>

<div class="actions-bar">
    <?php if ($conta['status'] === 'pendente' && Permissao::tem('aprovar')): ?>
        <form method="post" action="conta_acao.php" style="display:inline">
            <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
            <input type="hidden" name="acao" value="aprovar">
            <button class="btn">Aprovar</button>
        </form>
    <?php endif; ?>

    <?php if (in_array($conta['status'], ['pendente', 'aprovada'], true) && Permissao::tem('pagar')): ?>
        <a href="conta_form.php?id=<?= (int)$conta['id'] ?>#pagar" class="btn btn-success">💰 Pagar</a>
    <?php endif; ?>

    <?php if ($conta['status'] === 'paga' && Permissao::tem('criar')): ?>
        <a href="editar_pagamento.php?id=<?= (int)$conta['id'] ?>" class="btn">✏️ Editar Pagamento</a>
    <?php endif; ?>

    <?php if ($conta['status'] !== 'paga' && $conta['status'] !== 'cancelada'): ?>
        <form method="post" action="conta_acao.php" style="display:inline" onsubmit="return confirm('Cancelar esta conta?')">
            <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
            <input type="hidden" name="acao" value="cancelar">
            <button class="btn">Cancelar</button>
        </form>
    <?php endif; ?>

    <?php if (in_array($conta['status'], ['pendente', 'aprovada', 'cancelada'], true) && Permissao::tem('excluir')): ?>
        <form method="post" action="conta_acao.php" style="display:inline"
              onsubmit="return confirm('ATENÇÃO: Excluir DEFINITIVAMENTE esta conta? Não dá pra desfazer.\n\nSó é possível se ela ainda não tiver sido paga nem for pai de parcelas.\n\nContinuar?')">
            <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
            <input type="hidden" name="acao" value="excluir">
            <button class="btn btn-danger">🗑️ Excluir</button>
        </form>
    <?php endif; ?>

    <form method="post" action="anexo_upload.php" enctype="multipart/form-data" style="display:inline">
        <input type="hidden" name="tipo_origem" value="conta_pagar">
        <input type="hidden" name="origem_id" value="<?= (int)$conta['id'] ?>">
        <input type="file" name="arquivo" accept="application/pdf" required>
        <button type="submit" class="btn">📎 Anexar PDF</button>
    </form>
</div>