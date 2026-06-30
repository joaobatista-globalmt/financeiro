<?php
/**
 * Endpoint: cnae_servico_acao.php
 * Roteia acao: salvar | excluir | toggle
 */
require __DIR__ . '/bootstrap.php';

$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';
$controller = new CnaeServicoController();

switch ($acao) {
    case 'salvar':  $controller->salvar();   break;
    case 'excluir': $controller->excluir();  break;
    case 'toggle':  $controller->toggleAtivo(); break;
    default:
        redirect('cnae_servicos_listar.php', 'erro', 'Ação inválida.');
}
