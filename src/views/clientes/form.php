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
?>
<div class="page-header">
    <h1><?= $cliente ? 'Editar' : 'Novo' ?> Cliente</h1>
    <a href="clientes.php" class="btn">Voltar</a>
</div>

<form method="post" action="cliente_salvar.php" class="form" id="cliente-form">
    <input type="hidden" name="id" value="<?= (int)($cliente['id'] ?? 0) ?>">

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
                <select name="tipo_pessoa" required>
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
                <input type="text" name="cpf_cnpj" maxlength="20"
                       value="<?= htmlspecialchars($cliente['cpf_cnpj'] ?? '') ?>">
            </div>
        </div>
    </fieldset>

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

    <div class="form-group">
        <label>Observações</label>
        <textarea name="observacoes" rows="3"><?= htmlspecialchars($cliente['observacoes'] ?? '') ?></textarea>
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
