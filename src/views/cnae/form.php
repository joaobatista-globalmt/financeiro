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

    <fieldset>
        <legend>📋 Identificação</legend>

        <div class="form-group">
            <label for="cnae">CNAE *</label>
            <select id="cnae" name="cnae" required>
                <option value="">Selecione...</option>
                <option value="61.10-8-03" <?= ($servico['cnae'] ?? '') === '61.10-8-03' ? 'selected' : '' ?>>61.10-8-03 — Serviços de Comunicação Multimídia (SCM)</option>
                <option value="62.09-1-00" <?= ($servico['cnae'] ?? '') === '62.09-1-00' ? 'selected' : '' ?>>62.09-1-00 — Suporte técnico e manutenção em TI</option>
                <option value="63.11-9-00" <?= ($servico['cnae'] ?? '') === '63.11-9-00' ? 'selected' : '' ?>>63.11-9-00 — Tratamento de dados, hospedagem e serviços de aplicação</option>
                <option value="63.99-2-00" <?= ($servico['cnae'] ?? '') === '63.99-2-00' ? 'selected' : '' ?>>63.99-2-00 — Serviços de informação diversos</option>
            </select>
        </div>

        <div class="form-group">
            <label for="categoria">Categoria *</label>
            <select id="categoria" name="categoria" required>
                <option value="telecom" <?= ($servico['categoria'] ?? '') === 'telecom' ? 'selected' : '' ?>>Telecom (61.10)</option>
                <option value="ti" <?= ($servico['categoria'] ?? '') === 'ti' ? 'selected' : '' ?>>TI (62.09)</option>
                <option value="dados" <?= ($servico['categoria'] ?? '') === 'dados' ? 'selected' : '' ?>>Dados (63.11)</option>
                <option value="info" <?= ($servico['categoria'] ?? '') === 'info' ? 'selected' : '' ?>>Info (63.99)</option>
            </select>
        </div>

        <div class="form-group">
            <label for="codigo_servico">Código interno</label>
            <input type="text" id="codigo_servico" name="codigo_servico" maxlength="50" value="<?= htmlspecialchars($servico['codigo_servico'] ?? '') ?>" placeholder="Ex: SCM-001">
        </div>

        <div class="form-group">
            <label for="descricao">Descrição *</label>
            <input type="text" id="descricao" name="descricao" maxlength="255" required value="<?= htmlspecialchars($servico['descricao'] ?? '') ?>" placeholder="Ex: Prover acesso à internet">
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" name="ativo" value="1" <?= (int)($servico['ativo'] ?? 1) === 1 ? 'checked' : '' ?>>
                Ativo (aparece nas listagens)
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>📊 Classificação Fiscal (NBS + IBS/CBS)</legend>
        <p style="color: var(--color-text-muted); font-size: 13px; margin: 0 0 12px 0;">
            Conforme Reforma Tributária (EC 132/2023). NBS = Nomenclatura Brasileira de Serviços.
        </p>

        <div class="form-group">
            <label for="nbs">Código NBS</label>
            <input type="text" id="nbs" name="nbs" maxlength="10" pattern="\d{1,2}\.\d{2}\.\d{2}\.\d{2}" value="<?= htmlspecialchars($servico['nbs'] ?? '') ?>" placeholder="Ex: 1.03.02.00">
            <small style="color: var(--color-text-muted);">Formato: G.GG.GG.GG (ex: 1.03.02.00 para "Acesso à internet")</small>
        </div>

        <div class="form-group">
            <label for="lc116_item">Item LC 116/2003</label>
            <input type="text" id="lc116_item" name="lc116_item" maxlength="10" value="<?= htmlspecialchars($servico['lc116_item'] ?? '') ?>" placeholder="Ex: 03.04">
            <small style="color: var(--color-text-muted);">Item da Lista de Serviços do ISS (LC 116/2003)</small>
        </div>

        <div class="form-row" style="display: flex; gap: 16px;">
            <div class="form-group" style="flex: 1;">
                <label for="regime_ibs">Regime IBS/CBS</label>
                <select id="regime_ibs" name="regime_ibs">
                    <option value="normal" <?= ($servico['regime_ibs'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Tributação normal</option>
                    <option value="especifico" <?= ($servico['regime_ibs'] ?? '') === 'especifico' ? 'selected' : '' ?>>Regime específico</option>
                </select>
            </div>

            <div class="form-group" style="flex: 1;">
                <label for="local_operacao">Local da operação</label>
                <select id="local_operacao" name="local_operacao">
                    <option value="tomador" <?= ($servico['local_operacao'] ?? 'tomador') === 'tomador' ? 'selected' : '' ?>>Domicílio do tomador</option>
                    <option value="prestador" <?= ($servico['local_operacao'] ?? '') === 'prestador' ? 'selected' : '' ?>>Domicílio do prestador</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="observacoes_fiscais">Observações fiscais</label>
            <textarea id="observacoes_fiscais" name="observacoes_fiscais" rows="3" maxlength="1000" placeholder="Ex: Item LC 116: 03.04. Tributação normal IBS/CBS."><?= htmlspecialchars($servico['observacoes_fiscais'] ?? '') ?></textarea>
        </div>
    </fieldset>

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
fieldset {
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 20px;
}
legend {
    font-weight: 600;
    color: var(--color-text);
    padding: 0 8px;
}
</style>
