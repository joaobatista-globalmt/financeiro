<?php
/** Template Boleto Febraban - $boleto (com nosso_numero/linha_digitavel/codigo_barras/valor_extenso) */
$bancoCodigo = '237';
$bancoNome   = $boleto['banco_nome'] ?? 'Banco Padrao';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Boleto - CR #<?= (int)$boleto['id'] ?></title>
<style>
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 0; }
.boleto { width: 180mm; margin: 0 auto; padding: 4mm 0; }
table { border-collapse: collapse; width: 100%; }
table.banco { border-bottom: 2px solid #000; margin-bottom: 4px; }
table.banco td { vertical-align: middle; padding: 4px 6px; }
table.banco .codigo { font-size: 16px; font-weight: bold; }
table.banco .banco { font-size: 12px; font-weight: bold; }
table.banco .linha-digitavel { font-size: 11px; font-family: 'Courier New', monospace; }
table.ficha { border: 1px solid #000; margin-bottom: 6px; }
table.ficha td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
table.ficha .label { background: #f0f0f0; font-weight: bold; font-size: 8px; text-transform: uppercase; }
table.ficha .valor { font-size: 11px; }
.corte { border-top: 1px dashed #999; margin: 8px 0 4px 0; }
.barcode { background: #fff; padding: 8px 4px; text-align: center; font-family: 'Courier New', monospace; font-size: 36px; line-height: 1; border: 1px solid #000; margin-top: 2px; }
.barcode-fallback { font-size: 10px; color: #666; text-align: center; margin-top: 4px; font-style: italic; }
.text-right { text-align: right; }
.valor-destaque { font-size: 12px; font-weight: bold; }
.agencia-destaque { font-size: 13px; font-weight: bold; }
.w-12 { width: 12%; } .w-15 { width: 15%; } .w-25 { width: 25%; } .w-50 { width: 50%; } .w-100 { width: 100%; }
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
    <td class="valor">Pagavel em qualquer banco ate o vencimento. Apos, atualizar no site do banco.</td>
    <td class="valor text-right valor-destaque"><?= date('d/m/Y', strtotime($boleto['data_vencimento'])) ?></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-100">Cedente<br><span style="font-weight:normal;font-size:9px;"><?= htmlspecialchars($boleto['cedente_nome']) ?> - CNPJ: <?= htmlspecialchars($boleto['cedente_doc']) ?></span></td>
    <td class="label text-right w-25">Agencia / Codigo Cedente</td>
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
    <td class="label w-50">Sacado<br><span style="font-weight:normal;font-size:10px;"><?= htmlspecialchars($boleto['cliente_nome']) ?><br>CPF/CNPJ: <?= htmlspecialchars($boleto['cliente_doc'] ?? 'N/A') ?><br><?= htmlspecialchars($boleto['cliente_endereco'] ?? '') ?>, <?= htmlspecialchars($boleto['cliente_numero'] ?? '') ?> - <?= htmlspecialchars($boleto['cliente_bairro'] ?? '') ?><br><?= htmlspecialchars($boleto['cliente_cidade'] ?? '') ?> / <?= htmlspecialchars($boleto['cliente_uf'] ?? '') ?> - CEP: <?= htmlspecialchars($boleto['cliente_cep'] ?? '') ?></span></td>
    <td class="w-50" style="padding:0;"><table style="width:100%;"><tr>
        <td class="label">Carteira<br><span class="valor">17</span></td>
        <td class="label">Especie<br><span class="valor">R$</span></td>
        <td class="label">Qtde<br><span class="valor">&nbsp;</span></td>
        <td class="label">Valor<br><span class="valor">&nbsp;</span></td>
    </tr><tr>
        <td class="label" colspan="4" style="text-align:right;padding:6px;">(=) Valor do Documento<br><span class="valor-destaque">R$ <?= number_format((float)$boleto['valor'], 2, ',', '.') ?></span></td>
    </tr></table></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label" colspan="6">Demonstrativo / Instrucoes de responsabilidade do cedente</td>
</tr><tr>
    <td class="valor" colspan="6" style="padding:6px 8px;">
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
<div class="barcode"><?= str_repeat('|', 60) ?></div>
<div class="barcode-fallback">(Codigo de barras - sera gerado em Fase 3.5 com digito verificador real)</div>
<div class="corte"></div>
<!-- RECIBO DO SACADO -->
<table class="banco"><tr>
    <td class="codigo w-12"><?= $bancoCodigo ?>-<?= substr($boleto['nosso_numero'], 0, 1) ?></td>
    <td class="banco"><?= htmlspecialchars($bancoNome) ?></td>
    <td class="linha-digitavel text-right w-50"><?= $boleto['linha_digitavel'] ?></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label w-50">Vencimento<br><span class="valor valor-destaque"><?= date('d/m/Y', strtotime($boleto['data_vencimento'])) ?></span></td>
    <td class="label w-50">Valor do Documento<br><span class="valor valor-destaque">R$ <?= number_format((float)$boleto['valor'], 2, ',', '.') ?></span></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label">Cedente<br><span class="valor"><?= htmlspecialchars($boleto['cedente_nome']) ?> - CNPJ: <?= htmlspecialchars($boleto['cedente_doc']) ?></span></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label">Sacado<br><span style="font-weight:normal;font-size:10px;"><?= htmlspecialchars($boleto['cliente_nome']) ?><br>CPF/CNPJ: <?= htmlspecialchars($boleto['cliente_doc'] ?? 'N/A') ?></span></td>
</tr></table>
<table class="ficha"><tr>
    <td class="label">Nosso Numero<br><span class="valor"><?= $boleto['nosso_numero'] ?></span></td>
    <td class="label">Nr. do Doc.<br><span class="valor"><?= htmlspecialchars($boleto['numero_documento'] ?? $boleto['nosso_numero']) ?></span></td>
    <td class="label">Data do Doc.<br><span class="valor"><?= date('d/m/Y', strtotime($boleto['data_emissao'])) ?></span></td>
    <td class="label">Valor por Extenso<br><span class="valor"><?= htmlspecialchars($boleto['valor_extenso']) ?></span></td>
</tr></table>
<p style="text-align:center;font-size:8px;color:#666;margin:8px 0;">Recibo do Sacado - <?= htmlspecialchars($boleto['empresa_nome']) ?> - CR #<?= (int)$boleto['id'] ?> - Gerado em <?= date('d/m/Y H:i:s') ?></p>
</div>
</body>
</html>
