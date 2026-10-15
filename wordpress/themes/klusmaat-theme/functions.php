<?php
/**
 * Klusmaat-thema. Alle uiterlijk komt uit theme.json en assets/css/klusmaat.css.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('KLUSMAAT_THEME_VERSION', wp_get_theme()->get('Version') ?: '0.1.0');

add_action('after_setup_theme', static function (): void {
    add_theme_support('wp-block-styles');
    add_theme_support('responsive-embeds');
    add_editor_style('assets/css/klusmaat.css');
});

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'klusmaat',
        get_theme_file_uri('assets/css/klusmaat.css'),
        [],
        KLUSMAAT_THEME_VERSION
    );
});

add_action('init', static function (): void {
    register_block_pattern_category('klusmaat', ['label' => 'Klusmaat']);
});

/*
 * Updates via GitHub-releases. Op GitHub bouwt een workflow bij een tag "theme-v*" een zip van dit
 * thema. WordPress toont daarna bij Weergave > Thema's "Nu bijwerken". Is de repository privé, zet dan
 * in wp-config.php: define('KLUSMAAT_GITHUB_TOKEN', '...'); (alleen-lezen token).
 */
$klusmaat_puc = get_theme_file_path('inc/load-v5p7.php');
if (is_readable($klusmaat_puc)) {
    require_once $klusmaat_puc;
    $klusmaat_updates = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/rvrooijen-spec/klusmaat/',
        __FILE__,
        'klusmaat-theme'
    );
    $klusmaat_updates->getVcsApi()->enableReleaseAssets('/klusmaat-theme\.zip$/');
    if (defined('KLUSMAAT_GITHUB_TOKEN') && KLUSMAAT_GITHUB_TOKEN !== '') {
        $klusmaat_updates->setAuthentication(KLUSMAAT_GITHUB_TOKEN);
    }
}
