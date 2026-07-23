<?php /** @var array|null $cliente */ ?>
<?php
// Carrega e-mails NFSe/Boleto se for edição
$emailsNfse = [];
$emailsBoleto = [];
if ($cliente) {
    $db = \Database::getConnection();
    $s = $db->prepare('SELECT email FROM cliente_emails_nfse WHERE cliente_id = ? ORDER BY id');
    $s->execute([$cliente['id']]);
    $emailsNfse = array_column($s->fetchAll(PDO::FETCH_ASSOC), 'email');
    $s = $db->prepare('SELECT email FROM cliente_emails_boleto WHERE cliente_id = ? ORDER BY id');
    $s->execute([$cliente['id']]);
    $emailsBoleto = array_column($s->fetchAll(PDO::FETCH_ASSOC), 'email');
}

// Suporte a retorno para tela de origem (ex: conta_receber_form.php) com seleção automática
// Aceita nomes como "conta_form", "conta_receber_form", "clientes" (com ou sem .php)
$returnTo     = preg_match('/^[a-z0-9_]+$/', (string)($_GET['return'] ?? '')) ? $_GET['return'] : '';
$returnSelect = preg_match('/^[a-z0-9_]+$/', (string)($_GET['select'] ?? '')) ? $_GET['select'] : '';

// Propaga ?return=...&select=... no action do form
$actionForm = 'cliente_salvar.php' . ($returnTo ? '?return=' . rawurlencode($returnTo) . '&select=' . rawurlencode($returnSelect) : '');
?>
<div class="page-header">
    <h1><?= $cliente ? 'Editar' : 'Novo' ?> Cliente</h1>
    <a href="<?= $returnTo ?: 'clientes.php' ?>" class="btn">Voltar</a>
</div>

<?php if ($returnTo): ?>
    <div class="alert alert-info" style="margin-bottom:12px;">
        <strong>Modo criação rápida.</strong> Ao salvar, você voltará automaticamente para a tela de origem com este cliente selecionado.
    </div>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($actionForm) ?>" class="form" id="cliente-form">
    <input type="hidden" name="id" value="<?= (int)($cliente['id'] ?? 0) ?>">

<!-- (Fieldset "Identificacao" removido em 2026-07-22 - duplicado.
     O correto eh o que esta dentro da ABA 1: DADOS (linha ~88).
     O primeiro fieldset recebia os dados da BrasilAPI mas o navegado
     submetia o DOIS input[name=razao_social] e o PHP pegava o ultimo (vazio),
     causando "salva mas sem dados".) -->

<!-- ============================================
     SISTEMA DE ABAS (FICHARIO) - 6 abas
     ============================================ -->
<nav class="tabs-nav" role="tablist">
    <button type="button" class="tab-button active" data-tab="dados" role="tab">📋 Dados</button>
    <button type="button" class="tab-button" data-tab="endereco" role="tab">📍 Endereço</button>
    <button type="button" class="tab-button" data-tab="vencimento" role="tab">💰 Vencimento & Docs</button>
    <button type="button" class="tab-button" data-tab="pix" role="tab">💠 PIX</button>
    <button type="button" class="tab-button" data-tab="servicos" role="tab">🧾 Serviços <span id="servicos-count" class="badge">0</span></button>
    <button type="button" class="tab-button" data-tab="fiscais" role="tab">⚙️ Fiscais (NFSe)</button>
</nav>

