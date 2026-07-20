<?php
require __DIR__ . '/bootstrap.php';
$action = $_GET['action'] ?? 'index';
$controller = new ContasReceberController();
if ($action === 'drilldown') {
    $controller->drillDown();
} else {
    $controller->index();
}
