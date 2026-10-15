<?php
declare(strict_types=1);

namespace Klusmaat\Lokaal;

/** Zet alleen indexeerbare lokale pagina's in de WordPress-sitemap en houdt privépagina's en ruis eruit. */
final class Sitemap extends \WP_Sitemaps_Provider
{
    public function __construct()
    {
        $this->name = 'lokaal';
        $this->object_type = 'lokaal';
    }

    public static function haken(): void
    {
        add_action('wp_sitemaps_init', static function (): void {
            wp_register_sitemap_provider('lokaal', new self());
        });
        add_filter('wp_sitemaps_add_provider', static fn ($p, string $naam) => in_array($naam, ['users', 'taxonomies'], true) ? false : $p, 10, 2);
        add_filter('wp_sitemaps_posts_query_args', [self::class, 'uitsluiten']);
    }

    /** @param array<string,mixed> $args */
    public static function uitsluiten(array $args): array
    {
        $ids = [Pagina::sjabloonId()];
        $paginas = get_option('klusmaat_paginas', []);
        foreach (['werkbon', 'mijn_klus', 'inloggen', 'dashboard'] as $k) {
            $ids[] = is_array($paginas) ? (int) ($paginas[$k] ?? 0) : 0;
        }
        $args['post__not_in'] = array_values(array_unique(array_merge((array) ($args['post__not_in'] ?? []), array_filter($ids))));
        return $args;
    }

    /** @return list<array{loc:string}> */
    private function alle(): array
    {
        $uit = [];
        foreach (array_keys(Gegevens::BEROEPEN) as $b) {
            if (Gegevens::indexeerbaar($b, null)[0]) {
                $uit[] = ['loc' => Pagina::url($b)];
            }
            foreach (Gegevens::plaatsen() as $p) {
                if (Gegevens::indexeerbaar($b, $p['slug'])[0]) {
                    $uit[] = ['loc' => Pagina::url($b, $p['slug'])];
                }
            }
        }
        return $uit;
    }

    public function get_url_list($page_num, $object_subtype = ''): array
    {
        return array_slice($this->alle(), ((int) $page_num - 1) * 2000, 2000);
    }

    public function get_max_num_pages($object_subtype = ''): int
    {
        return max(1, (int) ceil(count($this->alle()) / 2000));
    }
}
