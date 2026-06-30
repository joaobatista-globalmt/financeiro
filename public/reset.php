<?php
/**
 * Reset de senha - Sistema Financeiro
 * 
 * Fluxo: usuário pede reset -> admin gera token via CLI ->
 *        usuário clica no link -> preenche nova senha -> bcrypt hash gravado
 * 
 * Compatível com o Auth::login() que usa password_verify($senha, $hash).
 * 
 * Segurança:
 *  - Token plain só aparece no link enviado. Banco guarda HASH (sha256).
 *  - Token expira em 30 min, single-use.
 *  - Senha mínima 6 chars.
 *  - Rate limit: 10 tentativas / 15 min por IP.
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

// Rate limit simples por IP
function rate_limit_check(string $ip): bool {
    $cache = '/tmp/reset_rate_fin_' . md5($ip) . '.json';
    $now = time();
    $window = 900;
    $max = 10;
    $data = ['count' => 0, 'reset' => $now + $window];
    if (file_exists($cache)) {
        $raw = @file_get_contents($cache);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['count'], $decoded['reset'])) {
            if ($decoded['reset'] > $now) {
                $data = $decoded;
            }
        }
    }
    if ($data['count'] >= $max) return false;
    $data['count']++;
    @file_put_contents($cache, json_encode($data));
    return true;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    die('Erro de conexão. Tente novamente em alguns minutos.');
}

// POST: enviar nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'], $_POST['nova_senha'], $_POST['nova_senha2'])) {
    if (!rate_limit_check($ip)) {
        $msg_erro = 'Muitas tentativas. Aguarde 15 minutos.';
    } else {
        $token = trim((string)$_POST['token']);
        $senha = (string)$_POST['nova_senha'];
        $senha2 = (string)$_POST['nova_senha2'];
        
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

// GET: validar token e mostrar form
$token = trim((string)($_GET['token'] ?? ''));
if (strlen($token) === 64 && ctype_xdigit($token)) {
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
} else {
    $msg_erro = 'Token não fornecido.';
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
    .login-box { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.3); width: 100%; max-width: 440px; overflow: hidden; }
    .login-header { background: linear-gradient(135deg, #2563eb, #1e40af); color: #fff; padding: 2rem; text-align: center; }
    .login-header h1 { font-size: 1.4rem; margin: 0; }
    .login-body { padding: 2rem; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-size: .9rem; color: #1e293b; margin-bottom: .4rem; font-weight: 500; }
    .form-group input { width: 100%; padding: .75rem 1rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; }
    .form-group input:focus { outline: none; border-color: #2563eb; }
    .btn-login { width: 100%; padding: .85rem; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
    .btn-login:hover { background: #1e40af; }
    .msg { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .95rem; }
    .msg.ok { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
    .msg.err { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .info { background: #dbeafe; color: #1e40af; padding: .75rem 1rem; border-radius: 8px; font-size: .9rem; margin-bottom: 1rem; }
    .footer-link { text-align: center; padding: 1rem; color: #64748b; font-size: .85rem; }
    .footer-link a { color: #2563eb; text-decoration: none; }
  </style>
</head>
<body class="login-page">
  <div class="login-box">
    <div class="login-header">
      <h1>🔑 Redefinir Senha</h1>
    </div>
    <div class="login-body">
      <?php if ($msg_ok): ?>
        <div class="msg ok">✅ <?= htmlspecialchars($msg_ok) ?></div>
        <a href="login.php" class="btn-login" style="display:block; text-align:center; text-decoration:none;">Ir para o Login</a>
      <?php else: ?>
        <?php if ($msg_erro): ?>
          <div class="msg err">⚠️ <?= htmlspecialchars($msg_erro) ?></div>
        <?php endif; ?>
        
        <?php if ($token_valido): ?>
          <div class="info">
            👤 Definir nova senha para: <strong><?= htmlspecialchars($user_nome) ?></strong>
          </div>
          <form method="POST" autocomplete="off">
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
          <p style="color:#64748b; text-align:center;">
            Solicite um novo link de reset ao administrador do sistema.
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <div class="footer-link">
      <a href="login.php">← Voltar para o login</a>
    </div>
  </div>
</body>
</html>
