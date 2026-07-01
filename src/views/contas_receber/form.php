<?php
/** @var array|null $conta */
/** @var array $clientes */
/** @var array $categorias */
/** @var array $contasBanco */

$status = $conta['status'] ?? 'pendente';
$ehRecebido = $status === 'recebida';
?>
<div class="page-header">
    <h1><?= $conta ? 'Editar Conta a Receber' : 'Nova Conta a Receber' ?></h1>
    <a href="contas_receber.php" class="btn">Voltar</a>
</div>

<form method="post" action="conta_receber_salvar.php" class="form">
    <input type="hidden" name="id" value="<?= (int)($conta['id'] ?? 0) ?>">

    <div class="row">
        <div class="form-group col-6">
            <label>Cliente *</label>
            <div class="input-group">
                <select name="cliente_id" id="select-cliente" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($conta['cliente_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (Permissao::tem('gerenciar_cadastros')): ?>
                    <a href="cliente_form.php?return=conta_receber_form&amp;select=cliente_id" target="_blank" class="btn-icon" title="Cadastrar novo cliente (abre em nova janela)">+</a>
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
                    <a href="categoria_form.php?return=conta_receber_form&amp;select=categoria_id&amp;tipo=receita" target="_blank" class="btn-icon" title="Cadastrar nova categoria (abre em nova janela)">+</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="form-group col-8">
            <label>Descrição *</label>
            <input type="text" name="descricao" required maxlength="255"
                   value="<?= htmlspecialchars($conta['descricao'] ?? '') ?>"
                   placeholder="Ex: Mensalidade junho, Venda produto X...">
        </div>
        <div class="form-group col-4">
            <label>Nº do Documento</label>
            <input type="text" name="numero_documento" maxlength="100"
                   value="<?= htmlspecialchars($conta['numero_documento'] ?? '') ?>">
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
        </div>
    </div>

    <div class="form-group col-6">
        <label>Forma de Recebimento</label>
        <select name="forma_recebimento" id="select-forma-recebimento">
            <?php
            $fp = $conta['forma_recebimento'] ?? 'boleto';
            $formas = ['boleto','pix','transferencia','dinheiro','cartao','cheque','deposito','outros'];
            foreach ($formas as $f): ?>
                <option value="<?= $f ?>" <?= $fp === $f ? 'selected' : '' ?>><?= ucfirst($f) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group col-6" id="pix-info-box" style="display: none;">
        <label>💠 Chave PIX do Cliente</label>
        <div class="input-group">
            <input type="text" id="pix-chave-display" readonly
                   placeholder="Selecione um cliente com chave PIX cadastrada"
                   style="background: #f0fdf4; font-family: monospace;">
            <button type="button" class="btn-icon" id="btn-copiar-pix" title="Copiar chave PIX" style="background: #16a34a;">📋</button>
        </div>
        <small class="muted" id="pix-tipo-label"></small>
    </div>

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($conta['observacoes'] ?? '') ?></textarea>
    </div>

    <?php if ($conta && in_array($status, ['pendente', 'aprovada'], true) && Permissao::tem('receber')): ?>
    <fieldset id="receber" class="payment-section">
        <legend>💰 Registrar Recebimento</legend>
        <div class="row">
            <div class="form-group col-4">
                <label>Conta Bancária *</label>
                <select name="conta_bancaria_id" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($contasBanco as $cb): ?>
                        <option value="<?= (int)$cb['id'] ?>">
                            <?= htmlspecialchars($cb['descricao']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-4">
                <label>Data Recebimento *</label>
                <input type="date" name="data_recebimento" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group col-4">
                <label>Valor Recebido *</label>
                <input type="number" step="0.01" name="valor_recebido" value="<?= htmlspecialchars($conta['valor']) ?>" required>
            </div>
        </div>
    </fieldset>
    <?php endif; ?>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="contas_receber.php" class="btn">Cancelar</a>
    </div>
</form>

<?php if ($ehRecebido): ?>
<div class="alert alert-info">
    <strong>Conta recebida em <?= dataIsoParaBr($conta['data_recebimento']) ?>.</strong>
    Valor: R$ <?= number_format((float)$conta['valor_recebido'], 2, ',', '.') ?>.
</div>
<?php endif; ?>

<?php
// === Listener de postMessage + storage para criação rápida via janela filha ===
// Quando o usuário cadastra cliente/categoria em uma janela aberta via target="_blank",
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
    var jaProcessados = {};

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

        var toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;top:16px;right:16px;z-index:10000;padding:12px 18px;border-radius:8px;color:white;font-size:14px;font-weight:500;background:#16a34a;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:rapido-toast-in 0.2s ease-out;';
        var tipoLabel = d.tipo.charAt(0).toUpperCase() + d.tipo.slice(1);
        toast.textContent = tipoLabel + ' "' + d.label + '" cadastrado e selecionado!';
        document.body.appendChild(toast);
        setTimeout(function () { toast.style.transition = 'opacity 0.3s'; toast.style.opacity = '0'; setTimeout(function () { toast.remove(); }, 300); }, 3500);

        try { sel.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) { /* ignore */ }
    }

    window.addEventListener('message', function (ev) {
        processarPayload(ev.data);
    });

    window.addEventListener('storage', function (ev) {
        if (ev.key !== STORAGE_KEY || !ev.newValue) return;
        try {
            var d = JSON.parse(ev.newValue);
            processarPayload(d);
        } catch (e) { /* ignore */ }
    });

    // Polling fallback (cobre bugs raros onde storage event não dispara)
    var pollingTentativas = 10;
    var pollingTimer = setInterval(function () {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                var d = JSON.parse(raw);
                processarPayload(d);
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
// Mapa JS de clientes para acesso rápido (id -> {razao_social, pix_chave, pix_tipo})
$clientesMap = [];
foreach ($clientes as $c) {
    $clientesMap[(int)$c['id']] = [
        'razao_social' => $c['razao_social'],
        'pix_chave'    => $c['pix_chave'] ?? null,
        'pix_tipo'     => $c['pix_tipo'] ?? null,
    ];
}
?>
<script>
// Mapa de clientes injetado pelo PHP
window.__clientesMap = <?= json_encode($clientesMap, JSON_UNESCAPED_UNICODE) ?>;

(function () {
    var selCliente = document.getElementById('select-cliente');
    var selForma = document.getElementById('select-forma-recebimento');
    var pixBox = document.getElementById('pix-info-box');
    var pixChaveDisplay = document.getElementById('pix-chave-display');
    var pixTipoLabel = document.getElementById('pix-tipo-label');
    var btnCopiar = document.getElementById('btn-copiar-pix');

    if (!selCliente || !selForma || !pixBox) return;

    var TIPOS_PIX = {
        cpf: 'CPF', cnpj: 'CNPJ', email: 'E-mail',
        telefone: 'Telefone', aleatoria: 'Chave aleatória'
    };

    function atualizarPixBox() {
        var id = parseInt(selCliente.value || '0', 10);
        var forma = selForma.value;
        var dados = window.__clientesMap && window.__clientesMap[id];
        var chave = dados ? dados.pix_chave : null;
        var tipo  = dados ? dados.pix_tipo : null;

        if (forma === 'pix' && chave) {
            pixChaveDisplay.value = chave;
            pixTipoLabel.textContent = 'Tipo: ' + (TIPOS_PIX[tipo] || tipo || 'não especificado');
            pixBox.style.display = '';
        } else if (forma === 'pix') {
            pixChaveDisplay.value = '';
            pixTipoLabel.textContent = '⚠️ Este cliente não tem chave PIX cadastrada.';
            pixBox.style.display = '';
        } else {
            pixBox.style.display = 'none';
        }
    }

    selCliente.addEventListener('change', atualizarPixBox);
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