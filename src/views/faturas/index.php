<?php
/** @var array $faturas */
/** @var array $resumo */
/** @var array $clientes */
/** @var string $mes */
/** @var int $clienteId */
/** @var string $status */
$resumo   = $resumo ?? ['qtd' => 0, 'total' => 0, 'recebido' => 0, 'pendente' => 0];
$clientes = $clientes ?? [];
?>
<div class="page-header">
    <h1>Faturas - <?= htmlspecialchars($mes) ?></h1>
    <div>
        <a href="fatura_acao.php?acao=form&mes=<?= urlencode($mes) ?>" class="btn btn-primary">
            + Gerar Faturas do Mês
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_sucesso'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_sucesso']) ?></div>
    <?php unset($_SESSION['flash_sucesso']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_erro'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_erro']) ?></div>
    <?php unset($_SESSION['flash_erro']); ?>
<?php endif; ?>

<!-- Resumo do mes -->
<div class="cards-resumo">
    <div class="card-resumo">
        <div class="card-resumo-label">Qtd Faturas</div>
        <div class="card-resumo-value"><?= (int)$resumo['qtd'] ?></div>
    </div>
    <div class="card-resumo">
        <div class="card-resumo-label">Total</div>
        <div class="card-resumo-value">R$ <?= number_format((float)$resumo['total'], 2, ',', '.') ?></div>
    </div>
    <div class="card-resumo">
        <div class="card-resumo-label">Recebido</div>
        <div class="card-resumo-value" style="color:#15803d;">
            R$ <?= number_format((float)$resumo['recebido'], 2, ',', '.') ?>
        </div>
    </div>
    <div class="card-resumo">
        <div class="card-resumo-label">Pendente</div>
        <div class="card-resumo-value" style="color:#b91c1c;">
            R$ <?= number_format((float)$resumo['pendente'], 2, ',', '.') ?>
        </div>
    </div>
</div>

<!-- Filtros -->
<form method="get" class="form-filtros" style="margin: 16px 0; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
    <div class="form-group" style="margin:0;">
        <label>Mês Referência</label>
        <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>">
    </div>
    <div class="form-group" style="margin:0; min-width:200px;">
        <label>Cliente</label>
        <select name="cliente_id">
            <option value="0">- Todos -</option>
            <?php foreach ($clientes as $cli): ?>
                <option value="<?= (int)$cli['id'] ?>" <?= $clienteId === (int)$cli['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cli['razao_social']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group" style="margin:0;">
        <label>Status</label>
        <select name="status">
            <option value=""        <?= $status === ''        ? 'selected' : '' ?>>- Todos -</option>
            <option value="aberta"  <?= $status === 'aberta'  ? 'selected' : '' ?>>Aberta</option>
            <option value="paga"    <?= $status === 'paga'    ? 'selected' : '' ?>>Paga</option>
            <option value="parcial" <?= $status === 'parcial' ? 'selected' : '' ?>>Parcial</option>
            <option value="vencida" <?= $status === 'vencida' ? 'selected' : '' ?>>Vencida</option>
            <option value="cancelada" <?= $status === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
        </select>
    </div>
    <div>
        <button type="submit" class="btn">Filtrar</button>
    </div>
</form>

<!-- Lista de faturas -->
<?php if (empty($faturas)): ?>
    <div class="alert alert-info">
        Nenhuma fatura encontrada para <?= htmlspecialchars($mes) ?>.
        <a href="fatura_acao.php?acao=form&mes=<?= urlencode($mes) ?>">Gerar faturas do mês</a>.
    </div>
<?php else: ?>
<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Pago em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($faturas as $f): ?>
                <tr>
                    <td>#<?= (int)$f['id'] ?></td>
                    <td>
                        <a href="cliente_form.php?id=<?= (int)$f['cliente_id'] ?>">
                            <?= htmlspecialchars($f['cliente_nome']) ?>
                        </a>
                    </td>
                    <td><?= date('d/m/Y', strtotime($f['data_vencimento'])) ?></td>
                    <td style="text-align:right;">
                        R$ <?= number_format((float)$f['valor_total'], 2, ',', '.') ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= htmlspecialchars($f['status']) ?>">
                            <?= htmlspecialchars(ucfirst($f['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <?= $f['data_pagamento'] ? date('d/m/Y', strtotime($f['data_pagamento'])) : '-' ?>
                    </td>
                    <td>
                        <a href="fatura_acao.php?acao=show&id=<?= (int)$f['id'] ?>" class="btn btn-sm">
                            Ver
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<style>
.cards-resumo { display:flex; gap:12px; margin: 16px 0; flex-wrap: wrap; }
.card-resumo {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 8px;
    padding: 12px 16px; min-width: 140px;
}
.card-resumo-label { font-size: 12px; color: #6b7280; }
.card-resumo-value { font-size: 20px; font-weight: 600; margin-top: 4px; }
.badge { padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
.badge-aberta { background:#fef3c7; color:#92400e; }
.badge-paga { background:#d1fae5; color:#065f46; }
.badge-parcial { background:#dbeafe; color:#1e40af; }
.badge-vencida { background:#fee2e2; color:#991b1b; }
.badge-cancelada { background:#e5e7eb; color:#374151; }
</style>
