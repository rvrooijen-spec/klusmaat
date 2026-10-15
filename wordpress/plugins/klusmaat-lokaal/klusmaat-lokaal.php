<?php
/**
 * Plugin Name: Klusmaat Lokaal
 * Description: Vakgebied- en plaatspagina's voor Google (zoals /schilder/roosendaal/), met indexregels, sitemap en schema. Hangt aan Klusmaat Core.
 * Version: 0.1.0
 * Author: Klusmaat
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Requires Plugins: klusmaat-core
 * Text Domain: klusmaat-lokaal
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $klasse): void {
    $voor = 'Klusmaat\\Lokaal\\';
    if (str_starts_with($klasse, $voor)) {
        $pad = __DIR__ . '/src/' . str_replace('\\', '/', substr($klasse, strlen($voor))) . '.php';
        if (is_file($pad)) {
            require $pad;
        }
    }
});

if (PHP_VERSION_ID >= 80100) {
    register_activation_hook(__FILE__, [\Klusmaat\Lokaal\Start::class, 'activeer']);
    register_deactivation_hook(__FILE__, [\Klusmaat\Lokaal\Start::class, 'deactiveer']);
    \Klusmaat\Lokaal\Start::start();
}
