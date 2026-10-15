<?php
declare(strict_types=1);

namespace Klusmaat\Lokaal;

use Klusmaat\Blog\Artikelen;
use Klusmaat\Core\Wp\Plugin as Kern;
use Klusmaat\Core\Wp\WpCatalogus;

/** Routes, inhoud, titel, canonical, robots en schema van /<vakgebied>/ en /<vakgebied>/<plaats>/. */
final class Pagina
{
    public const SJABLOON = 'lokaal';

    public static function haken(): void
    {
        add_filter('query_vars', static fn (array $v): array => array_merge($v, ['km_lp', 'km_pl']));
        add_action('wp', [self::class, 'controle'], 1);
        add_filter('redirect_canonical', [self::class, 'geenOmleiding']);
        add_filter('the_content', [self::class, 'inhoud'], 9);
        add_filter('pre_get_document_title', [self::class, 'titel'], 20);
        add_filter('body_class', [self::class, 'klasse']);
        add_filter('wp_robots', [self::class, 'robots'], 20);
        add_action('wp_head', [self::class, 'kop'], 1);
        add_action('wp_head', [self::class, 'stijl'], 30);
        remove_action('wp_head', 'rel_canonical');
        add_action('wp_head', [self::class, 'canonical'], 5);
    }

    public static function regels(): void
    {
        $b = implode('|', array_map('preg_quote', array_keys(Gegevens::BEROEPEN)));
        add_rewrite_rule('^(' . $b . ')/?$', 'index.php?pagename=' . self::SJABLOON . '&km_lp=$matches[1]', 'top');
        add_rewrite_rule('^(' . $b . ')/([a-z0-9-]+)/?$', 'index.php?pagename=' . self::SJABLOON . '&km_lp=$matches[1]&km_pl=$matches[2]', 'top');
    }

    public static function sjabloonId(): int
    {
        $p = get_page_by_path(self::SJABLOON);
        return $p ? (int) $p->ID : 0;
    }

    /** @return array{b:string,pl:?array}|null Het onderwerp van deze aanvraag, of null als dit geen lokale pagina is. */
    private static function onderwerp(): ?array
    {
        $b = (string) get_query_var('km_lp');
        if ($b === '' || !isset(Gegevens::BEROEPEN[$b])) {
            return null;
        }
        $pl = (string) get_query_var('km_pl');
        return ['b' => $b, 'pl' => $pl !== '' ? Gegevens::plaats($pl) : null];
    }

    public static function geenOmleiding(mixed $naar): mixed
    {
        return get_query_var('km_lp') !== '' ? false : $naar;
    }

    /** Onbekende plaats geeft een 404 en een directe bezoek aan de sjabloonpagina gaat naar de home. */
    public static function controle(): void
    {
        global $wp_query;
        if (!is_page(self::SJABLOON) || is_admin()) {
            return;
        }
        $onderwerp = self::onderwerp();
        if ($onderwerp === null && (string) get_query_var('km_lp') === '') {
            wp_safe_redirect(home_url('/'), 301);
            exit;
        }
        if ($onderwerp === null || ((string) get_query_var('km_pl') !== '' && $onderwerp['pl'] === null)) {
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
        }
    }

    private static function actief(): ?array
    {
        return is_page(self::SJABLOON) && !is_404() ? self::onderwerp() : null;
    }

    public static function url(string $beroep, ?string $plaats = null): string
    {
        return home_url('/' . $beroep . '/' . ($plaats !== null ? $plaats . '/' : ''));
    }

    private static function kop1(array $o): string
    {
        $naam = Gegevens::BEROEPEN[$o['b']][0];
        return $o['pl'] ? $naam . ' in ' . $o['pl']['naam'] : $naam . ' in Roosendaal en Bergen op Zoom';
    }

    public static function titel(string $t): string
    {
        $o = self::actief();
        return $o ? self::kop1($o) . ' | Klusmaat' : $t;
    }

    public static function klasse(array $k): array
    {
        if (self::actief()) {
            $k[] = 'klusmaat-pagina';
            $k[] = 'klusmaat-lokaal';
        }
        return $k;
    }

    public static function robots(array $r): array
    {
        $o = self::actief();
        if ($o) {
            [$mag] = Gegevens::indexeerbaar($o['b'], $o['pl']['slug'] ?? null);
            if (!$mag) {
                $r['noindex'] = true;
                $r['follow'] = true;
                unset($r['max-image-preview']);
            }
        } elseif (is_page(self::SJABLOON) || is_404()) {
            $r['noindex'] = true;
        }
        return $r;
    }

    public static function canonical(): void
    {
        $o = self::actief();
        if ($o) {
            echo '<link rel="canonical" href="' . esc_url(self::url($o['b'], $o['pl']['slug'] ?? null)) . '">' . "\n";
        } elseif (is_singular()) {
            rel_canonical();
        }
    }

