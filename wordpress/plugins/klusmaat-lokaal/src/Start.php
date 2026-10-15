<?php
declare(strict_types=1);

namespace Klusmaat\Lokaal;

final class Start
{
    public const VERSIE = '0.1.0';
    private const OPTIE_VERSIE = 'klusmaat_lokaal_versie';

    public static function start(): void
    {
        add_action('plugins_loaded', [self::class, 'opstarten'], 20);
    }

    public static function opstarten(): void
    {
        if (!class_exists(\Klusmaat\Core\Wp\Plugin::class)) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>Klusmaat Lokaal heeft de plugin Klusmaat Core nodig en doet niets zonder.</p></div>';
            });
            return;
        }
        add_action('init', [Pagina::class, 'regels'], 6);
        Pagina::haken();
        Sitemap::haken();
        if (is_admin()) {
            Beheer::haken();
        }
        if (get_option(self::OPTIE_VERSIE) !== self::VERSIE) {
            add_action('init', [self::class, 'activeer'], 20);
        }
    }

    public static function activeer(): void
    {
        Pagina::regels();
        if (!Pagina::sjabloonId()) {
            wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Lokaal', 'post_name' => Pagina::SJABLOON, 'post_content' => '']);
        }
        flush_rewrite_rules(false);
        update_option(self::OPTIE_VERSIE, self::VERSIE, false);
    }

    public static function deactiveer(): void
    {
        flush_rewrite_rules(false);
    }
}