<!-- ABA 1: DADOS -->
<div class="tab-content active" data-tab-content="dados" role="tabpanel">
<fieldset>
        <legend>📋 Identificação</legend>
        <div class="row">
            <div class="form-group col-8">
                <label>Razão Social / Nome *</label>
                <input type="text" name="razao_social" required maxlength="200"
                       value="<?= htmlspecialchars($cliente['razao_social'] ?? '') ?>">
            </div>
            <div class="form-group col-4">
                <label>Tipo de Pessoa *</label>
                <select name="tipo_pessoa" required onchange="var d=document.getElementById('cpf_cnpj');if(d){d.value='';d.placeholder=(this.value==='F'?'000.000.000-00':'00.000.000/0000-00');d.maxLength=(this.value==='F'?14:18);}">
                    <?php $tp = $cliente['tipo_pessoa'] ?? 'J'; ?>
                    <option value="J" <?= $tp === 'J' ? 'selected' : '' ?>>Jurídica (CNPJ)</option>
                    <option value="F" <?= $tp === 'F' ? 'selected' : '' ?>>Física (CPF)</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="form-group col-8">
                <label>Nome Fantasia / Apelido</label>
                <input type="text" name="nome_fantasia" maxlength="100"
                       value="<?= htmlspecialchars($cliente['nome_fantasia'] ?? '') ?>">
            </div>
            <div class="form-group col-4">
                <label>CPF/CNPJ</label>
                <input type="text" id="cpf_cnpj" name="cpf_cnpj" maxlength="18" autocomplete="off"
                       inputmode="numeric"
                       placeholder="00.000.000/0000-00"
                      
                       value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>">
            </div>
        </div>
    </fieldset>
</div>

<!-- ABA 2: ENDERECO -->
<div class="tab-content" data-tab-content="endereco" role="tabpanel">
<fieldset>
        <legend>📍 Endereço & Contato</legend>
        <div class="row">
            <div class="form-group col-3">
                <label>CEP</label>
                <input type="text" name="cep" maxlength="10"
                       value="<?= htmlspecialchars($cliente['cep'] ?? '') ?>">
            </div>
            <div class="form-group col-7">
                <label>Endereço</label>
                <input type="text" name="endereco" maxlength="255"
                       value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
            </div>
            <div class="form-group col-2">
                <label>UF</label>
                <input type="text" name="uf" maxlength="2"
                       value="<?= htmlspecialchars($cliente['uf'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div class="form-group col-8">
                <label>Cidade</label>
                <input type="text" name="cidade" maxlength="100"
                       value="<?= htmlspecialchars($cliente['cidade'] ?? '') ?>">
            </div>
            <div class="form-group col-4">
                <label>Telefone</label>
                <input type="text" name="telefone" maxlength="20"
                       value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
            </div>
        </div>

        <!-- Google Maps: botao + campo pra colar URL -->
        <div class="row" style="background: #f0f9ff; padding: 12px; border-radius: 6px; margin-top: 12px;">
            <div class="form-group col-12" style="margin-bottom: 8px;">
                <button type="button" id="btn-abrir-maps" class="btn btn-primary">
                    📍 Abrir no Google Maps
                </button>
                <small style="color: var(--color-text-muted); margin-left: 12px;">
                    Abre o endereco acima no Google Maps (nova aba).
                </small>
            </div>
            <div class="form-group col-12" style="margin-bottom: 8px;">
                <label>🔗 Cole a URL do Google Maps aqui:</label>
                <div style="display: flex; gap: 8px;">
                    <input type="url" id="endereco_maps_input" placeholder="https://www.google.com/maps/place/..."
                           style="flex: 1;">
                    <button type="button" id="btn-salvar-link-maps" class="btn">💾 Salvar Link</button>
                </div>
            </div>
            <div class="form-group col-12" style="margin-bottom: 0;">
                <label>📌 Endereço do Google Maps salvo:</label>
                <div id="endereco_maps_display" style="background: #fff; color: #666; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 4px; min-height: 36px; display: flex; align-items: center;">
                <?php if (!empty($cliente['endereco_maps'])): ?>
                    <a href="<?= htmlspecialchars($cliente['endereco_maps']) ?>" target="_blank" rel="noopener noreferrer" style="color: #1e40af; text-decoration: none; font-weight: 500;">
                        🔗 <?= htmlspecialchars($cliente['endereco_maps']) ?>
                    </a>
                <?php else: ?>
                    <em style="color: #999;">(nenhum link salvo ainda)</em>
                <?php endif; ?>
                </div>
                <input type="hidden" name="endereco_maps" id="endereco_maps_hidden"
                       value="<?= htmlspecialchars($cliente['endereco_maps'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div class="form-group col-6">
                <label>E-mail principal (legado)</label>
                <input type="email" name="email" maxlength="150"
                       value="<?= htmlspecialchars($cliente['email'] ?? '') ?>"
                       placeholder="Mantido por compatibilidade">
                <small style="color: var(--color-text-muted);">Use os campos acima para gerenciar e-mails por tipo de documento.</small>
            </div>
            <div class="form-group col-6">
                <label>Contato</label>
                <input type="text" name="contato" maxlength="100"
                       value="<?= htmlspecialchars($cliente['contato'] ?? '') ?>">
            </div>
        </div>
    </fieldset>
