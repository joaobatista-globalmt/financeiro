<?php
require __DIR__ . '/bootstrap.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$controller = new ContasPagarController();

if ($metodo === 'POST') {
    $controller->salvarEdicaoPagamento();
} else {
    $controller->editarPagamento();
}
