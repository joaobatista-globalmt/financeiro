<?php
/**
 * fix-seed-hashes.php
 *
 * Regenera hashes bcrypt (cost 10) pra todos os usuários do seed que estão
 * com placeholder quebrado (o seed.sql original tem um hash estático que NÃO
 * bate com "senha123"). Após rodar, todos voltam a logar com senha123.
 *
 * USO:
 *   php database/fix-seed-hashes.php                (regenera pra "senha123")
 *   php database/fix-seed-hashes.php novasenha123   (gera hash pra outra senha)
 *
 * Por padrão, se o hash atual JÁ bate com a senha alvo, o usuário é pulado
 * (não mexe no que está funcionando).
 *
 * IMPORTANTE: rodar via CLI com o user que tem acesso ao banco. Não usa
 * framework — é só PDO direto.
 *
 * Pré-requisito: variáveis DB_FIN_* já configuradas no ambiente (PHP-FPM
 * www.conf tem elas, CLI pega do /etc/php/8.2/cli/envvars ou do .env).
 */

declare(strict_types=1);

// Lê senha alvo do argumento ou usa default do seed
$senhaAlvo = $argv[1] ?? 'senha123';

if (strlen($senhaAlvo) < 6) {
    fwrite(STDERR, "ERRO: senha muito curta (mínimo 6 caracteres)\n");
    exit(1);
}

// Carrega credenciais (tenta .env local; fallback pro www.conf do FPM)
$host = getenv('DB_FIN_HOST') ?: '127.0.0.1';
$port = getenv('DB_FIN_PORT') ?: '3306';
$db   = getenv('DB_FIN_DB')   ?: 'financeiro';
$user = getenv('DB_FIN_USER') ?: 'financeiro_app';
$pass = getenv('DB_FIN_PASS') ?: 'financeiro_app_2026';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "ERRO conexao: " . $e->getMessage() . "\n");
    exit(1);
}

$novoHash = password_hash($senhaAlvo, PASSWORD_BCRYPT, ['cost' => 10]);

echo "=============================================================\n";
echo "Fix Seed Hashes - Sistema Financeiro\n";
echo "=============================================================\n";
echo "Banco: $user@$host:$port/$db\n";
echo "Senha alvo: " . str_repeat('*', strlen($senhaAlvo)) . "  (" . strlen($senhaAlvo) . " chars)\n";
echo "Hash novo: " . substr($novoHash, 0, 30) . "...\n";
echo "-------------------------------------------------------------\n";

$stmt = $pdo->query('SELECT id, nome, email, senha_hash FROM usuarios ORDER BY id');
$atualizados = 0;
$pulados = 0;
$quebrados = 0;

foreach ($stmt as $r) {
    $id = $r['id'];
    $nome = $r['nome'];
    $email = $r['email'];
    $hashAtual = $r['senha_hash'];

    $bate = password_verify($senhaAlvo, $hashAtual);

    if ($bate) {
        echo sprintf("[OK   ] ID %2d | %-30s | %s | ja bate\n", $id, $email, substr($hashAtual, 0, 25));
        $pulados++;
        continue;
    }

    // Não bate → atualiza
    $upd = $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
    $upd->execute([$novoHash, $id]);

    if ($upd->rowCount() > 0) {
        echo sprintf("[FIX  ] ID %2d | %-30s | %s -> %s\n",
            $id, $email,
            substr($hashAtual, 0, 20) . '...',
            substr($novoHash, 0, 20) . '...'
        );
        $atualizados++;
    } else {
        echo sprintf("[ERRO ] ID %2d | %-30s | update nao afetou linha\n", $id, $email);
        $quebrados++;
    }
}

echo "-------------------------------------------------------------\n";
echo "Resultado: $atualizados atualizados, $pulados ja ok, $quebrados com erro\n";
echo "=============================================================\n";

exit($quebrados > 0 ? 1 : 0);