<?php
/**
 * Bootstrap: carrega libs, registra autoloader e inicia sessão.
 *
 * Cada arquivo em public/ segue esse padrão mínimo:
 *   require __DIR__ . '/bootstrap.php';
 *   (new XController)->metodo();
 */

declare(strict_types=1);

require __DIR__ . '/../src/lib/Helper.php';

// Autoloader já foi registrado em Helper.php
// Apenas garante que as classes do Uploader/AnexoController/Helper carregam

Auth::start();