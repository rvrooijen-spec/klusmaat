<?php
/**
 * Plugin Name: Klusmaat Blog
 * Description: Blog met stappenplannen en tips om een klus zelf te doen. Aanvulling op Klusmaat Core.
 * Version: 0.1.1
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Requires Plugins: klusmaat-core
 * Text Domain: klusmaat-blog
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $klasse): void {
    $voor = 'Klusmaat\\Blog\\';
    if (str_starts_with($klasse, $voor)) {
        $pad = __DIR__ . '/src/' . str_replace('\\', '/', substr($klasse, strlen($voor))) . '.php';
        if (is_file($pad)) {
            require $pad;
        }
    }
});

if (PHP_VERSION_ID >= 80100) {
    register_activation_hook(__FILE__, [\Klusmaat\Blog\Start::class, 'activeer']);
    register_deactivation_hook(__FILE__, [\Klusmaat\Blog\Start::class, 'deactiveer']);
    \Klusmaat\Blog\Start::start();
}
