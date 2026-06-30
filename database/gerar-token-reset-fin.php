<?php
/**
 * gerar-token-reset-fin.php (CLI)
 * 
 * Uso: php gerar-token-reset-fin.php <email>
 * Exemplo: php gerar-token-reset-fin.php joao.batista@globalmt.com.br
 * 
 * Gera token de reset de 30min. Imprime a URL completa (apenas 1 vez).
 */

// Carrega env vars do PHP-FPM
$conf = @file_get_contents('/etc/php/8.2/fpm/pool.d/www.conf');
if ($conf) {
    preg_match_all('/^env\[([^\]]+)\] = (.+)$/m', $conf, $m);
    foreach ($m[1] as $i => $k) {
        putenv("$k=" . trim($m[2][$i]));
        $_ENV[$k] = trim($m[2][$i]);
    }
}

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser rodado via CLI.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Uso: php gerar-token-reset-fin.php <email>\n");
    exit(1);
}

$email = trim($argv[1]);

require __DIR__ . '/../src/lib/Database.php';

try {
    $pdo = Database::getConnection();
} catch (Exception $e) {
    fwrite(STDERR, "Erro de conexão: " . $e->getMessage() . "\n");
    exit(1);
}

$stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    fwrite(STDERR, "Usuário '$email' não encontrado ou inativo.\n");
    exit(1);
}

// Invalidar tokens anteriores não-usados
$pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
    ->execute([$user['id']]);

// Gerar token novo
$token = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $token);
$expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

$ins = $pdo->prepare("
    INSERT INTO password_resets (user_id, token_hash, expires_at, ip_request)
    VALUES (?, ?, ?, ?)
");
$ins->execute([$user['id'], $token_hash, $expires, 'CLI:joao-srv']);

// URLs (token aparece APENAS aqui, apenas uma vez)
$urls = [
    "http://192.168.70.45/financeiro/reset.php?token={$token}",
];

echo "===========================================\n";
echo " TOKEN DE RESET FINANCEIRO (30 min)\n";
echo "===========================================\n";
echo "Usuário : {$user['nome']} (id={$user['id']})\n";
echo "Email   : {$user['email']}\n";
echo "Expira  : $expires (servidor)\n";
echo "URL 1   : {$urls[0]}\n";
echo "===========================================\n";
echo " Envie APENAS a URL ao usuário.\n";
echo " Não compartilhe com terceiros.\n";
echo " Não logue esta saída em lugar público.\n";
echo "===========================================\n";
