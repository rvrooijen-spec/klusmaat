<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

use Klusmaat\Core\Kern;
use Klusmaat\Core\Schema;

/** Verbindt de kern met WordPress: haken, taxonomieën, pagina's, rollen en de dagelijkse controle. */
final class Plugin
{
    public const VERSIE = '0.2.1';
    public const ROL_KLUSSER = 'klusser';
    public const BEHEER = 'klusmaat_beheer';

    /** Pagina's die de plugin zelf aanmaakt: sleutel => [titel, slug, shortcode of tekst]. */
    private const PAGINAS = [
        'werkbon' => ['Werkbon', 'werkbon', '[klusmaat_werkbon]'],
        'mijn_klus' => ['Mijn klus', 'mijn-klus', '[klusmaat_mijn_klus]'],
        'klusser_worden' => ['Klusser worden', 'klusser-worden', '[klusmaat_klusser_aanmelden]'],
        'inloggen' => ['Inloggen', 'inloggen', '[klusmaat_inloggen]'],
        'dashboard' => ['Mijn klussen', 'mijn-klussen', '[klusmaat_klusser_dashboard]'],
        'privacy' => ['Privacy', 'privacy', 'Deze pagina volgt voordat Klusmaat open gaat. Hier komt te staan welke gegevens we bewaren, waarom en hoe lang.'],
        'voorwaarden' => ['Voorwaarden', 'voorwaarden', 'Deze pagina volgt voordat Klusmaat open gaat. Hier komen de voorwaarden voor klanten en klussers.'],
        'contact' => ['Contact', 'contact', 'Deze pagina volgt voordat Klusmaat open gaat. Hier komt te staan hoe je Klusmaat bereikt.'],
    ];

    /** Pagina's met persoonlijke gegevens: niet bewaren, niet indexeren. */
    private const PRIVE = ['werkbon', 'mijn_klus', 'inloggen', 'dashboard'];

    private static ?Kern $kern = null;

    public static function start(): void
    {
        add_action('init', [self::class, 'registreer'], 5);
        add_action('init', [Klant::class, 'haken']);
        add_action('init', [Klusser::class, 'haken']);
        add_action('template_redirect', [self::class, 'privekoppen'], 1);
        add_filter('wp_robots', [self::class, 'robots']);
        add_filter('show_admin_bar', [self::class, 'adminbalk']);
        add_filter('body_class', [self::class, 'lichaamsklasse']);
        add_filter('language_attributes', [self::class, 'taal']);
        add_action('admin_init', [self::class, 'houKlussersUitBeheer']);
        add_action('klusmaat_dagelijks', [self::class, 'dagelijks']);
        add_action('wp_enqueue_scripts', [self::class, 'scripts']);
        Privacy::haken();
        if (is_admin()) {
            Beheer::haken();
        }
        add_action('plugins_loaded', [self::class, 'bijwerken']);
    }

    public static function kern(): Kern
    {
        if (self::$kern === null) {
            global $wpdb;
            self::$kern = new Kern(new \Klusmaat\Core\WpdbDatabase($wpdb), Instellingen::config(), new WpCatalogus());
        }
        return self::$kern;
    }

    /** Zet de kern opnieuw op, bijvoorbeeld na het wijzigen van de instellingen. */
    public static function vergeetKern(): void
    {
        self::$kern = null;
    }

    public static function url(string $sleutel, array $args = []): string
    {
        $paginas = get_option('klusmaat_paginas', []);
        $id = is_array($paginas) ? (int) ($paginas[$sleutel] ?? 0) : 0;
        $url = $id > 0 ? (string) get_permalink($id) : home_url('/' . (self::PAGINAS[$sleutel][1] ?? '') . '/');
        return $args === [] ? $url : add_query_arg($args, $url);
    }

    public static function isPagina(string $sleutel): bool
    {
        $paginas = get_option('klusmaat_paginas', []);
        $id = is_array($paginas) ? (int) ($paginas[$sleutel] ?? 0) : 0;
        return $id > 0 && is_page($id);
    }

