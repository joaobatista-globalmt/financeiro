<?php
/**
 * _criar_filho_sucesso.php
 *
 * Página intermediária renderizada pelos controllers de fornecedor/cliente/categoria
 * quando voltam de uma janela filha (target="_blank") aberta pelo form de Conta a Pagar/Receber.
 *
 * Comportamento:
 *  1. Manda postMessage para window.opener (a janela pai com o form de origem)
 *  2. Fecha a janela após 500ms
 *
 * Parâmetros GET esperados:
 *   - select : nome do <select> na página pai (ex: fornecedor_id, cliente_id, categoria_id)
 *   - id     : ID do novo registro
 *   - label  : Nome/razão social exibido
 *   - tipo   : "fornecedor" | "cliente" | "categoria"  (usado na mensagem)
 *
 * Segurança: nada é executado no servidor, só exibe uma página de status.
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$select = preg_match('/^[a-z0-9_]+$/', (string)($_GET['select'] ?? '')) ? $_GET['select'] : '';
$id     = (int)($_GET['id'] ?? 0);
$label  = (string)($_GET['label'] ?? '');
$tipo   = preg_match('/^[a-z]+$/', (string)($_GET['tipo'] ?? '')) ? $_GET['tipo'] : '';

if ($select === '' || $id <= 0 || $label === '' || $tipo === '') {
    http_response_code(400);
    echo '<h1>Parâmetros inválidos</h1>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Cadastrado com sucesso</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f9fafb;
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; margin: 0; padding: 20px;
        }
        .box {
            background: white; padding: 32px 40px; border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1); text-align: center; max-width: 420px;
        }
        .ok { font-size: 48px; margin-bottom: 8px; }
        h1 { font-size: 20px; color: #16a34a; margin: 0 0 8px 0; }
        p { color: #6b7280; font-size: 14px; margin: 0 0 16px 0; }
        .destaque { color: #111827; font-weight: 600; }
        button {
            background: #2563eb; color: white; border: none; padding: 8px 18px;
            border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 500;
        }
        button:hover { background: #1d4ed8; }
    </style>
</head>
<body>
    <div class="box">
        <div class="ok">✅</div>
        <h1><?= ucfirst(htmlspecialchars($tipo)) ?> cadastrado!</h1>
        <p>
            <span class="destaque"><?= htmlspecialchars($label) ?></span><br>
            foi selecionado na tela anterior.
        </p>
        <p><small>Esta janela fechará automaticamente em 1 segundo…</small></p>
        <button onclick="window.close()">Fechar agora</button>
    </div>

    <script>
    (function () {
        var payload = {
            tipo:   <?= json_encode($tipo) ?>,
            select: <?= json_encode($select) ?>,
            id:     <?= json_encode($id) ?>,
            label:  <?= json_encode($label) ?>
        };
        // Adiciona timestamp pra evitar duplicatas no listener
        payload._ts = Date.now();

        // === 1) localStorage: funciona cross-window mesmo se opener for null ===
        try {
            if (window.opener && !window.opener.closed && window.opener.localStorage) {
                window.opener.localStorage.setItem('rapido_novo_cadastro', JSON.stringify(payload));
                // Limpa após 5s pra não acumular lixo
                setTimeout(function () {
                    try { window.opener.localStorage.removeItem('rapido_novo_cadastro'); } catch (e) {}
                }, 5000);
            } else {
                // Fallback: grava no nosso próprio localStorage e avisa o usuário
                localStorage.setItem('rapido_novo_cadastro', JSON.stringify(payload));
            }
        } catch (e) {
            console.error('[rapido] localStorage falhou:', e);
        }

        // === 2) postMessage: redundância ===
        if (window.opener && !window.opener.closed) {
            try {
                window.opener.postMessage(payload, '*');
            } catch (e) {
                console.error('postMessage falhou:', e);
            }
        } else {
            document.querySelector('p small').textContent = 'Janela pai não detectada. Use o botão abaixo para voltar e atualizar.';
        }

        // === 3) Fecha a janela após 1s ===
        setTimeout(function () {
            try { window.close(); } catch (e) { /* se não conseguir fechar, mostra o botão */ }
        }, 1000);
    })();
    </script>
</body>
</html>
