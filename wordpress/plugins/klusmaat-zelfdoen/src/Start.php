<?php
declare(strict_types=1);

namespace Klusmaat\Zelfdoen;

/** Opstart van de child-plugin "Zelf doen". Werkt alleen als Klusmaat Core actief is. */
final class Start
{
    public const VERSIE = '0.1.0';
    private const SLUG = 'zelf-doen';

    public static function kernActief(): bool
    {
        return class_exists(\Klusmaat\Core\Wp\Plugin::class);
    }

    public static function start(): void
    {
        add_action('plugins_loaded', static function (): void {
            if (!self::kernActief()) {
                add_action('admin_notices', static function (): void {
                    echo '<div class="notice notice-error"><p><strong>Klusmaat Zelf doen</strong> heeft de plugin <strong>Klusmaat Core</strong> nodig. Activeer die eerst.</p></div>';
                });
                return;
            }
            add_action('init', [Artikelen::class, 'registreer'], 6);
            Artikelen::haken();
            add_filter('body_class', static function (array $k): array {
                $id = (int) get_option('klusmaat_zelfdoen_pagina', 0);
                if ($id > 0 && is_page($id)) {
                    $k[] = 'klusmaat-pagina'; // het overzicht heeft een eigen kop
                }
                return $k;
            });
            add_action('plugins_loaded', [self::class, 'bijwerken'], 30);
        }, 20);
    }

    public static function bijwerken(): void
    {
        if (get_option('klusmaat_zelfdoen_versie') !== self::VERSIE && current_user_can('activate_plugins')) {
            self::activeer();
        }
    }

    public static function activeer(): void
    {
        if (!self::kernActief()) {
            return;
        }
        Artikelen::registreer();
        $id = (int) get_option('klusmaat_zelfdoen_pagina', 0);
        if ($id <= 0 || get_post_status($id) === false) {
            $bestaand = get_page_by_path(self::SLUG);
            $id = $bestaand instanceof \WP_Post ? $bestaand->ID : (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Zelf doen', 'post_name' => self::SLUG, 'post_content' => '[klusmaat_artikelen]']);
            update_option('klusmaat_zelfdoen_pagina', $id, false);
        }
        flush_rewrite_rules(false);
        update_option('klusmaat_zelfdoen_versie', self::VERSIE, false);
    }

    public static function deactiveer(): void
    {
        flush_rewrite_rules(false);
    }

    /** Adres van de overzichtspagina. */
    public static function url(): string
    {
        $id = (int) get_option('klusmaat_zelfdoen_pagina', 0);
        return $id > 0 ? (string) get_permalink($id) : home_url('/' . self::SLUG . '/');
    }
}