</div>

<!-- ABA 3: VENCIMENTO & DOCS -->
<div class="tab-content" data-tab-content="vencimento" role="tabpanel">
<fieldset>
        <legend>📅 Vencimento padrão</legend>
        <div class="row">
            <div class="form-group col-4">
                <label>Dia do vencimento</label>
                <input type="number" name="dia_vencimento" min="1" max="31"
                       value="<?= htmlspecialchars($cliente['dia_vencimento'] ?? '') ?>"
                       placeholder="Ex: 5, 10, 15...">
                <small style="color: var(--color-text-muted);">1-31. Vazio = sem dia fixo.</small>
            </div>
            <div class="form-group col-8">
                <label>Tipo de vencimento</label>
                <?php $tv = $cliente['tipo_vencimento'] ?? ''; ?>
                <select name="tipo_vencimento">
                    <option value="" <?= $tv === '' ? 'selected' : '' ?>>— Não definido —</option>
                    <option value="mes_corrente" <?= $tv === 'mes_corrente' ? 'selected' : '' ?>>No mês corrente (ex: dia 5 = mês corrente)</option>
                    <option value="mes_seguinte" <?= $tv === 'mes_seguinte' ? 'selected' : '' ?>>No mês seguinte (ex: dia 5 = próximo mês)</option>
                </select>
                <small style="color: var(--color-text-muted);">Define se a fatura vence no mesmo mês do serviço ou no seguinte.</small>
            </div>
        </div>
    </fieldset>
<fieldset>
        <legend>📑 Documentos fiscais</legend>
        <div class="form-group">
            <label>
                <input type="checkbox" name="emite_nfse" value="1" <?= (!$cliente || (int)$cliente['emite_nfse'] === 1) ? 'checked' : '' ?>>
                <strong>Emite NFS-e</strong> (Nota Fiscal de Serviços Eletrônica)
            </label>
            <label style="margin-left: 24px;">
                <input type="checkbox" name="emite_boleto" value="1" <?= (!$cliente || (int)$cliente['emite_boleto'] === 1) ? 'checked' : '' ?>>
                <strong>Emite boleto</strong>
            </label>
        </div>
    </fieldset>
<fieldset>
        <legend>📧 E-mails para envio</legend>
        <p style="color: var(--color-text-muted); font-size: 13px; margin: 0 0 12px 0;">
            <strong>NFS-e</strong> e <strong>Boleto</strong> podem ter listas de e-mails diferentes. Clique em "+ Adicionar" para incluir mais.
        </p>

        <div class="form-group">
            <label style="color: #1e40af; font-weight: 600;">📄 E-mails para NFS-e</label>
            <div id="emails-nfse-list" class="emails-list">
                <?php foreach ($emailsNfse as $email): ?>
                <div class="email-item">
                    <input type="email" name="emails_nfse[]" value="<?= htmlspecialchars($email) ?>" placeholder="exemplo@cliente.com">
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm" onclick="addEmail('nfse')">+ Adicionar e-mail NFSe</button>
        </div>

        <div class="form-group">
            <label style="color: #1e40af; font-weight: 600;">💰 E-mails para Boleto</label>
            <div id="emails-boleto-list" class="emails-list">
                <?php foreach ($emailsBoleto as $email): ?>
                <div class="email-item">
                    <input type="email" name="emails_boleto[]" value="<?= htmlspecialchars($email) ?>" placeholder="exemplo@cliente.com">
                    <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm" onclick="addEmail('boleto')">+ Adicionar e-mail Boleto</button>
        </div>
    </fieldset>
</div>