    private static function beschrijving(array $o): string
    {
        $opt = Gegevens::opties();
        $eigen = $o['pl'] ? (string) ($opt['combo'][$o['b'] . '|' . $o['pl']['slug']] ?? '') : (string) ($opt['beroep'][$o['b']] ?? '');
        $eigen = trim((string) preg_replace('/\s+/', ' ', $eigen));
        if ($eigen !== '') {
            return mb_strlen($eigen) > 155 ? rtrim(mb_substr($eigen, 0, 152)) . '...' : $eigen;
        }
        $naam = mb_strtolower(Gegevens::BEROEPEN[$o['b']][0]);
        return 'Een ' . $naam . ' nodig' . ($o['pl'] ? ' in ' . $o['pl']['naam'] : ' in de regio') . '? Zet je klus op de werkbon, dan nemen goedgekeurde klussers uit de buurt contact met je op.';
    }

    public static function kop(): void
    {
        $o = self::actief();
        if (!$o) {
            return;
        }
        echo '<meta name="description" content="' . esc_attr(self::beschrijving($o)) . '">' . "\n";
        $naam = Gegevens::BEROEPEN[$o['b']][0];
        $kruimels = [['Home', home_url('/')], [$naam, self::url($o['b'])]];
        if ($o['pl']) {
            $kruimels[] = [$o['pl']['naam'], self::url($o['b'], $o['pl']['slug'])];
        }
        $lijst = [];
        foreach ($kruimels as $i => [$n, $u]) {
            $lijst[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $n, 'item' => $u];
        }
        $url = self::url($o['b'], $o['pl']['slug'] ?? null);
        $graaf = [
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'BreadcrumbList', 'itemListElement' => $lijst],
                ['@type' => 'WebPage', '@id' => $url . '#pagina', 'url' => $url, 'name' => self::kop1($o), 'inLanguage' => 'nl-NL', 'description' => self::beschrijving($o)],
                [
                    '@type' => 'Service', 'name' => self::kop1($o), 'serviceType' => $naam, 'url' => $url,
                    'areaServed' => $o['pl'] ? ['@type' => 'City', 'name' => $o['pl']['naam']] : [['@type' => 'City', 'name' => 'Roosendaal'], ['@type' => 'City', 'name' => 'Bergen op Zoom']],
                    'provider' => ['@type' => 'Organization', 'name' => 'Klusmaat', 'url' => home_url('/')],
                ],
            ],
        ];
        echo '<script type="application/ld+json">' . wp_json_encode($graaf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
    }

    public static function stijl(): void
    {
        if (!self::actief()) {
            return;
        }
        echo '<style id="km-lokaal">.km-lokaal{max-width:var(--km-breedte-smal,860px);margin:0 auto;padding:24px 16px 56px;color:var(--km-inkt,#161B26)}'
            . '.km-lokaal h1{font-size:clamp(2rem,5vw,3rem);line-height:1.1;margin:.4em 0 .5em;text-wrap:balance}.km-lokaal h2{font-size:1.5rem;margin:1.8em 0 .6em}'
            . '.km-lokaal .kl-kruimel{font-size:.9rem;color:var(--km-potlood,#4B5260)}.km-lokaal .kl-kruimel a{color:inherit}.km-lokaal .kl-lead{font-size:1.2rem;max-width:42em}'
            . '.km-lokaal .kl-lijst{display:flex;flex-wrap:wrap;gap:8px;padding:0;margin:0;list-style:none}.km-lokaal .kl-lijst a{display:block;padding:8px 14px;border:2px solid var(--km-inkt,#161B26);text-decoration:none;color:inherit;background:var(--km-papier,#FCFCF9)}'
            . '.km-lokaal .kl-lijst a:hover,.km-lokaal .kl-lijst a:focus-visible{background:var(--km-markeer,#F5DC00)}.km-lokaal .kl-cta{margin:1.6em 0}.km-lokaal .kl-blok{background:var(--km-doorslag-geel,#F4E8A0);padding:16px 20px;margin:1.6em 0}'
            . '.km-lokaal ol{padding-left:1.2em}.km-lokaal p{max-width:42em}</style>' . "\n";
    }

    private static function werkbonUrl(array $o): string
    {
        $soort = Gegevens::soort($o['b']);
        return Kern::url('werkbon', array_filter(['klus' => $soort['slug'] ?? '', 'plaats' => $o['pl']['id'] ?? 0]));
    }

    private static function alinea(string $tekst): string
    {
        $h = '';
        foreach (preg_split('/\R{2,}/', trim($tekst)) ?: [] as $a) {
            $h .= '<p>' . nl2br(esc_html(trim($a))) . '</p>';
        }
        return $h;
    }

    public static function inhoud(string $inh): string
    {
        $o = self::actief();
        if (!$o || !in_the_loop() || !is_main_query()) {
            return $inh;
        }
        $opt = Gegevens::opties();
        $b = $o['b'];
        $naam = Gegevens::BEROEPEN[$b][0];
        $pl = $o['pl'];
        $soort = Gegevens::soort($b);
        $h = '<div class="km-lokaal"><nav class="kl-kruimel" aria-label="Kruimelpad"><a href="' . esc_url(home_url('/')) . '">Home</a> &rsaquo; ';
        $h .= $pl ? '<a href="' . esc_url(self::url($b)) . '">' . esc_html($naam) . '</a> &rsaquo; ' . esc_html($pl['naam']) : esc_html($naam);
        $h .= '</nav><h1>' . esc_html(self::kop1($o)) . '</h1>';
        $h .= '<p class="kl-lead">' . esc_html(Gegevens::BEROEPEN[$b][2]) . '</p>';

        $eigen = $pl ? (string) ($opt['combo'][$b . '|' . $pl['slug']] ?? '') : (string) ($opt['beroep'][$b] ?? '');
        $h .= self::alinea($eigen);
        $h .= '<p class="kl-cta"><a class="km-knop" href="' . esc_url(self::werkbonUrl($o)) . '">Plaats je klus op de werkbon</a></p>';

        if ($soort !== null) {
            $h .= '<h2>Welke ' . esc_html(mb_strtolower($naam)) . 'klussen kun je laten doen?</h2><ul class="kl-lijst">';
            foreach (WpCatalogus::categorieen($soort['id']) as $c) {
                $h .= '<li><a href="' . esc_url(Kern::url('werkbon', array_filter(['klus' => $c->slug, 'plaats' => $pl['id'] ?? 0]))) . '">' . esc_html($c->name) . '</a></li>';
            }
            $h .= '</ul>';
        }

        $k = $pl ? Gegevens::klussers($b, $pl['id']) : 0;
        if ($k > 0) {
            $h .= '<div class="kl-blok"><strong>' . $k . ' goedgekeurde ' . ($k === 1 ? 'klusser is' : 'klussers zijn') . ' actief in ' . esc_html($pl['naam']) . '</strong> voor dit vak. Maximaal ' . (int) \Klusmaat\Core\Wp\Instellingen::alle()['max_klussers'] . ' van hen bellen je op. Jij kiest met wie je verder gaat.</div>';
        }
        $over = $pl ? (string) ($opt['plaats'][$pl['slug']] ?? '') : '';
        if ($over !== '') {
            $h .= '<h2>Klussen in ' . esc_html($pl['naam']) . '</h2>' . self::alinea($over);
        }

        $h .= '<h2>Zo werkt het</h2><ol><li>Vul de werkbon in met je klus, plaats en contactgegevens.</li><li>Goedgekeurde klussers uit de buurt zien je klus, zonder je gegevens.</li><li>Wie de klus oppakt, belt je. Jij kiest met wie je verder gaat.</li></ol>';

        $h .= self::artikelen($soort);

        $links = [];
        foreach (Gegevens::plaatsen() as $p) {
            if ($pl && $p['slug'] === $pl['slug']) {
                continue;
            }
            if (Gegevens::indexeerbaar($b, $p['slug'])[0]) {
                $links[] = '<li><a href="' . esc_url(self::url($b, $p['slug'])) . '">' . esc_html($naam . ' in ' . $p['naam']) . '</a></li>';
            }
        }
        if ($links !== []) {
            $h .= '<h2>' . ($pl ? 'Ook in de buurt' : 'Kies je plaats') . '</h2><ul class="kl-lijst">' . implode('', $links) . '</ul>';
        }
        return $h . '</div>';
    }

    private static function artikelen(?array $soort): string
    {
        if ($soort === null || !class_exists(Artikelen::class)) {
            return '';
        }
        $posts = get_posts([
            'post_type' => Artikelen::TYPE, 'posts_per_page' => 3, 'post_status' => 'publish',
            'tax_query' => [['taxonomy' => WpCatalogus::KLUSTYPE, 'field' => 'term_id', 'terms' => $soort['id'], 'include_children' => true]],
        ]);
        if ($posts === []) {
            return '';
        }
        $h = '<h2>Liever zelf aan de slag?</h2><ul class="kl-lijst">';
        foreach ($posts as $p) {
            $h .= '<li><a href="' . esc_url((string) get_permalink($p)) . '">' . esc_html(get_the_title($p)) . '</a></li>';
        }
        return $h . '</ul>';
    }
}
