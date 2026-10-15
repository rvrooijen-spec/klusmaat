<?php
/**
 * Plugin Name: Klusmaat Mail
 * Description: Alle mail van Klusmaat: bevestigingen, meldingen voor klussers en beheer, met voorbeeld, testmodus en logboek. Aanvulling op Klusmaat Core.
 * Version: 0.1.0
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * Requires Plugins: klusmaat-core
 * Text Domain: klusmaat-mail
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

spl_autoload_register(static function (string $klasse): void {
    $voor = 'Klusmaat\\Mail\\';
    if (str_starts_with($klasse, $voor)) {
        $pad = __DIR__ . '/src/' . str_replace('\\', '/', substr($klasse, strlen($voor))) . '.php';
        if (is_file($pad)) {
            require $pad;
        }
    }
});

if (PHP_VERSION_ID >= 80100) {
    register_activation_hook(__FILE__, [\Klusmaat\Mail\Start::class, 'activeer']);
    register_deactivation_hook(__FILE__, [\Klusmaat\Mail\Start::class, 'deactiveer']);
    \Klusmaat\Mail\Start::start();
}