<!-- ABA 4: PIX -->
<div class="tab-content" data-tab-content="pix" role="tabpanel">
<fieldset class="form-section">
        <legend>💠 Chave PIX</legend>
        <div class="row">
            <div class="form-group col-3">
                <label>Tipo da Chave</label>
                <select name="pix_tipo">
                    <?php $pixTipoAtual = $cliente['pix_tipo'] ?? ''; ?>
                    <option value="">— Nenhuma —</option>
                    <option value="cpf"       <?= $pixTipoAtual === 'cpf'       ? 'selected' : '' ?>>CPF</option>
                    <option value="cnpj"      <?= $pixTipoAtual === 'cnpj'      ? 'selected' : '' ?>>CNPJ</option>
                    <option value="email"     <?= $pixTipoAtual === 'email'     ? 'selected' : '' ?>>E-mail</option>
                    <option value="telefone"  <?= $pixTipoAtual === 'telefone'  ? 'selected' : '' ?>>Telefone</option>
                    <option value="aleatoria" <?= $pixTipoAtual === 'aleatoria' ? 'selected' : '' ?>>Chave aleatória</option>
                </select>
            </div>
            <div class="form-group col-9">
                <label>Chave</label>
                <input type="text" name="pix_chave" maxlength="255"
                       value="<?= htmlspecialchars($cliente['pix_chave'] ?? '') ?>"
                       placeholder="Ex: 123.456.789-00, email@exemplo.com, (65) 99999-9999, ou UUID">
            </div>
        </div>
        <small class="muted">Informe o tipo e a chave PIX para receber pagamentos por transferência instantânea.</small>
    </fieldset>
</div>

<!-- ABA 5: SERVICOS -->
<div class="tab-content" data-tab-content="servicos" role="tabpanel">
<?php if (!empty($cliente['id'])): ?>
    <fieldset id="servicos">
        <legend>🧾 Serviços Contratados (Faturamento Mensal)</legend>
        <div id="cliente-servicos-lista">
            <p class="muted">Carregando serviços contratados...</p>
        </div>
        <div style="margin-top:12px;">
            <a href="cliente_servico_form.php?cliente_id=<?= (int)$cliente['id'] ?>" class="btn btn-primary">+ Novo Serviço Contratado</a>
        </div>
    </fieldset>
    <script>
    (function() {
        var clienteId = <?= (int)$cliente['id'] ?>;
        var lista = document.getElementById('cliente-servicos-lista');
        if (!lista) return;
        fetch('cliente_servico_index.php?cliente_id=' + encodeURIComponent(clienteId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.erro) {
                    lista.innerHTML = '<p class="muted">Erro: ' + data.erro + '</p>';
                    return;
                }
                if (!data.servicos || data.servicos.length === 0) {
                    lista.innerHTML = '<p class="muted">Nenhum serviço contratado. Clique em <strong>+ Novo Serviço Contratado</strong> para começar.</p>';
                    return;
                }
                var html = '<table class="table"><thead><tr>';
                html += '<th>Descrição</th><th>CNAE (fiscal)</th><th class="valor">Valor Mensal</th><th>Início</th><th>Fim</th><th>Status</th><th>Ações</th>';
                html += '</tr></thead><tbody>';
                var total = 0;
                for (var i = 0; i < data.servicos.length; i++) {
                    var s = data.servicos[i];
                    var valor = parseFloat(s.valor_mensal) || 0;
                    if (parseInt(s.ativo) === 1) total += valor;
                    var dataFim = s.data_fim || '—';
                    var status = parseInt(s.ativo) === 1
                        ? '<span class="badge badge-success">Ativo</span>'
                        : '<span class="badge badge-secondary">Inativo</span>';

                    // Chip fiscal: CNAE + NBS + LC 116 (se houver CNAE vinculado)
                    var cnaeChip;
                    if (s.cnae_codigo) {
                        var partesCnae = ['CNAE ' + s.cnae_codigo];
                        if (s.cnae_nbs)   partesCnae.push('NBS ' + s.cnae_nbs);
                        if (s.cnae_lc116) partesCnae.push('LC 116 ' + s.cnae_lc116);
                        cnaeChip = '<span class="badge" style="background:#e0f2fe; color:#075985; border:1px solid #bae6fd; font-size:10px; padding:4px 8px; display:inline-block;">'
                                 + partesCnae.join(' &middot; ').replace(/</g,'&lt;')
                                 + '</span>';
                    } else {
                        cnaeChip = '<span class="muted" style="font-size:11px;">Sem CNAE</span>';
                    }

                    html += '<tr>';
                    html += '<td>' + (s.descricao || '').replace(/</g,'&lt;') + '</td>';
                    html += '<td>' + cnaeChip + '</td>';
                    html += '<td class="valor">R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td>';
                    html += '<td>' + (s.data_inicio || '-') + '</td>';
                    html += '<td>' + dataFim + '</td>';
                    html += '<td>' + status + '</td>';
                    html += '<td>';
                    html += '<a href="cliente_servico_form.php?cliente_id=' + clienteId + '&id=' + s.id + '" class="btn btn-sm">✏️ Editar</a> ';
                    // Botão Excluir: usa type=button + onclick para evitar form aninhado
                    // (o botao esta dentro do <form id="cliente-form"> principal)
                    html += '<button type="button" class="btn btn-sm btn-danger" onclick="excluirServicoCliente(' + s.id + ', ' + clienteId + ', \'' + (s.descricao || '').replace(/'/g, '\\\'') + '\');">🗑️ Excluir</button>';
                    html += '</td>';
                    html += '</tr>';
                }
                html += '<tr style="background:#f9fafb; font-weight:600;"><td colspan="2" style="text-align:right;">Total mensal ativo:</td><td class="valor">R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</td><td colspan="4"></td></tr>';
                html += '</tbody></table>';
                lista.innerHTML = html;
            })
            .catch(function(err) {
                lista.innerHTML = '<p class="muted">Erro de rede: ' + err.message + '</p>';
            });
    })();
    </script>
    <?php endif; ?>
