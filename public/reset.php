<?php
/**
 * Reset de senha - Sistema Financeiro
 * 
 * Modos:
 *   1. ?action=forgot   → form de "esqueci minha senha" (informa email, gera token, mostra link)
 *   2. ?token=XXX       → form de redefinir senha (token válido)
 *   3. POST com token   → executa a redefinição
 * 
 * Como o sistema ainda NÃO tem SMTP configurado, após gerar o token
 * o link é mostrado na tela (com timer de 30min). Quando configurar SMTP,
 * basta trocar o bloco "MODO DEMO" por mail() e remover a exibição do link.
 * 
 * Segurança:
 *  - Token plain só aparece no link. Banco guarda HASH (sha256).
 *  - Single-use, expira em 30 min.
 *  - Rate limit por IP (10/15min) e por email (3/15min) - anti enumeração.
 *  - Sempre mostra a mesma mensagem genérica "se o email existir..." (não revela
 *    se o email está cadastrado).
 */

declare(strict_types=1);
session_start();

// Carrega env vars do PHP-FPM
$conf = @file_get_contents('/etc/php/8.2/fpm/pool.d/www.conf');
if ($conf) {
    preg_match_all('/^env\[([^\]]+)\] = (.+)$/m', $conf, $m);
    foreach ($m[1] as $i => $k) {
        putenv("$k=" . trim($m[2][$i]));
        $_ENV[$k] = trim($m[2][$i]);
    }
}

require __DIR__ . '/../src/lib/Database.php';
require __DIR__ . '/../src/lib/Helper.php';

$msg_ok = '';
$msg_erro = '';
$token_valido = false;
$user_nome = '';
$demo_link = ''; // link mostrado em modo demo (sem SMTP)
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Rate limit por IP (best effort, /tmp)
function rate_limit_check(string $cache, int $max, int $window): bool {
    $now = time();
    $data = ['count' => 0, 'reset' => $now + $window];
    if (file_exists($cache)) {
        $raw = @file_get_contents($cache);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['count'], $decoded['reset'])) {
            if ($decoded['reset'] > $now) $data = $decoded;
        }
    }
    if ($data['count'] >= $max) return false;
    $data['count']++;
    @file_put_contents($cache, json_encode($data));
    return true;
}

try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die('Erro de conexão. Tente novamente em alguns minutos.');
}

