<?php
/** @var array|null $conta */
/** @var array $fornecedores */
/** @var array $categorias */
/** @var array $contasBanco */

$status = $conta['status'] ?? 'pendente';
$ehPago = $status === 'paga';
?>
<div class="page-header">
    <h1><?= $conta ? 'Editar Conta a Pagar' : 'Nova Conta a Pagar' ?></h1>
    <a href="contas_pagar.php" class="btn">Voltar</a>
</div>

<form method="post" action="conta_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($conta['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-6">
            <label>Fornecedor *</label>
            <div class="input-group">
                <select name="fornecedor_id" id="select-fornecedor" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($fornecedores as $f): ?>
                        <option value="<?= (int)$f['id'] ?>" <?= ($conta['fornecedor_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($f['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (Permissao::tem('gerenciar_cadastros')): ?>
                    <a href="fornecedor_form.php?return=conta_form&amp;select=fornecedor_id" target="_blank" class="btn-icon" title="Cadastrar novo fornecedor (abre em nova janela)">+</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group col-6">
            <label>Categoria *</label>
            <div class="input-group">
                <select name="categoria_id" id="select-categoria" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= ($conta['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (Permissao::tem('gerenciar_cadastros')): ?>
                    <a href="categoria_form.php?return=conta_form&amp;select=categoria_id&amp;tipo=despesa" target="_blank" class="btn-icon" title="Cadastrar nova categoria (abre em nova janela)">+</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-8">
            <label>Descrição *</label>
            <input type="text" name="descricao" required maxlength="255"
                   value="<?= htmlspecialchars($conta['descricao'] ?? '') ?>"
                   placeholder="Ex: Aluguel junho, Energia Elétrica...">
        </div>
        <div class="form-group col-4">
            <label>Nº do Documento</label>
            <input type="text" name="numero_documento" maxlength="100"
                   value="<?= htmlspecialchars($conta['numero_documento'] ?? '') ?>"
                   placeholder="Ex: 12345, NF-001">
        </div>
    </div>

    <div class="row">
        <div class="form-group col-3">
            <label>Valor *</label>
            <input type="number" step="0.01" min="0.01" name="valor" required
                   value="<?= htmlspecialchars($conta['valor'] ?? '') ?>">
        </div>
        <div class="form-group col-3">
            <label>Data Emissão</label>
            <input type="date" name="data_emissao"
                   value="<?= htmlspecialchars($conta['data_emissao'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group col-3">
            <label>Vencimento *</label>
            <input type="date" name="data_vencimento" required
                   value="<?= htmlspecialchars($conta['data_vencimento'] ?? '') ?>">
        </div>
        <div class="form-group col-3">
            <label>Parcelas</label>
            <input type="number" min="1" max="48" name="parcelas"
                   value="<?= htmlspecialchars($conta['parcelas'] ?? 1) ?>"
                   <?= $conta ? 'readonly' : '' ?>>
            <?php if (!$conta): ?><small class="muted">1 = à vista</small><?php endif; ?>
        </div>
    </div>

    <div class="form-group col-6">
        <label>Forma de Pagamento</label>
        <select name="forma_pagamento" id="select-forma-pagamento">
            <?php
            $fp = $conta['forma_pagamento'] ?? 'boleto';
            $formas = ['boleto','pix','transferencia','dinheiro','cartao','cheque','outros'];
            foreach ($formas as $f): ?>
                <option value="<?= $f ?>" <?= $fp === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group col-6" id="pix-info-box" style="display: none;">
        <label>💠 Chave PIX do Fornecedor</label>
        <div class="input-group">
            <input type="text" id="pix-chave-display" readonly
                   placeholder="Selecione um fornecedor com chave PIX cadastrada"
                   style="background: #f0fdf4; font-family: monospace;">
            <button type="button" class="btn-icon" id="btn-copiar-pix" title="Copiar chave PIX" style="background: #16a34a;">📋</button>
        </div>
        <small class="muted" id="pix-tipo-label"></small>
    </div>

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($conta['observacoes'] ?? '') ?></textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <?php if ($conta && Permissao::tem('excluir') && $status !== 'paga'): ?>
            <button type="submit" formaction="conta_acao.php" formmethod="post"
                    onclick="return confirm('ATENÇÃO: Excluir DEFINITIVAMENTE esta conta? Não dá pra desfazer.\n\nSó é possível se ela ainda não tiver sido paga nem for pai de parcelas.')"
                    class="btn btn-danger">🗑️ Excluir</button>
        <?php endif; ?>
        <a href="contas_pagar.php" class="btn">Cancelar</a>
    </div>
</form>

<?php if ($conta && in_array($status, ['pendente', 'aprovada'], true) && Permissao::tem('pagar')): ?>
<!-- Form SEPARADO para registrar pagamento (não conflita com edição da conta) -->
<form method="post" action="conta_acao.php" class="form payment-form" style="margin-top: 24px; border-top: 2px dashed #cbd5e1; padding-top: 20px;">
    <input type="hidden" name="id" value="<?= (int)$conta['id'] ?>">
    <input type="hidden" name="acao" value="pagar">

    <fieldset id="pagar" class="payment-section">
        <legend>💰 Registrar Pagamento</legend>
        <div class="row">
            <div class="form-group col-4">
                <label>Conta Bancária *</label>
                <select name="conta_bancaria_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($contasBanco as $cb): ?>
                        <option value="<?= (int)$cb['id'] ?>">
                            <?= htmlspecialchars($cb['descricao']) ?>
                            (R$ <?= number_format(ContasBancariasController::calcularSaldo((int)$cb['id']), 2, ',', '.') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-4">
                <label>Data Pagamento *</label>
                <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group col-4">
                <label>Valor Pago *</label>
                <input type="number" step="0.01" name="valor_pago" value="<?= htmlspecialchars($conta['valor']) ?>" required>
            </div>
        </div>
    </fieldset>
    <div class="form-actions">
        <button type="submit" class="btn btn-success">💰 Confirmar Pagamento</button>
    </div>
</form>
<?php endif; ?>

<?php if ($ehPago): ?>
<div class="alert alert-info">
    <strong>Conta paga em <?= dataIsoParaBr($conta['data_pagamento']) ?>.</strong>
    Valor pago: R$ <?= number_format((float)$conta['valor_pago'], 2, ',', '.') ?>.
    <?php if (!empty($conta['conta_bancaria_descricao'])): ?>
        Lançada em: <?= htmlspecialchars($conta['conta_bancaria_descricao']) ?>.
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
// === Listener de postMessage + storage para criação rápida via janela filha ===
// Quando o usuário cadastra fornecedor/cliente/categoria em uma janela aberta via target="_blank",
// a view intermediária _criar_filho_sucesso.php:
//   1) Grava no localStorage da janela pai com chave 'rapido_novo_cadastro'
//   2) Manda postMessage com o mesmo payload
// Aqui escutamos AMBOS (redundância) e inserimos a <option> no <select> correto,
// mantendo a janela pai (com os dados já digitados) intacta — sem reload.

$selectsValidos = ['fornecedor_id', 'categoria_id', 'cliente_id'];
?>
<script>
(function () {
    if (window.__rapidoListenerInstalado) return;
    window.__rapidoListenerInstalado = true;

    var selectsValidos = <?= json_encode($selectsValidos) ?>;
    var STORAGE_KEY = 'rapido_novo_cadastro';
    var jaProcessados = {}; // _ts -> true, evita processar o mesmo evento 2x

    function processarPayload(d) {
        if (!d || !d.tipo || !d.select || !d.id || !d.label) return;
        if (selectsValidos.indexOf(d.select) === -1) return;
        if (d._ts && jaProcessados[d._ts]) return;
        if (d._ts) jaProcessados[d._ts] = true;

        var sel = document.querySelector('select[name="' + d.select + '"]');
        if (!sel) return;

        var idStr = String(d.id);
        var exists = Array.from(sel.options).some(function (o) { return o.value === idStr; });
        if (!exists) {
            var opt = document.createElement('option');
            opt.value = idStr;
            opt.textContent = d.label;
            opt.selected = true;
            sel.appendChild(opt);
        }
        sel.value = idStr;
        sel.dispatchEvent(new Event('change', { bubbles: true }));

        // Toast verde de feedback
        var toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;top:16px;right:16px;z-index:10000;padding:12px 18px;border-radius:8px;color:white;font-size:14px;font-weight:500;background:#16a34a;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:rapido-toast-in 0.2s ease-out;';
        var tipoLabel = d.tipo.charAt(0).toUpperCase() + d.tipo.slice(1);
        toast.textContent = tipoLabel + ' "' + d.label + '" cadastrado e selecionado!';
        document.body.appendChild(toast);
        setTimeout(function () { toast.style.transition = 'opacity 0.3s'; toast.style.opacity = '0'; setTimeout(function () { toast.remove(); }, 300); }, 3500);

        try { sel.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) { /* ignore */ }
    }

    // === Listener 1: postMessage (caso o JS da janela filha rode antes de fechar) ===
    window.addEventListener('message', function (ev) {
        processarPayload(ev.data);
    });

    // === Listener 2: storage event (dispara cross-window quando localStorage muda) ===
    window.addEventListener('storage', function (ev) {
        if (ev.key !== STORAGE_KEY || !ev.newValue) return;
        try {
            var d = JSON.parse(ev.newValue);
            processarPayload(d);
        } catch (e) { /* ignore */ }
    });

    // === Listener 3: polling no localStorage a cada 500ms por 5s após carregar a página ===
    //    (cobre o caso raro onde o storage event não dispara por bug do navegador)
    var pollingTentativas = 10;
    var pollingTimer = setInterval(function () {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                var d = JSON.parse(raw);
                processarPayload(d);
                // Limpa pra não processar de novo no próximo tick
                localStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) { /* ignore */ }
        pollingTentativas--;
        if (pollingTentativas <= 0) clearInterval(pollingTimer);
    }, 500);
})();
</script>
<style>
@keyframes rapido-toast-in { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
</style>

<?php
// Mapa JS de fornecedores para acesso rápido (id -> {razao_social, pix_chave, pix_tipo})
$fornecedoresMap = [];
foreach ($fornecedores as $f) {
    $fornecedoresMap[(int)$f['id']] = [
        'razao_social' => $f['razao_social'],
        'pix_chave'    => $f['pix_chave'] ?? null,
        'pix_tipo'     => $f['pix_tipo'] ?? null,
    ];
}
?>
<script>
// Mapa de fornecedores injetado pelo PHP
window.__fornecedoresMap = <?= json_encode($fornecedoresMap, JSON_UNESCAPED_UNICODE) ?>;

(function () {
    var selFornecedor = document.getElementById('select-fornecedor');
    var selForma = document.getElementById('select-forma-pagamento');
    var pixBox = document.getElementById('pix-info-box');
    var pixChaveDisplay = document.getElementById('pix-chave-display');
    var pixTipoLabel = document.getElementById('pix-tipo-label');
    var btnCopiar = document.getElementById('btn-copiar-pix');

    if (!selFornecedor || !selForma || !pixBox) return;

    var TIPOS_PIX = {
        cpf: 'CPF', cnpj: 'CNPJ', email: 'E-mail',
        telefone: 'Telefone', aleatoria: 'Chave aleatória'
    };

    function atualizarPixBox() {
        var id = parseInt(selFornecedor.value || '0', 10);
        var forma = selForma.value;
        var dados = window.__fornecedoresMap && window.__fornecedoresMap[id];
        var chave = dados ? dados.pix_chave : null;
        var tipo  = dados ? dados.pix_tipo : null;

        if (forma === 'pix' && chave) {
            pixChaveDisplay.value = chave;
            pixTipoLabel.textContent = 'Tipo: ' + (TIPOS_PIX[tipo] || tipo || 'não especificado');
            pixBox.style.display = '';
        } else if (forma === 'pix') {
            pixChaveDisplay.value = '';
            pixTipoLabel.textContent = '⚠️ Este fornecedor não tem chave PIX cadastrada.';
            pixBox.style.display = '';
        } else {
            pixBox.style.display = 'none';
        }
    }

    selFornecedor.addEventListener('change', atualizarPixBox);
    selForma.addEventListener('change', atualizarPixBox);
    if (btnCopiar) {
        btnCopiar.addEventListener('click', function () {
            if (!pixChaveDisplay.value) return;
            pixChaveDisplay.select();
            try {
                navigator.clipboard.writeText(pixChaveDisplay.value).then(function () {
                    var orig = btnCopiar.textContent;
                    btnCopiar.textContent = '✓';
                    setTimeout(function () { btnCopiar.textContent = orig; }, 1500);
                });
            } catch (e) {
                document.execCommand('copy');
            }
        });
    }
    atualizarPixBox();
})();
</script>