</div>

<!-- ABA 6: FISCAIS (placeholder - sera implementado no Bloco 2) -->
<div class="tab-content" data-tab-content="fiscais" role="tabpanel">
    <div class="alert alert-info">
        <strong>⚙️ Configuração Fiscal (NFSe)</strong>
        <p style="margin:8px 0 0 0; color:#666;">
            Configuração de tipo de faturamento (Individual ou Geral),
            código de serviço (LC 116), alíquota ISS, município de prestação,
            CNAE, NBS — virá na <strong>Fase 2</strong> (Bloco 2 do Faturamento).
        </p>
    </div>
</div>




    <div class="form-group">
        <label>
            <input type="checkbox" name="ativo" value="1" <?= (!$cliente || $cliente['ativo']) ? 'checked' : '' ?>>
            Ativo
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="clientes.php" class="btn">Cancelar</a>
    </div>
</form>

<!-- Script de controle de abas -->
<script>
(function() {
    var btns = document.querySelectorAll('.tab-button');
    var contents = document.querySelectorAll('.tab-content');
    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = btn.getAttribute('data-tab');
            btns.forEach(function(b) { b.classList.remove('active'); });
            contents.forEach(function(c) { c.classList.remove('active'); });
            btn.classList.add('active');
            var content = document.querySelector('[data-tab-content="' + tab + '"]');
            if (content) content.classList.add('active');
            // Lazy-load servicos: carrega so na primeira vez que a aba for aberta
            if (tab === 'servicos' && !window.__servicos_loaded) {
                window.__servicos_loaded = true;
                if (typeof carregarServicosCliente === 'function') {
                    carregarServicosCliente();
                }
            }
        });
    });
    // Se a URL tiver #servicos (apos salvar servico), abre essa aba
    if (window.location.hash === '#servicos') {
        var btnServ = document.querySelector('[data-tab="servicos"]');
        if (btnServ) btnServ.click();
    }

    // Google Maps: botao abrir + salvar link
    var btnAbrir = document.getElementById('btn-abrir-maps');
    var inputUrl = document.getElementById('endereco_maps_input');
    var hiddenLink = document.getElementById('endereco_maps_hidden');
    var display = document.getElementById('endereco_maps_display');
    var btnSalvar = document.getElementById('btn-salvar-link-maps');
    var hiddenLink = document.getElementById('endereco_maps_hidden');
    
    if (btnAbrir) {
        btnAbrir.addEventListener('click', function() {
            var form = btnAbrir.closest('form');
            var cep = form.querySelector('[name="cep"]').value.trim();
            var end = form.querySelector('[name="endereco"]').value.trim();
            var cid = form.querySelector('[name="cidade"]').value.trim();
            var uf  = form.querySelector('[name="uf"]').value.trim();
            var q = [end, cid, uf, cep].filter(Boolean).join(', ');
            if (!q) {
                window.alert('Preencha o endereco (CEP, logradouro, cidade, UF) antes de abrir o Google Maps.');
                return;
            }
            var url = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(q);
            window.open(url, '_blank');
        });
    }
    
    if (btnSalvar && inputUrl && hiddenLink) {
        btnSalvar.addEventListener('click', function() {
            var url = inputUrl.value.trim();
            if (!url) {
                window.alert('Cole uma URL do Google Maps primeiro.');
                return;
            }
            if (!url.startsWith('http://') && !url.startsWith('https://')) {
                window.alert('A URL deve comecar com http:// ou https://');
                return;
            }
            hiddenLink.value = url;
            if (display) {
                // display eh uma div - atualiza com link clicavel
                display.innerHTML = '<a href="' + url + '" target="_blank" rel="noopener noreferrer" style="color: #1e40af; text-decoration: none; font-weight: 500;">\u{1F517} ' + url + '</a>';
            }
            // Feedback visual
            var msg = document.createElement('span');
            msg.textContent = ' ✅ Salvo! Clique em "Salvar" no fim do form para persistir.';
            msg.style.color = '#15803d';
            msg.style.marginLeft = '8px';
            msg.style.fontWeight = '600';
            btnSalvar.parentNode.appendChild(msg);
            setTimeout(function() { msg.remove(); }, 4000);
        });
    }

    if (window.location.hash === '#servicos') {
        var btnServ = document.querySelector('[data-tab="servicos"]');
        if (btnServ) btnServ.click();
    }
})();
</script>