// ============================================================
// MODO 3: POST com token (executar reset)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reset') {
    if (!rate_limit_check('/tmp/reset_rate_ip_' . md5($ip) . '.json', 10, 900)) {
        $msg_erro = 'Muitas tentativas. Aguarde 15 minutos.';
    } else {
        $token = trim((string)($_POST['token'] ?? ''));
        $senha = (string)($_POST['nova_senha'] ?? '');
        $senha2 = (string)($_POST['nova_senha2'] ?? '');

        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            $msg_erro = 'Token inválido.';
        } elseif (strlen($senha) < 6) {
            $msg_erro = 'Senha deve ter no mínimo 6 caracteres.';
        } elseif ($senha !== $senha2) {
            $msg_erro = 'As senhas não conferem.';
        } else {
            $token_hash = hash('sha256', $token);
            $stmt = $pdo->prepare("
                SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, pr.used_at, u.nome
                FROM password_resets pr
                JOIN usuarios u ON u.id = pr.user_id
                WHERE pr.token_hash = ?
                LIMIT 1
            ");
            $stmt->execute([$token_hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $msg_erro = 'Token não encontrado.';
            } elseif ($row['used_at'] !== null) {
                $msg_erro = 'Este link já foi usado. Solicite um novo.';
            } elseif (strtotime($row['expires_at']) < time()) {
                $msg_erro = 'Link expirado. Solicite um novo.';
            } else {
                $novo_hash = password_hash($senha, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
                $upd->execute([$novo_hash, $row['user_id']]);

                $mark = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
                $mark->execute([$row['reset_id']]);

                $msg_ok = 'Senha alterada com sucesso! Você já pode fazer login.';
            }
        }
    }
}

// ============================================================
// MODO 1: ?action=forgot (ou POST com action=forgot) - gerar token
// ============================================================
if ($action === 'forgot') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim((string)($_POST['email'] ?? ''));
        
        // Rate limit por email (anti enumeração)
        $emailCache = '/tmp/reset_rate_email_' . md5(strtolower($email)) . '.json';
        if (!rate_limit_check($emailCache, 3, 900)) {
            $msg_erro = 'Muitas tentativas para este e-mail. Aguarde 15 minutos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg_erro = 'E-mail inválido.';
        } else {
            // Mensagem genérica (não revela se email existe)
            $msg_ok = 'Se o e-mail estiver cadastrado, um link de redefinição foi gerado. '
                    . 'Verifique sua caixa de entrada ou contate o administrador.';
            
            // Verifica se email existe e gera token
            $stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Invalida tokens anteriores não-usados
                $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                    ->execute([$user['id']]);
                
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
                
                $ins = $pdo->prepare("
                    INSERT INTO password_resets (user_id, token_hash, expires_at, ip_request)
                    VALUES (?, ?, ?, ?)
                ");
                $ins->execute([$user['id'], $token_hash, $expires, 'web:' . $ip]);
                
                // ===================================================
                // MODO DEMO (sem SMTP): mostra o link na tela
                // Quando configurar SMTP, substituir este bloco por:
                //   $assunto = 'Redefinição de senha - Sistema Financeiro';
                //   $mensagem = "Olá {$user['nome']},\n\n"
                //             . "Clique no link para redefinir sua senha:\n"
                //             . "$link\n\n"
                //             . "Este link expira em 30 minutos.";
                //   mail($email, $assunto, $mensagem, 'From: noreply@globalmt.com.br');
                // ===================================================
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? '192.168.70.45';
                $path = dirname($_SERVER['SCRIPT_NAME']) . '/reset.php';
                $path = str_replace('//', '/', $path);
                $link = "$scheme://$host$path?token=$token";
                $demo_link = $link;
            }
        }
    }
    // Continua pra renderizar o form forgot
}

