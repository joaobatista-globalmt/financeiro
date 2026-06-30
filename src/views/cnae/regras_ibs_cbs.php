<?php /** @var array $regras */ ?>
<div class="page-header">
    <h1>📊 Regras IBS/CBS — Reforma Tributária</h1>
    <div>
        <a href="cnae_servicos_listar.php" class="btn">← Voltar para Serviços</a>
    </div>
</div>

<div class="info-box" style="background:#fef3c7; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:6px; margin-bottom:20px; font-size:13px;">
    <strong>ℹ️ Sobre a Reforma Tributária:</strong>
    O IBS (Imposto sobre Bens e Serviços estadual/municipal) e a CBS (Contribuição sobre Bens e Serviços federal)
    substituem PIS/COFINS/ICMS/ISS conforme a <strong>EC 132/2023</strong>. As alíquotas serão definidas pelo
    Comitê Gestor do IBS/CBS. Esta tabela mostra o regime aplicável aos serviços do sistema.
</div>

<div class="regras-grid">
    <?php foreach ($regras as $r): ?>
        <div class="regra-card">
            <div class="regra-header">
                <div class="regra-grupo"><?= htmlspecialchars($r['nbs_grupo']) ?></div>
                <h3><?= htmlspecialchars($r['descricao']) ?></h3>
            </div>
            <div class="regra-body">
                <div class="regra-row">
                    <span class="regra-label">CNAE principal</span>
                    <code><?= htmlspecialchars($r['cnae_principal'] ?? '—') ?></code>
                </div>
                <div class="regra-row">
                    <span class="regra-label">Item LC 116</span>
                    <code><?= htmlspecialchars($r['lc116_item'] ?? '—') ?></code>
                </div>
                <div class="regra-row">
                    <span class="regra-label">Regime</span>
                    <span class="badge badge-<?= $r['regime'] === 'normal' ? 'success' : 'warning' ?>">
                        <?= $r['regime'] === 'normal' ? 'Tributação normal' : 'Regime específico' ?>
                    </span>
                </div>
                <div class="regra-row">
                    <span class="regra-label">Local da operação</span>
                    <strong><?= $r['local_operacao'] === 'tomador' ? 'Domicílio do tomador' : 'Domicílio do prestador' ?></strong>
                </div>
                <div class="regra-row">
                    <span class="regra-label">Alíquota</span>
                    <strong><?= $r['aliquota_padrao'] !== null ? number_format((float)$r['aliquota_padrao'], 2, ',', '.') . '%' : 'Definida pelo Comitê Gestor' ?></strong>
                </div>
                <?php if (!empty($r['observacoes'])): ?>
                <div class="regra-obs">
                    <?= htmlspecialchars($r['observacoes']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<style>
.regras-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
    gap: 16px;
    margin-top: 20px;
}
.regra-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.regra-header {
    background: linear-gradient(135deg, #2563eb, #1e40af);
    color: white;
    padding: 16px 20px;
}
.regra-grupo {
    font-size: 24px;
    font-weight: 800;
    font-family: monospace;
    margin-bottom: 4px;
}
.regra-header h3 {
    font-size: 14px;
    font-weight: 500;
    margin: 0;
    opacity: 0.95;
}
.regra-body {
    padding: 16px 20px;
}
.regra-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--color-border);
    font-size: 13px;
}
.regra-row:last-of-type {
    border-bottom: none;
}
.regra-label {
    color: var(--color-text-muted);
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
    font-weight: 600;
}
.regra-obs {
    margin-top: 12px;
    padding: 10px 12px;
    background: #f8fafc;
    border-left: 3px solid #94a3b8;
    font-size: 12px;
    color: var(--color-text);
    line-height: 1.5;
    border-radius: 4px;
}
</style>