<script>
function addEmail(tipo) {
    const list = document.getElementById('emails-' + tipo + '-list');
    const div = document.createElement('div');
    div.className = 'email-item';
    div.innerHTML = '<input type="email" name="emails_' + tipo + '[]" placeholder="exemplo@cliente.com">' +
                    '<button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()">✕</button>';
    list.appendChild(div);
    div.querySelector('input').focus();
}

// Excluir servico contratado (substitui <form> aninhado que nao funciona dentro de <form id="cliente-form">)
function excluirServicoCliente(id, clienteId, descricao) {
    if (!confirm('ATENCAO: Excluir PERMANENTEMENTE o servico "' + descricao + '"?\n\nEsta acao NAO pode ser desfeita.')) return;
    // Cria form dinamico FORA do form principal para evitar problema de form aninhado
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = 'cliente_servico_acao.php';
    var campoId   = document.createElement('input'); campoId.type='hidden';   campoId.name='id';         campoId.value=id;
    var campoCli  = document.createElement('input'); campoCli.type='hidden';  campoCli.name='cliente_id'; campoCli.value=clienteId;
    var campoAc   = document.createElement('input'); campoAc.type='hidden';   campoAc.name='acao';       campoAc.value='excluir';
    f.appendChild(campoId);
    f.appendChild(campoCli);
    f.appendChild(campoAc);
    document.body.appendChild(f);
    f.submit();
}
</script>

<style>
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
.emails-list {
    margin-bottom: 8px;
}
.email-item {
    display: flex;
    gap: 8px;
    margin-bottom: 6px;
    align-items: center;
}
.email-item input[type="email"] {
    flex: 1;
    padding: 6px 10px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    font-size: 14px;
}
.form-actions {
    display: flex;
    gap: 8px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
}
</style>
<script src="/financeiro/assets/mascara_cpf_cnpj.js?v=20260723_v2_v2_v2"></script>
