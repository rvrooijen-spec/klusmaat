<?php
/**
 * Plugin Name: Klusmaat Zelf doen
 * Description: Blog met stappenplannen en tips om een klus zelf te doen. Aanvulling op Klusmaat Core.
 * Version: 0.1.0
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Requires Plugins: klusmaat-core
 * Text Domain: klusmaat-zelfdoen
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $klasse): void {
    $voor = 'Klusmaat\\Zelfdoen\\';
    if (str_starts_with($klasse, $voor)) {
        $pad = __DIR__ . '/src/' . str_replace('\\', '/', substr($klasse, strlen($voor))) . '.php';
        if (is_file($pad)) {
            require $pad;
        }
    }
});

if (PHP_VERSION_ID >= 80100) {
    register_activation_hook(__FILE__, [\Klusmaat\Zelfdoen\Start::class, 'activeer']);
    register_deactivation_hook(__FILE__, [\Klusmaat\Zelfdoen\Start::class, 'deactiveer']);
    \Klusmaat\Zelfdoen\Start::start();
}
