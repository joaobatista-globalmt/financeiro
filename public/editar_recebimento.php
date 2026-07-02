<?php
require __DIR__ . '/bootstrap.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$controller = new ContasReceberController();

if ($metodo === 'POST') {
    $controller->salvarEdicaoRecebimento();
} else {
    $controller->editarRecebimento();
}
