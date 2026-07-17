<?php
/** @var array $cliente */
/** @var array|null $servico */
/** @var array $catalogo */
/** @var array $contas */

$isEdit = !empty($servico);
$actionUrl = 'cliente_servico_salvar.php';
$contas = $contas ?? []; // Garante array vazio se nao vier do controller
?>
<div class="page-header">
    <h1><?= $isEdit ? 'Editar' : 'Novo' ?> Servico Contratado</h1>
    <div>
        <a href="cliente_form.php?id=<?= (int)$cliente['id'] ?>#servicos" class="btn">Voltar para o cliente</a>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom:16px;">
    <strong>Cliente:</strong> <?= htmlspecialchars($cliente['razao_social']) ?>
    <small class="muted"> - ID #<?= (int)$cliente['id'] ?></small>
</div>

<?php if (!empty($_SESSION['flash_erro'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_erro']) ?></div>
    <?php unset($_SESSION['flash_erro']); ?>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($actionUrl) ?>" class="form">
    <input type="hidden" name="id"         value="<?= (int)($servico['id'] ?? 0) ?>">
    <input type="hidden" name="cliente_id" value="<?= (int)$cliente['id'] ?>">

    <fieldset>
        <legend>Identificacao do Servico</legend>
        <div class="row">
            <div class="form-group col-8">
                <label>Descricao *</label>
                <input type="text" id="descricao" name="descricao" required maxlength="200"
                       placeholder="Ex: Contabilidade Mensal, Folha de Pagamento, Consultoria"
                       value="<?= htmlspecialchars($servico['descricao'] ?? '') ?>">
                <small class="muted">Selecionar um CNAE abaixo preenche esta descricao (pode editar).</small>
            </div>
            <div class="form-group col-4">
                <label>Catalogo CNAE (fiscal)</label>
                <select id="cnae_servico_id" name="cnae_servico_id">
                    <option value="">- Texto livre (sem CNAE) -</option>
                    <?php $csid = (int)($servico['cnae_servico_id'] ?? 0); ?>
                    <?php foreach ($catalogo as $cat): ?>
                        <?php
                            $rotulo = trim(
                                ($cat['cnae']            ?? '') . ' | ' .
                                ($cat['codigo_servico']  ?? '') . ' - ' .
                                ($cat['descricao']       ?? '') .
                                ($cat['nbs']       ? ' [NBS ' . $cat['nbs'] . ']'        : '') .
                                ($cat['lc116_item'] ? ' [LC 116 ' . $cat['lc116_item'] . ']' : '')
                            );
                        ?>
                        <option value="<?= (int)$cat['id'] ?>"
                                data-descricao="<?= htmlspecialchars($cat['descricao'] ?? '') ?>"
                                data-cnae="<?= htmlspecialchars($cat['cnae'] ?? '') ?>"
                                data-nbs="<?= htmlspecialchars($cat['nbs'] ?? '') ?>"
                                data-lc116="<?= htmlspecialchars($cat['lc116_item'] ?? '') ?>"
                                <?= $csid === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rotulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="muted">Base para emissao de NFSe. Catalogo fiscal da empresa.</small>
            </div>
        </div>
        <div id="cnae-chip" style="display:none; margin-top:8px;">
            <span class="badge badge-info" style="font-size:11px; padding:6px 10px; background:#e0f2fe; color:#075985; border:1px solid #bae6fd;">
                <span id="cnae-chip-cnae"></span>
                <span id="cnae-chip-nbs" style="margin-left:6px;"></span>
                <span id="cnae-chip-lc116" style="margin-left:6px;"></span>
            </span>
        </div>
    </fieldset>

    <fieldset>
        <legend>Valor e Vigencia</legend>
        <div class="row">
            <div class="form-group col-4">
                <label>Valor Mensal (R$) *</label>
                <input type="text" id="valor_mensal" name="valor_mensal" required
                       inputmode="decimal"
                       placeholder="0,00"
                       value="<?= isset($servico['valor_mensal']) ? number_format((float)$servico['valor_mensal'], 2, ',', '.') : '' ?>">
            </div>
            <div class="form-group col-4">
                <label>Data de Inicio *</label>
                <input type="date" name="data_inicio" required
                       value="<?= htmlspecialchars($servico['data_inicio'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group col-4">
                <label>Data de Fim (opcional)</label>
                <input type="date" name="data_fim"
                       value="<?= htmlspecialchars($servico['data_fim'] ?? '') ?>">
                <small class="muted">Vazio = indeterminado</small>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>Vencimento (opcional)</legend>
        <div class="row">
            <div class="form-group col-4">
                <label>Dia do vencimento</label>
                <input type="number" name="dia_vencimento" min="1" max="31"
                       value="<?= htmlspecialchars($servico['dia_vencimento'] ?? '') ?>"
                       placeholder="1-31">
                <small class="muted">Override do vencimento padrao do cliente</small>
            </div>
            <div class="form-group col-8">
                <label>Tipo de vencimento</label>
                <?php $tv = $servico['tipo_vencimento'] ?? ''; ?>
                <select name="tipo_vencimento">
                    <option value=""        <?= $tv === ''         ? 'selected' : '' ?>>- Usar padrao do cliente -</option>
                    <option value="mes_corrente"  <?= $tv === 'mes_corrente'  ? 'selected' : '' ?>>No mes corrente (ex: dia 5 = mes do servico)</option>
                    <option value="mes_seguinte"  <?= $tv === 'mes_seguinte'  ? 'selected' : '' ?>>No mes seguinte (ex: dia 5 = proximo mes)</option>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend>💰 Dados de Cobrança (Boleto)</legend>
        <div class="row">
            <div class="form-group col-4">
                <label>Tipo de cobrança</label>
                <?php $tc = $servico['tipo_cobranca'] ?? ''; ?>
                <select name="tipo_cobranca">
                    <option value=""             <?= $tc === ''             ? 'selected' : '' ?>>- Padrao -</option>
                    <option value="mensal_fixa"  <?= $tc === 'mensal_fixa'  ? 'selected' : '' ?>>Mensal fixa (valor igual todo mes)</option>
                    <option value="medicao"      <?= $tc === 'medicao'      ? 'selected' : '' ?>>Por medicao (valor varia)</option>
                </select>
                <small class="muted">Define como o valor da fatura sera calculado.</small>
            </div>
            <div class="form-group col-4">
                <label>Conta bancaria (recebimento)</label>
                <?php $cb = (int)($servico['conta_bancaria_id'] ?? 0); ?>
                <select id="conta_bancaria_id" name="conta_bancaria_id">
                    <option value="">- Sem conta definida -</option>
                    <?php foreach ($contas as $conta): ?>
                        <option value="<?= (int)$conta['id'] ?>" <?= $cb === (int)$conta['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(
                                $conta['descricao'] .
                                ($conta['banco']        ? ' - ' . $conta['banco']         : '') .
                                ($conta['agencia']      ? ' / Ag ' . $conta['agencia']    : '') .
                                ($conta['numero_conta'] ? ' / CC ' . $conta['numero_conta'] . ($conta['digito'] ? '-' . $conta['digito'] : '') : '')
                            ) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="muted">Conta que recebera o boleto.</small>
            </div>
            <div class="form-group col-4">
                <label>Nº do Contrato / Proposta</label>
                <input type="text" name="numero_contrato" maxlength="50"
                       placeholder="Ex: CT-2026-001"
                       value="<?= htmlspecialchars($servico['numero_contrato'] ?? '') ?>">
            </div>
        </div>
        <div class="row">
            <div class="form-group col-3">
                <label>Multa por atraso (%)</label>
                <input type="text" name="multa_percentual" inputmode="decimal"
                       placeholder="2,00"
                       value="<?= $servico['multa_percentual'] !== null ? number_format((float)$servico['multa_percentual'], 2, ',', '.') : '2,00' ?>">
                <small class="muted">Padrao 2,00%</small>
            </div>
            <div class="form-group col-3">
                <label>Juros mensal (%)</label>
                <input type="text" name="juros_mensal_percentual" inputmode="decimal"
                       placeholder="1,00"
                       value="<?= $servico['juros_mensal_percentual'] !== null ? number_format((float)$servico['juros_mensal_percentual'], 2, ',', '.') : '1,00' ?>">
                <small class="muted">Padrao 1,00% a.m.</small>
            </div>
            <div class="form-group col-6">
                <label>Tipo de boleto</label>
                <?php $tb = $servico['tipo_boleto'] ?? 'sem_registro'; ?>
                <select name="tipo_boleto">
                    <option value="sem_registro"  <?= $tb === 'sem_registro'  ? 'selected' : '' ?>>Sem registro (mais simples, sem CNAB)</option>
                    <option value="com_registro"  <?= $tb === 'com_registro'  ? 'selected' : '' ?>>Com registro (integrado com banco via CNAB)</option>
                </select>
                <small class="muted">Sem registro: gera PDF direto. Com registro: precisa de convenio bancario.</small>
            </div>
        </div>
        <div class="row">
            <div class="form-group col-6">
                <label>Instrucoes do boleto - Linha 1</label>
                <input type="text" id="instrucoes_boleto_linha1" name="instrucoes_boleto_linha1" maxlength="80"
                       placeholder="Ref. {descricao} - {mes/ano}"
                       value="<?= htmlspecialchars($servico['instrucoes_boleto_linha1'] ?? '') ?>">
                <small class="muted">Vai impresso no campo "instrucoes" do boleto.</small>
            </div>
            <div class="form-group col-6">
                <label>Instrucoes do boleto - Linha 2 (opcional)</label>
                <input type="text" name="instrucoes_boleto_linha2" maxlength="80"
                       placeholder="Ex: Nao receber apos 30 dias"
                       value="<?= htmlspecialchars($servico['instrucoes_boleto_linha2'] ?? '') ?>">
            </div>
        </div>
    </fieldset>

    <div class="form-group">
        <label>Observacoes</label>
        <textarea name="observacoes" rows="3" placeholder="Anotacoes internas sobre este servico"><?= htmlspecialchars($servico['observacoes'] ?? '') ?></textarea>
    </div>

    <div class="form-group">
        <label>
            <input type="checkbox" name="ativo" value="1" <?= (!$servico || (int)$servico['ativo'] === 1) ? 'checked' : '' ?>>
            <strong>Ativo</strong> - servico entra no faturamento mensal
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
        <a href="cliente_form.php?id=<?= (int)$cliente['id'] ?>#servicos" class="btn">Cancelar</a>
    </div>
</form>

<script>
// Auto-preencher descricao + chip fiscal ao escolher do catalogo CNAE
(function() {
    var sel    = document.getElementById('cnae_servico_id');
    var desc   = document.getElementById('descricao');
    var chip   = document.getElementById('cnae-chip');
    var chCnae = document.getElementById('cnae-chip-cnae');
    var chNbs  = document.getElementById('cnae-chip-nbs');
    var chLc   = document.getElementById('cnae-chip-lc116');
    var instr1 = document.getElementById('instrucoes_boleto_linha1');
    if (!sel) return;

    function render() {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) {
            if (chip) chip.style.display = 'none';
            return;
        }
        var dDesc = opt.getAttribute('data-descricao');
        var dCnae = opt.getAttribute('data-cnae');
        var dNbs  = opt.getAttribute('data-nbs');
        var dLc   = opt.getAttribute('data-lc116');
        if (desc && dDesc && !desc.value.trim()) desc.value = dDesc;
        if (chip) {
            chCnae.textContent = dCnae ? ('CNAE: ' + dCnae) : '';
            chNbs.textContent  = dNbs  ? ('NBS ' + dNbs) : '';
            chLc.textContent   = dLc   ? ('LC 116 ' + dLc) : '';
            chip.style.display = (dCnae || dNbs || dLc) ? 'inline-block' : 'none';
        }
        // Auto-preencher instrucoes do boleto com template fixo (se vazio)
        if (instr1 && dDesc && !instr1.value.trim()) {
            var meses = ['Janeiro','Fevereiro','Marco','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
            var hoje = new Date();
            var mesAno = meses[hoje.getMonth()] + '/' + hoje.getFullYear();
            instr1.value = 'Ref. ' + dDesc + ' - ' + mesAno;
        }
    }

    sel.addEventListener('change', render);
    // render inicial (caso esteja editando um servico ja vinculado)
    render();
})();
</script>
