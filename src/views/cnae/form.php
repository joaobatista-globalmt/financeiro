<?php /** @var array $servico */ ?>
<div class="page-header">
    <h1><?= $servico['id'] > 0 ? '✏️ Editar Serviço CNAE' : '➕ Novo Serviço CNAE' ?></h1>
    <div>
        <a href="cnae_servicos_listar.php" class="btn">← Voltar</a>
    </div>
</div>

<form method="post" action="cnae_servico_acao.php" class="form">
    <input type="hidden" name="acao" value="salvar">
    <?php if ($servico['id'] > 0): ?>
        <input type="hidden" name="id" value="<?= (int)$servico['id'] ?>">
    <?php endif; ?>

    <div class="form-group">
        <label for="cnae">CNAE *</label>
        <select id="cnae" name="cnae" required>
            <option value="">Selecione...</option>
            <option value="61.10-8-03" <?= $servico['cnae'] === '61.10-8-03' ? 'selected' : '' ?>>61.10-8-03 — Serviços de Comunicação Multimídia (SCM)</option>
            <option value="62.09-1-00" <?= $servico['cnae'] === '62.09-1-00' ? 'selected' : '' ?>>62.09-1-00 — Suporte técnico e manutenção em TI</option>
            <option value="63.11-9-00" <?= $servico['cnae'] === '63.11-9-00' ? 'selected' : '' ?>>63.11-9-00 — Tratamento de dados, hospedagem e serviços de aplicação</option>
            <option value="63.99-2-00" <?= $servico['cnae'] === '63.99-2-00' ? 'selected' : '' ?>>63.99-2-00 — Serviços de informação diversos</option>
        </select>
        <small style="color: var(--color-text-muted);">Formato: XX.XX-X-XX (Classe-Subclasse-Denominação)</small>
    </div>

    <div class="form-group">
        <label for="categoria">Categoria *</label>
        <select id="categoria" name="categoria" required>
            <option value="telecom" <?= $servico['categoria'] === 'telecom' ? 'selected' : '' ?>>Telecom (61.10)</option>
            <option value="ti" <?= $servico['categoria'] === 'ti' ? 'selected' : '' ?>>TI (62.09)</option>
            <option value="dados" <?= $servico['categoria'] === 'dados' ? 'selected' : '' ?>>Dados (63.11)</option>
            <option value="info" <?= $servico['categoria'] === 'info' ? 'selected' : '' ?>>Info (63.99)</option>
        </select>
        <small style="color: var(--color-text-muted);">Categoria fiscal (normalmente derivada do CNAE)</small>
    </div>

    <div class="form-group">
        <label for="codigo_servico">Código interno</label>
        <input type="text" id="codigo_servico" name="codigo_servico" maxlength="50" value="<?= htmlspecialchars($servico['codigo_servico'] ?? '') ?>" placeholder="Ex: SCM-001">
        <small style="color: var(--color-text-muted);">Opcional. Código próprio pra identificar o serviço internamente.</small>
    </div>

    <div class="form-group">
        <label for="descricao">Descrição *</label>
        <input type="text" id="descricao" name="descricao" maxlength="255" required value="<?= htmlspecialchars($servico['descricao'] ?? '') ?>" placeholder="Ex: Prover acesso à internet">
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativo" value="1" <?= (int)$servico['ativo'] === 1 ? 'checked' : '' ?>>
            Ativo (aparece nas listagens)
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Salvar</button>
        <a href="cnae_servicos_listar.php" class="btn">Cancelar</a>
    </div>
</form>

<style>
.form-actions {
    display: flex;
    gap: 8px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
}
</style>
