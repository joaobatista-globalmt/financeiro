<?php
/**
 * Layout: header comum
 *
 * Incluído por layout() no Helper.php.
 * Variáveis disponíveis: $titulo (string), $flash (array)
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo ?? 'Financeiro') ?> - Financeiro</title>
    <link rel="stylesheet" href="assets/financeiro.css">
</head>
<body>
    <?php if (!empty($flash)): ?>
        <div id="flash-container">
            <?php foreach ($flash as $f): ?>
                <div class="flash flash-<?= htmlspecialchars($f['tipo']) ?>">
                    <?= htmlspecialchars($f['msg']) ?>
                    <button type="button" class="flash-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endforeach; ?>
        </div>
        <script>
            setTimeout(() => {
                document.querySelectorAll('.flash').forEach(el => el.remove());
            }, 5000);
        </script>
    <?php endif; ?>
    <main class="content">