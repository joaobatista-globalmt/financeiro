<?php /** @var array $servicos */ /** @var string $cnae */ /** @var string $categoria */ /** @var bool $apenasAtivos */ /** @var int $total */ ?>
<div class="page-header">
    <h1>📋 Tipos de Serviços por CNAE</h1>
    <div>
        <a href="cnae_servico_form.php" class="btn btn-primary">➕ Novo Serviço</a>
        <a href="cnae_servicos_listar.php" class="btn">🔄 Limpar filtros</a>
    </div>
</div>

<?php
// Mostra aviso se GLOBALMT tem dados fiscais, com link pras regras
$empresaId = Auth::user()['empresa_id'];
$temDadosFiscais = false;
foreach ($servicos as $s) {
    if (!empty($s['nbs'])) { $temDadosFiscais = true; break; }
}
?>

<?php if ($temDadosFiscais): ?>
<div class="info-box" style="background:#dbeafe; border-left:4px solid #2563eb; padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:13px;">
    <strong>📊 Reforma Tributária (IBS/CBS):</strong>
    Esta empresa tem serviços classificados com NBS + LC 116.
    <a href="ibs_cbs_regras_listar.php" style="color:#2563eb; text-decoration:underline;">Ver regras por grupo</a>
</div>
<?php endif; ?>

<form method="get" class="filters-bar">
    <div class="form-group">
        <label>CNAE</label>
        <select name="cnae">
            <option value="">Todos</option>
            <option value="61.10-8-03" <?= $cnae === '61.10-8-03' ? 'selected' : '' ?>>61.10-8-03 (SCM)</option>
            <option value="62.09-1-00" <?= $cnae === '62.09-1-00' ? 'selected' : '' ?>>62.09-1-00 (TI)</option>
            <option value="63.11-9-00" <?= $cnae === '63.11-9-00' ? 'selected' : '' ?>>63.11-9-00 (Dados)</option>
            <option value="63.99-2-00" <?= $cnae === '63.99-2-00' ? 'selected' : '' ?>>63.99-2-00 (Info)</option>
        </select>
    </div>
    <div class="form-group">
        <label>Categoria</label>
        <select name="categoria">
            <option value="">Todas</option>
            <option value="telecom" <?= $categoria === 'telecom' ? 'selected' : '' ?>>Telecom</option>
            <option value="ti" <?= $categoria === 'ti' ? 'selected' : '' ?>>TI</option>
            <option value="dados" <?= $categoria === 'dados' ? 'selected' : '' ?>>Dados</option>
            <option value="info" <?= $categoria === 'info' ? 'selected' : '' ?>>Info</option>
        </select>
    </div>
    <div class="form-group">
        <label>
            <input type="checkbox" name="apenas_ativos" value="1" <?= $apenasAtivos ? 'checked' : '' ?>>
            Apenas ativos
        </label>
    </div>
    <button type="submit" class="btn btn-primary">Filtrar</button>
</form>

<p style="color: var(--color-text-muted); margin: 12px 0;">
    Total: <strong><?= $total ?></strong> serviço(s)
</p>

<table class="table">
    <thead>
        <tr>
            <th>CNAE</th>
            <th>Categoria</th>
            <th>Código</th>
            <th>Descrição</th>
            <th title="Nomenclatura Brasileira de Serviços">NBS</th>
            <th title="Item da LC 116/2003">LC 116</th>
            <th>Status</th>
            <th style="width: 200px;">Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($servicos)): ?>
            <tr><td colspan="8" class="muted center">Nenhum serviço encontrado.</td></tr>
        <?php else: foreach ($servicos as $s): ?>
            <tr>
                <td><code><?= htmlspecialchars($s['cnae']) ?></code></td>
                <td><span class="badge badge-<?= htmlspecialchars($s['categoria']) ?>"><?= htmlspecialchars($s['categoria']) ?></span></td>
                <td><code><?= htmlspecialchars($s['codigo_servico'] ?? '—') ?></code></td>
                <td>
                    <?= htmlspecialchars($s['descricao']) ?>
                    <?php if (!empty($s['observacoes_fiscais'])): ?>
                        <br><small style="color:var(--color-text-muted); font-style:italic;"><?= htmlspecialchars($s['observacoes_fiscais']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($s['nbs'])): ?>
                        <code style="background:#dbeafe; color:#1e40af; padding:2px 6px; border-radius:4px; font-size:12px;"><?= htmlspecialchars($s['nbs']) ?></code>
                    <?php else: ?>
                        <span style="color:var(--color-text-muted);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($s['lc116_item'])): ?>
                        <code><?= htmlspecialchars($s['lc116_item']) ?></code>
                    <?php else: ?>
                        <span style="color:var(--color-text-muted);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int)$s['ativo'] === 1): ?>
                        <span style="color: var(--color-success);">● Ativo</span>
                    <?php else: ?>
                        <span style="color: var(--color-text-muted);">○ Inativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="cnae_servico_form.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm">✏️</a>
                    <form method="post" action="cnae_servico_acao.php" style="display:inline;" onsubmit="return confirm('Excluir este serviço? Esta ação não pode ser desfeita.');">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                    </form>
                    <form method="post" action="cnae_servico_acao.php" style="display:inline;">
                        <input type="hidden" name="acao" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button type="submit" class="btn btn-sm" title="<?= (int)$s['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>">
                            <?= (int)$s['ativo'] === 1 ? '⏸️' : '▶️' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
