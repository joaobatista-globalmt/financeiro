<?php
/**
 * Gerador de hashes bcrypt para o seed.sql
 *
 * Uso: php database/gerar-hashes.php
 *
 * Substitui os placeholders no seed.sql por hashes bcrypt reais
 * para a senha "senha123".
 */

declare(strict_types=1);

$senhaPadrao = 'senha123';
$hash = password_hash($senhaPadrao, PASSWORD_BCRYPT, ['cost' => 10]);

echo "Hash gerado para senha '{$senhaPadrao}':\n";
echo $hash . "\n\n";

// Atualiza seed.sql
$seedFile = __DIR__ . '/seed.sql';
$conteudo = file_get_contents($seedFile);

$placeholder = '$2y$10$placeholder_hash_senha123_xxxxxxxxxxxxxxxxxxxxxxx';
$conteudoAtualizado = str_replace($placeholder, $hash, $conteudo);

file_put_contents($seedFile, $conteudoAtualizado);

echo "✓ seed.sql atualizado em {$seedFile}\n";
echo "  Substituições: " . substr_count($conteudo, $placeholder) . " placeholders\n\n";
echo "Agora você pode executar o seed.sql no banco de dados.\n";
echo "ATENÇÃO: troque a senha 'senha123' de todos os usuários no primeiro acesso!\n";