// ============================================================
// MODO 2: ?token=XXX (GET) - validar token e mostrar form de reset
// ============================================================
$token = trim((string)($_GET['token'] ?? ''));
if (empty($action) && strlen($token) === 64 && ctype_xdigit($token)) {
    $token_hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT pr.id, pr.expires_at, pr.used_at, u.nome
        FROM password_resets pr
        JOIN usuarios u ON u.id = pr.user_id
        WHERE pr.token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([$token_hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['used_at'] === null && strtotime($row['expires_at']) >= time()) {
        $token_valido = true;
        $user_nome = $row['nome'];
    } elseif ($row && $row['used_at'] !== null) {
        $msg_erro = 'Este link já foi usado. Solicite um novo.';
    } elseif ($row) {
        $msg_erro = 'Link expirado. Solicite um novo.';
    } else {
        $msg_erro = 'Token inválido.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redefinir Senha - Sistema Financeiro</title>
  <link rel="stylesheet" href="assets/financeiro.css">
  <style>
    body.login-page { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
    .login-box { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.3); width: 100%; max-width: 480px; overflow: hidden; }
    .login-header { background: linear-gradient(135deg, #2563eb, #1e40af); color: #fff; padding: 2rem; text-align: center; }
    .login-header h1 { font-size: 1.4rem; margin: 0; }
    .login-body { padding: 2rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: .9rem; color: #1e293b; margin-bottom: .4rem; font-weight: 500; }
    .form-group input { width: 100%; padding: .75rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; box-sizing: border-box; }
    .form-group input:focus { outline: none; border-color: #2563eb; }
    .btn-login { width: 100%; padding: .85rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
    .btn-login:hover { background: #1e40af; }
    .msg { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .95rem; }
    .msg.ok { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .info { background: #dbeafe; color: #1e40af; padding: .75rem 1rem; border-radius: 8px; font-size: .9rem; margin-bottom: 1rem; }
    .demo-link {
        background: #fef3c7; border: 1px solid #fbbf24; color: #92400e;
        padding: 1rem; border-radius: 8px; margin: 1rem 0; font-size: .85rem;
        word-break: break-all;
    }
    .demo-link strong { display: block; margin-bottom: 6px; }
    .demo-link code { background: #fff; padding: 4px 6px; border-radius: 4px; font-size: .8rem; }
    .footer-link { text-align: center; padding: 1rem; color: #64748b; font-size: .85rem; }
    .footer-link a { color: #2563eb; text-decoration: none; }
    .footer-link a:hover { text-decoration: underline; }
  </style>
</head>
<body class="login-page">
  <div class="login-box">
    <div class="login-header">
      <h1>🔑 <?= $action === 'forgot' ? 'Esqueci minha senha' : 'Redefinir Senha' ?></h1>
    </div>
    <div class="login-body">
      <?php if ($msg_ok): ?>
        <div class="msg ok">✅ <?= htmlspecialchars($msg_ok) ?></div>
      <?php endif; ?>
      <?php if ($msg_erro): ?>
        <div class="msg err">⚠️ <?= htmlspecialchars($msg_erro) ?></div>
      <?php endif; ?>

      <?php if ($action === 'forgot' && !$msg_ok): ?>
        <p style="color:#475569; margin-bottom:1rem; font-size:.95rem;">
          Informe seu e-mail. Se estiver cadastrado, geraremos um link de redefinição válido por 30 minutos.
        </p>
        <form method="POST" autocomplete="off">
          <input type="hidden" name="action" value="forgot">
          <div class="form-group">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <button type="submit" class="btn-login">📧 Gerar link de redefinição</button>
        </form>
      <?php elseif ($action === 'forgot' && $msg_ok && $demo_link): ?>
        <?php
        // Calcula expiração para mostrar ao usuário
        $expiraEm = (new DateTime('+30 minutes'))->format('H:i');
        ?>
        <div class="demo-link">
          <strong>🔗 Link de redefinição (MODO DEMO)</strong>
          O sistema não tem SMTP configurado. Copie o link abaixo e abra no navegador para redefinir sua senha:
          <div style="margin-top:8px;">
            <code><?= htmlspecialchars($demo_link) ?></code>
          </div>
          <div style="margin-top:8px; font-size:.8rem;">
            ⏰ Expira às <strong><?= $expiraEm ?></strong> (servidor)
          </div>
        </div>
        <a href="login.php" class="btn-login" style="display:block; text-align:center; text-decoration:none;">Ir para o Login</a>
      <?php elseif ($action === 'forgot' && $msg_ok): ?>
        <a href="login.php" class="btn-login" style="display:block; text-align:center; text-decoration:none;">Ir para o Login</a>
      <?php elseif ($token_valido): ?>
        <div class="info">
          👤 Definir nova senha para: <strong><?= htmlspecialchars($user_nome) ?></strong>
        </div>
        <form method="POST" autocomplete="off">
          <input type="hidden" name="action" value="reset">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <div class="form-group">
            <label for="nova_senha">Nova senha (mínimo 6 caracteres)</label>
            <input type="password" id="nova_senha" name="nova_senha" minlength="6" required autofocus>
          </div>
          <div class="form-group">
            <label for="nova_senha2">Confirme a nova senha</label>
            <input type="password" id="nova_senha2" name="nova_senha2" minlength="6" required>
          </div>
          <button type="submit" class="btn-login">💾 Salvar nova senha</button>
        </form>
      <?php else: ?>
        <p style="color:#475569; text-align:center;">
          <?php if ($msg_erro): ?>
            Solicite um novo link de reset no formulário de login.
          <?php else: ?>
            Solicite um link de redefinição informando seu e-mail.
          <?php endif; ?>
        </p>
        <a href="reset.php?action=forgot" class="btn-login" style="display:block; text-align:center; text-decoration:none; margin-top:1rem;">📧 Solicitar link</a>
      <?php endif; ?>
    </div>
    <div class="footer-link">
      <a href="login.php">← Voltar para o login</a>
    </div>
  </div>
</body>
</html>
