<?php
/**
 * Plugin Name: Klusmaat Core
 * Description: Kern van Klusmaat: klussen, klussers, credits en logboek. Alleen Nederland.
 * Version: 0.2.0
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Text Domain: klusmaat
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $klasse): void {
    $voor = 'Klusmaat\\Core\\';
    if (str_starts_with($klasse, $voor)) {
        $pad = __DIR__ . '/src/' . str_replace('\\', '/', substr($klasse, strlen($voor))) . '.php';
        if (is_file($pad)) {
            require $pad;
        }
    }
});

if (PHP_VERSION_ID < 80100) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>Klusmaat Core vraagt PHP 8.1 of nieuwer.</p></div>';
    });
    return;
}

/** Bouwt de kern op met de instellingen uit WordPress. */
function klusmaat_kern(): \Klusmaat\Core\Kern
{
    return \Klusmaat\Core\Wp\Plugin::kern();
}

register_activation_hook(__FILE__, [\Klusmaat\Core\Wp\Plugin::class, 'activeer']);
register_deactivation_hook(__FILE__, [\Klusmaat\Core\Wp\Plugin::class, 'deactiveer']);
\Klusmaat\Core\Wp\Plugin::start();