    /** Pagina's met een eigen kop in de inhoud krijgen een klasse, zodat het thema de paginatitel niet dubbel toont. */
    public static function lichaamsklasse(array $klassen): array
    {
        foreach (array_keys(self::PAGINAS) as $sleutel) {
            if (in_array($sleutel, self::PRIVE, true) && self::isPagina($sleutel)) {
                $klassen[] = 'klusmaat-pagina';
                break;
            }
        }
        return $klassen;
    }

    /** De site is alleen Nederlands: zoekmachines krijgen lang=\"nl-NL\", ook als WordPress op Engels staat. */
    public static function taal(string $uitvoer): string
    {
        $schoon = preg_replace('/(^|\s)lang="[^"]*"/', '', $uitvoer);
        return trim((string) $schoon) . ' lang="nl-NL"';
    }

    public static function registreer(): void
    {
        $rechten = [
            'manage_terms' => self::BEHEER, 'edit_terms' => self::BEHEER, 'delete_terms' => self::BEHEER, 'assign_terms' => self::BEHEER,
        ];
        // Verborgen berichttype: alleen nodig omdat WordPress de beheerschermen voor termen aan een type koppelt.
        register_post_type('klusmaat_klus', [
            'label' => 'Klusmaat-klussen', 'public' => false, 'show_ui' => true, 'show_in_menu' => 'klusmaat', 'show_in_rest' => false,
            'supports' => ['title'], 'capability_type' => 'post', 'capabilities' => ['create_posts' => 'do_not_allow'], 'map_meta_cap' => true,
        ]);
        register_taxonomy(WpCatalogus::PLAATS, ['klusmaat_klus'], [
            'labels' => ['name' => 'Plaatsen', 'singular_name' => 'Plaats', 'menu_name' => 'Plaatsen', 'add_new_item' => 'Nieuwe plaats'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'hierarchical' => false, 'show_admin_column' => false, 'capabilities' => $rechten,
        ]);
        register_taxonomy(WpCatalogus::KLUSTYPE, ['klusmaat_klus'], [
            'labels' => ['name' => 'Soorten klussen', 'singular_name' => 'Soort klus', 'menu_name' => 'Soorten klussen', 'add_new_item' => 'Nieuwe soort of categorie', 'parent_item' => 'Hoort onder'],
            'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'hierarchical' => true, 'capabilities' => $rechten,
        ]);
        register_term_meta(WpCatalogus::KLUSTYPE, 'volgorde', ['type' => 'integer', 'single' => true, 'show_in_rest' => false]);
        add_shortcode('klusmaat_werkbon', [Klant::class, 'werkbon']);
        add_shortcode('klusmaat_mijn_klus', [Klant::class, 'mijnKlus']);
        add_shortcode('klusmaat_klusser_aanmelden', [Klusser::class, 'aanmelden']);
        add_shortcode('klusmaat_inloggen', [Klusser::class, 'inloggen']);
        add_shortcode('klusmaat_klusser_dashboard', [Klusser::class, 'dashboard']);
    }

    public static function scripts(): void
    {
        // De thema-CSS bevat de opmaak. Dit kleine script is er alleen voor de knop "Kopieer link".
        if (self::isPagina('werkbon')) {
            wp_register_script('klusmaat-kopieer', '', [], Plugin::VERSIE, true);
            wp_enqueue_script('klusmaat-kopieer');
            wp_add_inline_script('klusmaat-kopieer', "document.addEventListener('click',function(e){var b=e.target.closest('[data-kopieer]');if(!b){return;}var v=document.getElementById(b.getAttribute('data-kopieer'));if(!v){return;}v.select();var k=function(){b.textContent='Gekopieerd';};if(navigator.clipboard){navigator.clipboard.writeText(v.value).then(k,function(){document.execCommand('copy');k();});}else{document.execCommand('copy');k();}});");
        }
    }

    public static function privekoppen(): void
    {
        foreach (self::PRIVE as $sleutel) {
            if (self::isPagina($sleutel)) {
                Beveiliging::privekoppen();
                return;
            }
        }
    }

    /** @param array<string,mixed> $robots */
    public static function robots(array $robots): array
    {
        foreach (self::PRIVE as $sleutel) {
            if (self::isPagina($sleutel)) {
                $robots['noindex'] = true;
                $robots['nofollow'] = true;
                unset($robots['max-image-preview']);
            }
        }
        return $robots;
    }

    public static function adminbalk(bool $tonen): bool
    {
        return self::isKlusser() ? false : $tonen;
    }

    public static function isKlusser(?\WP_User $u = null): bool
    {
        $u = $u ?? wp_get_current_user();
        return $u->exists() && in_array(self::ROL_KLUSSER, (array) $u->roles, true) && !user_can($u, self::BEHEER);
    }

    public static function houKlussersUitBeheer(): void
    {
        if (self::isKlusser() && !wp_doing_ajax() && !(defined('DOING_CRON') && DOING_CRON)) {
            $actie = (string) ($_REQUEST['action'] ?? '');
            if (!str_starts_with($actie, 'klusmaat_')) {
                wp_safe_redirect(self::url('dashboard'));
                exit;
            }
        }
    }

    /** Dagelijks: oude gesloten klussen anonimiseren en de gegevens controleren. */
    public static function dagelijks(): void
    {
        $i = Instellingen::alle();
        try {
            self::kern()->aanvragen->anonimiseerOudeKlussen((int) $i['bewaar_dagen']);
            $meldingen = self::kern()->controle->uitvoeren();
        } catch (\Throwable $e) {
            $meldingen = ['De dagelijkse controle gaf een fout: ' . $e->getMessage()];
        }
        update_option('klusmaat_controle', ['tijd' => time(), 'meldingen' => $meldingen], false);
        if ($meldingen !== []) {
            do_action('klusmaat_controle_mislukt', $meldingen);
        }
    }

    /** Draait na een update van de plugin: tabellen bijwerken als de versie is veranderd. */
    public static function bijwerken(): void
    {
        if (get_option('klusmaat_db_versie') !== self::VERSIE && current_user_can('activate_plugins')) {
            self::activeer();
        }
    }

    /** Wat er bij activatie en na een update moet gebeuren. */
    public static function activeer(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (Schema::mysql($wpdb->prefix, $wpdb->get_charset_collate()) as $sql) {
            dbDelta($sql);
        }
        self::triggers();
        self::rollen();
        self::paginas();
        self::zaaiTermen();
        if (!get_option(Instellingen::OPTIE)) {
            add_option(Instellingen::OPTIE, Instellingen::STANDAARD, '', false);
        }
        if (!wp_next_scheduled('klusmaat_dagelijks')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'klusmaat_dagelijks');
        }
        if (get_option('timezone_string') === '' && get_option('gmt_offset') == 0) {
            update_option('timezone_string', 'Europe/Amsterdam');
        }
        if (get_option('permalink_structure') === '') {
            update_option('permalink_structure', '/%postname%/');
        }
        self::registreer();
        flush_rewrite_rules(false);
        update_option('klusmaat_db_versie', self::VERSIE, false);
    }

