<?php
/** Template Boleto Febraban v2 - A5 paisagem + 3 campos BB (compacto p/ caber em 1 pg) */
$bancoCodigo = '237';
$bancoNome   = $boleto['banco_nome'] ?? 'Banco Padrao';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Boleto - CR #<?= (int)$boleto['id'] ?></title>
<style>
@page { size: A5 landscape; margin: 4mm; }
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 9px; margin: 0; padding: 0; }
.boleto { width: 188mm; margin: 0 auto; }
table { border-collapse: collapse; width: 100%; }
table.banco { border-bottom: 2px solid #000; }
table.banco td { vertical-align: middle; padding: 2px 4px; }
table.banco .codigo { font-size: 14px; font-weight: bold; }
table.banco .banco { font-size: 11px; font-weight: bold; }
table.banco .linha-digitavel { font-size: 10px; font-family: 'Courier New', monospace; }
table.ficha { border: 1px solid #000; margin-bottom: 3px; }
table.ficha td { border: 1px solid #000; padding: 2px 3px; vertical-align: top; }
table.ficha .label { background: #f0f0f0; font-weight: bold; font-size: 7px; text-transform: uppercase; }
table.ficha .valor { font-size: 10px; }
.corte {
    border-top: 1px dashed #000;
    margin: 4px 0 2px 0;
    padding-top: 2px;
    text-align: center;
    font-size: 7px;
    color: #000;
    text-transform: uppercase;
    font-weight: bold;
}
.corte::before { content: "✂ ---------------------------------------------------------------------------------------------------------------------------"; }
.autenticacao { font-size: 7px; color: #000; text-align: center; margin-top: 2px; font-weight: bold; text-transform: uppercase; }
.barcode-img img { max-width: 100%; height: auto; display: block; }
.barcode-fallback { font-size: 9px; color: #666; text-align: center; margin-top: 2px; font-style: italic; }
.barcode-number { font-size: 10px; text-align: center; font-family: 'Courier New', monospace; }
.text-right { text-align: right; }
.valor-destaque { font-size: 11px; font-weight: bold; }
.agencia-destaque { font-size: 12px; font-weight: bold; }
.local-pagamento-box { background: #fffbe6; border-left: 3px solid #000; padding: 2px 4px; font-size: 9px; font-weight: bold; }
.w-12 { width: 12%; } .w-15 { width: 15%; } .w-25 { width: 25%; } .w-30 { width: 30%; } .w-50 { width: 50%; } .w-70 { width: 70%; } .w-100 { width: 100%; }
</style>
</head>
<body>
<div class="boleto">
<!-- FICHA DE COMPENSACAO -->
<table class="banco"><tr>
    <td class="codigo w-12"><?= $bancoCodigo ?>-<?= substr($boleto['nosso_numero'], 0, 1) ?></td>
    <td class="banco"><?= htmlspecialchars($bancoNome) ?></td>
    <td class="linha-digitavel text-right w-50"><?= $boleto['linha_digitavel'] ?></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-100">Local de Pagamento</td>
    <td class="label text-right w-25">Vencimento</td>
</tr><tr>
    <td class="valor"><div class="local-pagamento-box">Pag&aacute;vel preferencialmente no <?= htmlspecialchars($bancoNome) ?>. Ap&oacute;s o vencimento, atualizar no site do banco.</div></td>
    <td class="valor text-right valor-destaque"><?= date('d/m/Y', strtotime($boleto['data_vencimento'])) ?></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-70">Cedente<br><span style="font-weight:normal;font-size:8px;"><?= htmlspecialchars($boleto['cedente_nome']) ?> - CNPJ: <?= htmlspecialchars($boleto['cedente_doc']) ?></span></td>
    <td class="label text-right w-30">Agencia / Codigo Cedente</td>
</tr><tr>
    <td></td>
    <td class="valor text-right agencia-destaque"><?= htmlspecialchars($boleto['agencia']) ?> / <?= htmlspecialchars($boleto['numero_conta'] . '-' . $boleto['digito']) ?></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-15">Data do Doc.<br><span class="valor"><?= date('d/m/Y', strtotime($boleto['data_emissao'])) ?></span></td>
    <td class="label w-15">Nr. do Doc.<br><span class="valor"><?= htmlspecialchars($boleto['numero_documento'] ?? $boleto['nosso_numero']) ?></span></td>
    <td class="label w-15">Especie<br><span class="valor">DM</span></td>
    <td class="label w-15">Aceite<br><span class="valor">N</span></td>
    <td class="label w-15">Processamento<br><span class="valor"><?= date('d/m/Y', strtotime($boleto['data_emissao'])) ?></span></td>
    <td class="label w-15">Nosso Numero<br><span class="valor"><?= $boleto['nosso_numero'] ?></span></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-50">Sacado<br><span style="font-weight:normal;font-size:9px;"><?= htmlspecialchars($boleto['cliente_nome']) ?><br>CPF/CNPJ: <?= htmlspecialchars($boleto['cliente_doc'] ?? 'N/A') ?><br><?= htmlspecialchars($boleto['cliente_endereco'] ?? '') ?><br><?= htmlspecialchars($boleto['cliente_cidade'] ?? '') ?> / <?= htmlspecialchars($boleto['cliente_uf'] ?? '') ?> - CEP: <?= htmlspecialchars($boleto['cliente_cep'] ?? '') ?></span></td>
    <td class="w-50" style="padding:0;"><table style="width:100%;"><tr>
        <td class="label">Carteira<br><span class="valor">17</span></td>
        <td class="label">Especie<br><span class="valor">R$</span></td>
        <td class="label">Qtde<br><span class="valor">&nbsp;</span></td>
        <td class="label">Valor<br><span class="valor">&nbsp;</span></td>
    </tr><tr>
        <td class="label" colspan="4" style="text-align:right;padding:3px;">(=) Valor do Documento<br><span class="valor-destaque">R$ <?= number_format((float)$boleto['valor'], 2, ',', '.') ?></span></td>
    </tr></table></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label" colspan="6">Demonstrativo / Instrucoes de responsabilidade do cedente</td>
</tr><tr>
    <td class="valor" colspan="6" style="padding:3px 5px;">
        Referente a: <?= htmlspecialchars($boleto['descricao']) ?><br>
        Apos o vencimento, cobrar juros de 1% ao mes e multa de 2%. Nao receber apos 30 dias do vencimento.
    </td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-15">(-) Desc</td>
    <td class="label w-15">(+) Jur/Mul</td>
    <td class="label w-15">(=) Vlr Cobrado</td>
    <td class="label w-15">(-) Out Ded</td>
    <td class="label w-15">(+) Out Acr</td>
    <td class="label w-25 text-right">(=) Vlr Cobrado</td>
</tr><tr>
    <td class="valor">&nbsp;</td><td class="valor">&nbsp;</td><td class="valor">&nbsp;</td><td class="valor">&nbsp;</td><td class="valor">&nbsp;</td>
    <td class="valor text-right valor-destaque">R$ <?= number_format((float)$boleto['valor'], 2, ',', '.') ?></td>
</tr></table>
<?php $barcodeImg = $boleto['barcode_png'] ?? ''; ?>
<div class="barcode-img">
    <?php if ($barcodeImg): ?>
        <img src="<?= $barcodeImg ?>" alt="Codigo de barras: <?= htmlspecialchars($boleto['codigo_barras']) ?>" style="max-width: 100%; height: auto;">
    <?php else: ?>
        <div class="barcode-fallback"><?= str_repeat('|', 60) ?><br><small>(GD nao disponivel - codigo: <?= htmlspecialchars($boleto['codigo_barras']) ?>)</small></div>
    <?php endif; ?>
</div>
<div class="barcode-number"><?= htmlspecialchars($boleto['codigo_barras']) ?></div>
<!-- CORTE -->
<div class="corte">corte aqui</div>
<!-- RECIBO DO SACADO (compacto) -->
<table class="banco"><tr>
    <td class="codigo w-12"><?= $bancoCodigo ?>-<?= substr($boleto['nosso_numero'], 0, 1) ?></td>
    <td class="banco"><?= htmlspecialchars($bancoNome) ?></td>
    <td class="linha-digitavel text-right w-50"><?= $boleto['linha_digitavel'] ?></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-25">Vencimento<br><span class="valor valor-destaque"><?= date('d/m/Y', strtotime($boleto['data_vencimento'])) ?></span></td>
    <td class="label w-25">Valor do Documento<br><span class="valor valor-destaque">R$ <?= number_format((float)$boleto['valor'], 2, ',', '.') ?></span></td>
    <td class="label w-50">Sacado<br><span style="font-weight:normal;font-size:9px;"><?= htmlspecialchars($boleto['cliente_nome']) ?> - CPF/CNPJ: <?= htmlspecialchars($boleto['cliente_doc'] ?? 'N/A') ?></span></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-50">Cedente<br><span class="valor"><?= htmlspecialchars($boleto['cedente_nome']) ?> - CNPJ: <?= htmlspecialchars($boleto['cedente_doc']) ?></span></td>
    <td class="label w-25">Nosso Numero<br><span class="valor"><?= $boleto['nosso_numero'] ?></span></td>
    <td class="label w-25">Valor por Extenso<br><span class="valor"><?= htmlspecialchars($boleto['valor_extenso']) ?></span></td>
</tr></table>
<p class="autenticacao">Autentica&ccedil;&atilde;o Mec&acirc;nica</p>
<p style="text-align:center;font-size:7px;color:#666;margin:2px 0 0 0;">Recibo do Sacado - <?= htmlspecialchars($boleto['empresa_nome']) ?> - CR #<?= (int)$boleto['id'] ?> - Gerado em <?= date('d/m/Y H:i:s') ?></p>
</div>
</body>
</html>
