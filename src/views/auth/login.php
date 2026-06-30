<?php
Auth::start();
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Financeiro</title>
    <link rel="stylesheet" href="assets/financeiro.css">
    <style>
        .forgot-link {
            display: block;
            text-align: center;
            margin-top: 14px;
            color: var(--color-text-muted);
            font-size: 13px;
            text-decoration: none;
            transition: color .15s;
        }
        .forgot-link:hover {
            color: var(--color-primary);
            text-decoration: underline;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-box">
        <h1>💰 Financeiro</h1>
        <p class="subtitle">Sistema integrado de Contas a Pagar, Receber e Bancos</p>

        <?php if ($erro): ?>
            <div class="flash flash-erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Senha</label>
                <input type="password" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
        </form>

        <a href="reset.php?action=forgot" class="forgot-link">🔑 Esqueci minha senha</a>
    </div>
</body>
</html>