    public static function deactiveer(): void
    {
        wp_clear_scheduled_hook('klusmaat_dagelijks');
    }

    /** Triggers die boekingen en logboek alleen-toevoegen maken. Lukt dat niet, dan meldt het beheerscherm het. */
    private static function triggers(): void
    {
        global $wpdb;
        $bestaand = $wpdb->get_col($wpdb->prepare('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME LIKE %s', $wpdb->esc_like($wpdb->prefix . 'klusmaat_') . '%'));
        if (is_array($bestaand) && count($bestaand) >= 4) {
            update_option('klusmaat_triggers', 'aan', false);
            return;
        }
        $wpdb->suppress_errors(true);
        $gelukt = true;
        foreach (Schema::mysqlTriggers($wpdb->prefix) as $sql) {
            if ($wpdb->query($sql) === false) {
                $gelukt = false;
            }
        }
        $wpdb->suppress_errors(false);
        update_option('klusmaat_triggers', $gelukt ? 'aan' : 'mislukt', false);
    }

    private static function rollen(): void
    {
        add_role(self::ROL_KLUSSER, 'Klusser', ['read' => true]);
        add_role('klusmaat_beheerder', 'Klusmaat-beheerder', ['read' => true, self::BEHEER => true]);
        get_role('administrator')?->add_cap(self::BEHEER);
    }

