<?php
/**
 * Login - renderiza formulário de autenticação
 *
 * Caso especial: não passa pelo Controller porque o form
 * precisa estar disponível ANTES do login (sem sessão ativa).
 */

require __DIR__ . '/bootstrap.php';

if (Auth::isLogged()) {
    header('Location: index.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    if (empty($email) || empty($senha)) {
        $erro = 'Informe e-mail e senha.';
    } else {
        $resultado = Auth::login($email, $senha);
        if ($resultado) {
            header('Location: index.php');
            exit;
        }
        $erro = 'E-mail ou senha inválidos, ou usuário sem empresa vinculada.';
    }
}

require __DIR__ . '/../src/views/auth/login.php';