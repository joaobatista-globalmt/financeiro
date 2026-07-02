<?php
require __DIR__ . '/bootstrap.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$controller = new MovimentacoesController();

if ($metodo === 'POST') {
    $controller->salvarCorrecao();
} else {
    $controller->corrigir();
}
