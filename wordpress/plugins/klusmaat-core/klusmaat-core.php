<?php
/**
 * Plugin Name: Klusmaat Core
 * Description: Kern van Klusmaat: klussen, klussers, credits en logboek. Alleen Nederland.
 * Version: 0.1.0
 * Requires PHP: 8.1
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

/** Bouwt de kern op met de instellingen uit WordPress. */
function klusmaat_kern(): \Klusmaat\Core\Kern
{
    static $kern = null;
    if ($kern === null) {
        global $wpdb;
        $config = new \Klusmaat\Core\Config(
            (bool) get_option('klusmaat_credits_aan', 1),
            max(0, (int) get_option('klusmaat_gratis_credits', 6)),
            max(0, (int) get_option('klusmaat_kosten_per_klus', 1)),
            max(1, (int) get_option('klusmaat_max_klussers', 3)),
            defined('KLUSMAAT_LOG_SECRET') ? (string) KLUSMAAT_LOG_SECRET : wp_salt('auth')
        );
        $kern = new \Klusmaat\Core\Kern(new \Klusmaat\Core\WpdbDatabase($wpdb), $config);
    }
    return $kern;
}

register_activation_hook(__FILE__, static function (): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach (\Klusmaat\Core\Schema::mysql($wpdb->prefix, $wpdb->get_charset_collate()) as $sql) {
        dbDelta($sql);
    }
    // Triggers die logboek en boekingen alleen-toevoegen maken. Lukt dit niet (rechten), dan staat
    // het in het logboek en moet de beheerder het zien; de code zelf wijzigt deze tabellen nooit.
    foreach (\Klusmaat\Core\Schema::mysqlTriggers($wpdb->prefix) as $sql) {
        $wpdb->query($sql);
    }
    add_role('klusser', 'Klusser', ['read' => true]);
    add_role('klusmaat_beheerder', 'Klusmaat-beheerder', ['read' => true, 'klusmaat_beheer' => true]);
    get_role('administrator')?->add_cap('klusmaat_beheer');
    update_option('klusmaat_db_versie', '0.1.0');
});
