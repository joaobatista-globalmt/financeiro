<?php
require __DIR__ . '/bootstrap.php';
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'ID invalido'; exit; }
(new ContasReceberController())->gerarBoletoPdf($id);
