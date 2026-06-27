<?php /** @var array $recorrencias */ /** @var string $tipo */ ?>
<div class="page-header">
    <h1>🔁 Recorrências (Contas a <?= $tipo === 'pagar' ? 'Pagar' : 'Receber' ?>)</h1>
    <div>
        <a href="<?= $tipo === 'pagar' ? 'recorrencia_pagar_form' : 'recorrencia_receber_form' ?>.php" class="btn btn-primary">+ Nova Recorrência</a>
        <a href="<?= $tipo === 'pagar' ? 'contas_pagar' : 'contas_receber' ?>.php" class="btn">Ver Contas</a>
    </div>
</div>

<form method="post" action="recorrencia_<?= $tipo ?>_gerar.php" onsubmit="return confirm('Gerar contas do mês para todas as recorrências ativas?')">
    <button type="submit" class="btn btn-success">⚙ Gerar Contas do Mês</button>
</form>

<table class="table">
    <thead>
        <tr>
            <th>Descrição</th>
            <th><?= $tipo === 'pagar' ? 'Fornecedor' : 'Cliente' ?></th>
            <th>Categoria</th>
            <th>Dia Venc.</th>
            <th class="text-right">Valor</th>
            <th>Próxima Geração</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($recorrencias)): ?>
            <tr><td colspan="8" class="muted center">Nenhuma recorrência cadastrada.</td></tr>
        <?php else: foreach ($recorrencias as $r): ?>
            <tr>
                <td><strong><?= htmlspecialchars($r['descricao']) ?></strong></td>
                <td><?= htmlspecialchars($r[$tipo === 'pagar' ? 'fornecedor_nome' : 'cliente_nome']) ?></td>
                <td><?= htmlspecialchars($r['categoria_nome']) ?></td>
                <td class="text-center"><?= (int)$r['dia_vencimento'] ?></td>
                <td class="text-right">R$ <?= number_format((float)$r['valor'], 2, ',', '.') ?></td>
                <td><?= $r['proxima_geracao'] ? dataIsoParaBr($r['proxima_geracao']) : '-' ?></td>
                <td>
                    <span class="badge badge-<?= $r['ativa'] ? 'success' : 'secondary' ?>">
                        <?= $r['ativa'] ? 'Ativa' : 'Inativa' ?>
                    </span>
                </td>
                <td class="actions">
                    <a href="recorrencia_<?= $tipo ?>_form.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm">Editar</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>