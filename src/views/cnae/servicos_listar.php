<?php /** @var array $servicos */ /** @var string $cnae */ /** @var string $categoria */ /** @var bool $apenasAtivos */ /** @var int $total */ ?>
<div class="page-header">
    <h1>📋 Tipos de Serviços por CNAE</h1>
    <div>
        <a href="cnae_servicos_listar.php" class="btn">🔄 Limpar filtros</a>
    </div>
</div>

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
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($servicos)): ?>
            <tr><td colspan="5" class="muted center">Nenhum serviço encontrado.</td></tr>
        <?php else: foreach ($servicos as $s): ?>
            <tr>
                <td><code><?= htmlspecialchars($s['cnae']) ?></code></td>
                <td><span class="badge badge-<?= htmlspecialchars($s['categoria']) ?>"><?= htmlspecialchars($s['categoria']) ?></span></td>
                <td><code><?= htmlspecialchars($s['codigo_servico'] ?? '—') ?></code></td>
                <td><?= htmlspecialchars($s['descricao']) ?></td>
                <td>
                    <?php if ((int)$s['ativo'] === 1): ?>
                        <span style="color: var(--color-success);">● Ativo</span>
                    <?php else: ?>
                        <span style="color: var(--color-text-muted);">○ Inativo</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