    private static function paginas(): void
    {
        $paginas = get_option('klusmaat_paginas', []);
        $paginas = is_array($paginas) ? $paginas : [];
        foreach (self::PAGINAS as $sleutel => [$titel, $slug, $inhoud]) {
            $id = (int) ($paginas[$sleutel] ?? 0);
            if ($id > 0 && get_post_status($id) !== false) {
                continue;
            }
            $bestaand = get_page_by_path($slug);
            if ($bestaand instanceof \WP_Post) {
                $paginas[$sleutel] = $bestaand->ID;
                continue;
            }
            $tekst = str_starts_with($inhoud, '[') ? $inhoud : '<!-- wp:paragraph --><p>' . esc_html($inhoud) . '</p><!-- /wp:paragraph -->';
            $nieuw = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $titel, 'post_name' => $slug, 'post_content' => $tekst]);
            if (!is_wp_error($nieuw)) {
                $paginas[$sleutel] = (int) $nieuw;
            }
        }
        update_option('klusmaat_paginas', $paginas, false);
        update_option('blogdescription', 'Een klusser uit de buurt', true);
    }

    /** Vult de eerste keer de plaatsen en soorten klussen. Daarna beheert de beheerder ze zelf. */
    private static function zaaiTermen(): void
    {
        if (get_option('klusmaat_gezaaid')) {
            return;
        }
        register_taxonomy(WpCatalogus::PLAATS, 'klusmaat_klus', ['hierarchical' => false]);
        register_taxonomy(WpCatalogus::KLUSTYPE, 'klusmaat_klus', ['hierarchical' => true]);
        foreach (['Roosendaal', 'Bergen op Zoom', 'Halsteren', 'Wouw', 'Nispen', 'Heerle', 'Hoogerheide', 'Ossendrecht', 'Lepelstraat', 'Moerstraten'] as $plaats) {
            if (!term_exists($plaats, WpCatalogus::PLAATS)) {
                wp_insert_term($plaats, WpCatalogus::PLAATS);
            }
        }
        $soorten = [
            'Schilderwerk' => ['Binnen schilderen', 'Buiten schilderen', 'Behangen', 'Plamuren en stucwerk'],
            'Loodgieterswerk' => ['Lekkage', 'Kraan en toilet', 'Afvoer ontstoppen', 'Verwarming'],
            'Tuinwerk' => ['Snoeien en hagen', 'Bestraten', 'Gras en onderhoud', 'Schutting en tuinhuis'],
            'Elektra' => ['Lampen en stopcontacten', 'Groepenkast', 'Storing zoeken'],
            'Montage' => ['Meubels in elkaar zetten', 'Keuken en kasten', 'Planken en tv ophangen'],
        ];
        $volgorde = 1;
        foreach ($soorten as $soort => $kinderen) {
            $r = wp_insert_term($soort, WpCatalogus::KLUSTYPE);
            if (is_wp_error($r)) {
                continue;
            }
            update_term_meta((int) $r['term_id'], 'volgorde', $volgorde++);
            foreach ($kinderen as $kind) {
                wp_insert_term($kind, WpCatalogus::KLUSTYPE, ['parent' => (int) $r['term_id']]);
            }
        }
        update_option('klusmaat_gezaaid', 1, false);
    }
}
