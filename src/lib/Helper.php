<?php
/**
 * Helper - Funções globais e autoloader
 *
 * Define constantes e funções utilitárias comuns em todo o sistema.
 * Inclui autoloader PSR-4 simplificado para classes em src/lib e src/controllers.
 */

declare(strict_types=1);

// Constantes de path
// __DIR__ = .../src/lib  ->  dirname(__DIR__) = .../src  ->  dirname(dirname(__DIR__)) = .../
define('FINANCEIRO_ROOT', dirname(__DIR__, 2));
define('FINANCEIRO_SRC', FINANCEIRO_ROOT . '/src');
define('FINANCEIRO_PUBLIC', FINANCEIRO_ROOT . '/public');

// Autoloader simples
spl_autoload_register(function (string $classe): void {
    $paths = [
        FINANCEIRO_SRC . '/lib/' . $classe . '.php',
        FINANCEIRO_SRC . '/controllers/' . $classe . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

/**
 * Inclui uma view a partir de src/views/<caminho>.php
 * Variáveis locais ficam disponíveis no escopo da view.
 *
 * @param string $caminho Caminho relativo ex: 'auth/login.php'
 * @param array $vars Variáveis a extrair no escopo
 */
function view(string $caminho, array $vars = []): void
{
    $arquivo = FINANCEIRO_SRC . '/views/' . $caminho;

    if (!file_exists($arquivo)) {
        throw new RuntimeException("View não encontrada: $caminho");
    }

    extract($vars, EXTR_SKIP);
    require $arquivo;
}

/**
 * Inclui o layout (header + content + footer) envolvendo uma view.
 *
 * @param string $titulo
 * @param string $view
 * @param array $vars
 */
function layout(string $titulo, string $view, array $vars = []): void
{
    $usuario = Auth::user();
    $flash = Flash::get();

    extract($vars, EXTR_SKIP);

    require FINANCEIRO_SRC . '/views/layout/header.php';
    require FINANCEIRO_SRC . '/views/layout/navbar.php';
    require FINANCEIRO_SRC . '/views/' . $view;
    require FINANCEIRO_SRC . '/views/layout/footer.php';
}

/**
 * Helper para redirect com flash.
 */
function redirect(string $url, ?string $flashTipo = null, ?string $flashMsg = null): void
{
    if ($flashTipo && $flashMsg) {
        Flash::set($flashTipo, $flashMsg);
    }
    header('Location: ' . $url);
    exit;
}

/**
 * Converte data BR (dd/mm/yyyy) para ISO (yyyy-mm-dd).
 */
function dataBrParaIso(?string $data): ?string
{
    if (!$data) return null;
    $partes = explode('/', $data);
    if (count($partes) !== 3) return null;
    return $partes[2] . '-' . str_pad($partes[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($partes[0], 2, '0', STR_PAD_LEFT);
}

/**
 * Converte data ISO (yyyy-mm-dd) para BR (dd/mm/yyyy).
 */
function dataIsoParaBr(?string $data): ?string
{
    if (!$data) return null;
    $partes = explode('-', $data);
    if (count($partes) !== 3) return null;
    return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
}