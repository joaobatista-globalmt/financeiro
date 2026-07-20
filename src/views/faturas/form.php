<?php
/** @var string $mes */
/** @var string $ini */
/** @var string $fim */
/** @var array $candidatos */
/** @var int $totalClientes */
/** @var float $totalValor */
?>
<div class="page-header">
    <h1>Gerar Faturas - <?= htmlspecialchars($mes) ?></h1>
    <div>
        <a href="faturas.php?mes=<?= urlencode($mes) ?>" class="btn">Voltar</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_erro'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_erro']) ?></div>
    <?php unset($_SESSION['flash_erro']); ?>
<?php endif; ?>

<div class="alert alert-info">
    <strong>Periodo:</strong> <?= date('d/m/Y', strtotime($ini)) ?> a <?= date('d/m/Y', strtotime($fim)) ?><br>
    <strong>Encontrados:</strong> <?= count($candidatos) ?> servico(s) em <?= $totalClientes ?> cliente(s) — total estimado
    R$ <?= number_format($totalValor, 2, ',', '.') ?>
</div>

<?php if (empty($candidatos)): ?>
    <div class="alert alert-warning">
        Nenhum servico ativo sem fatura para <?= htmlspecialchars($mes) ?>.
        Verifique se os servicos estao com <code>ativo=1</code> e com vigencia no periodo.
    </div>
<?php else: ?>

<form method="post" action="fatura_acao.php?acao=gerar" id="form-gerar">
    <input type="hidden" name="mes_referencia" value="<?= htmlspecialchars($mes) ?>">

    <div style="margin: 12px 0;">
        <label>
            <input type="checkbox" id="selecionar-todos" checked>
            <strong>Selecionar todos</strong>
        </label>
    </div>

    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:30px;">
                        <input type="checkbox" id="check-all" checked>
                    </th>
                    <th>Cliente</th>
                    <th>Servico</th>
                    <th>Tipo</th>
                    <th>Dia Venc.</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidatos as $c): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="servicos[]" value="<?= (int)$c['servico_id'] ?>" class="check-servico" checked>
                        </td>
                        <td><?= htmlspecialchars($c['cliente_nome']) ?></td>
                        <td><?= htmlspecialchars($c['descricao']) ?></td>
                        <td>
                            <span class="badge"><?= htmlspecialchars($c['tipo_cobranca'] ?? 'mensal_fixa') ?></span>
                        </td>
                        <td><?= (int)($c['dia_vencimento'] ?? 0) ?: '-' ?></td>
                        <td style="text-align:right;">
                            R$ <?= number_format((float)$c['valor_mensal'], 2, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="form-actions" style="margin-top: 16px;">
        <button type="submit" class="btn btn-primary" onclick="return confirmarGeracao()">
            Gerar Faturas Selecionadas
        </button>
        <a href="faturas.php?mes=<?= urlencode($mes) ?>" class="btn">Cancelar</a>
    </div>
</form>

<script>
function confirmarGeracao() {
    var marcados = document.querySelectorAll('.check-servico:checked').length;
    if (marcados === 0) {
        alert('Selecione ao menos um servico para gerar fatura.');
        return false;
    }
    return confirm('Confirma a geracao de ' + marcados + ' fatura(s) para ' + document.querySelector('input[name="mes_referencia"]').value + '?');
}
document.getElementById('check-all').addEventListener('change', function() {
    document.querySelectorAll('.check-servico').forEach(function(c) { c.checked = this.checked; }.bind(this));
});
</script>

<?php endif; ?>
