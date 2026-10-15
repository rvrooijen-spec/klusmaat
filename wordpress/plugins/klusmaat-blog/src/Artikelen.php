<?php
declare(strict_types=1);

namespace Klusmaat\Blog;

use Klusmaat\Core\Wp\Plugin as Kern;
use Klusmaat\Core\Wp\WpCatalogus;

/**
 * Blog "Zelf doen": artikelen waarmee mensen een klus zelf kunnen oppakken.
 * Twee soorten: een stappenplan (met benodigdheden, moeilijkheid en afbeeldingen per stap) en een tipartikel.
 * Het beheer vult de velden in; deze klasse bewaart ze, toont ze en geeft zoekmachines de gegevens mee.
 */
final class Artikelen
{
    public const TYPE = 'klusmaat_artikel';
    public const SOORTEN = ['stappenplan' => 'Stappenplan', 'tip' => 'Tips'];
    public const MOEILIJKHEID = [
        1 => ['Makkelijk', 'Kan iedereen, geen ervaring nodig.'],
        2 => ['Gemiddeld', 'Handig met gereedschap helpt. Neem de tijd.'],
        3 => ['Lastig', 'Alleen als je dit eerder hebt gedaan.'],
    ];
    private const NONCE = 'km_artikel';

    public static function haken(): void
    {
        add_action('add_meta_boxes_' . self::TYPE, [self::class, 'metaBox']);
        add_action('save_post_' . self::TYPE, [self::class, 'bewaar'], 10, 2);
        add_action('admin_enqueue_scripts', [self::class, 'adminScripts']);
        // Klassieke editor: de velden voor stappen en benodigdheden staan dan direct onder de tekst in beeld.
        add_filter('use_block_editor_for_post_type', static fn ($aan, $type) => $type === self::TYPE ? false : $aan, 10, 2);
        add_filter('the_content', [self::class, 'inhoud'], 20);
        add_action('wp_head', [self::class, 'gestructureerdeGegevens']);
        add_shortcode('klusmaat_artikelen', [self::class, 'overzicht']);
        add_filter('manage_' . self::TYPE . '_posts_columns', [self::class, 'kolommen']);
        add_action('manage_' . self::TYPE . '_posts_custom_column', [self::class, 'kolomWaarde'], 10, 2);
    }

    public static function registreer(): void
    {
        register_post_type(self::TYPE, [
            'labels' => [
                'name' => 'Blog', 'singular_name' => 'Artikel', 'menu_name' => 'Blog', 'add_new' => 'Nieuw artikel', 'add_new_item' => 'Nieuw artikel',
                'edit_item' => 'Artikel bewerken', 'all_items' => 'Alle artikelen', 'search_items' => 'Artikelen zoeken', 'not_found' => 'Nog geen artikelen.',
            ],
            'public' => true, 'has_archive' => false, 'show_in_rest' => true, 'menu_icon' => 'dashicons-hammer', 'menu_position' => 27,
            'supports' => ['title', 'editor', 'excerpt', 'thumbnail'], 'rewrite' => ['slug' => 'blog', 'with_front' => false],
            'taxonomies' => [WpCatalogus::KLUSTYPE],
        ]);
        register_taxonomy_for_object_type(WpCatalogus::KLUSTYPE, self::TYPE);
    }

    // Opslag

    /** Maakt van ruwe invoer nette gegevens. Zuiver, zonder database. */
    public static function schoon(array $p): array
    {
        $regels = static function ($tekst): array {
            $r = is_array($tekst) ? $tekst : (preg_split('/\R/u', (string) $tekst) ?: []);
            $r = array_map(static fn ($x) => trim(sanitize_text_field((string) $x)), $r);
            return array_slice(array_values(array_filter($r, static fn ($x) => $x !== '')), 0, 40);
        };
        $stappen = [];
        foreach ((array) ($p['stappen'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $titel = trim(sanitize_text_field((string) ($s['titel'] ?? '')));
            $tekst = trim(sanitize_textarea_field((string) ($s['tekst'] ?? '')));
            if ($titel === '' && $tekst === '') {
                continue;
            }
            $stappen[] = ['titel' => $titel, 'tekst' => $tekst, 'afbeelding' => absint($s['afbeelding'] ?? 0), 'alt' => trim(sanitize_text_field((string) ($s['alt'] ?? '')))];
        }
        $soort = (string) ($p['soort'] ?? '');
        return [
            'soort' => isset(self::SOORTEN[$soort]) ? $soort : 'stappenplan',
            'moeilijkheid' => min(3, max(1, (int) ($p['moeilijkheid'] ?? 1))),
            'minuten' => min(10080, max(0, (int) ($p['minuten'] ?? 0))),
            'kosten' => trim(sanitize_text_field((string) ($p['kosten'] ?? ''))),
            'gereedschap' => $regels($p['gereedschap'] ?? ''),
            'materiaal' => $regels($p['materiaal'] ?? ''),
            'stappen' => array_slice($stappen, 0, 30),
            'tips' => $regels($p['tips'] ?? ''),
            'klusser_als' => $regels($p['klusser_als'] ?? ''),
        ];
    }

    /** Leest de gegevens van een artikel, altijd compleet. */
    public static function gegevens(int $id): array
    {
        $g = get_post_meta($id, '_km_artikel', true);
        return self::schoon(is_array($g) ? $g : []);
    }

    public static function bewaar(int $id, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['km_artikel_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['km_artikel_nonce'])), self::NONCE) || !current_user_can('edit_post', $id)) {
            return;
        }
        $g = self::schoon(wp_unslash((array) ($_POST['km'] ?? [])));
        update_post_meta($id, '_km_artikel', $g);
        update_post_meta($id, '_km_soort', $g['soort']); // aparte sleutel, zodat het overzicht erop kan filteren
    }

    // Het invulscherm

    public static function metaBox(): void
    {
        add_meta_box('km_artikel', 'Klusgegevens en stappenplan', [self::class, 'toonMetaBox'], self::TYPE, 'normal', 'high');
    }

    public static function adminScripts(string $haak): void
    {
        $scherm = get_current_screen();
        if (in_array($haak, ['post.php', 'post-new.php'], true) && $scherm && $scherm->post_type === self::TYPE) {
            wp_enqueue_media();
        }
    }

    private static function stapRij(string $i, array $s): string
    {
        $img = (int) ($s['afbeelding'] ?? 0);
        $thumb = $img ? (string) wp_get_attachment_image($img, [120, 120], false, ['class' => 'km-ad-thumb']) : '';
        $naam = static fn (string $v): string => 'km[stappen][' . $i . '][' . $v . ']';
        return '<li class="km-ad-stap"><div class="km-ad-stapkop"><strong class="km-ad-nr"></strong>'
            . '<span><button type="button" class="button km-ad-omhoog" aria-label="Stap omhoog">Omhoog</button> <button type="button" class="button km-ad-omlaag" aria-label="Stap omlaag">Omlaag</button> <button type="button" class="button km-ad-weg">Verwijderen</button></span></div>'
            . '<p><label>Titel van de stap<br><input type="text" class="widefat" name="' . esc_attr($naam('titel')) . '" value="' . esc_attr((string) ($s['titel'] ?? '')) . '" maxlength="120"></label></p>'
            . '<p><label>Wat doe je?<br><textarea class="widefat" rows="3" name="' . esc_attr($naam('tekst')) . '">' . esc_textarea((string) ($s['tekst'] ?? '')) . '</textarea></label></p>'
            . '<div class="km-ad-beeld"><div class="km-ad-voorbeeld">' . $thumb . '</div><input type="hidden" class="km-ad-id" name="' . esc_attr($naam('afbeelding')) . '" value="' . $img . '">'
            . '<button type="button" class="button km-ad-kies">Afbeelding kiezen</button> <button type="button" class="button km-ad-wis"' . ($img ? '' : ' hidden') . '>Afbeelding weghalen</button>'
            . '<p><label>Beschrijving van de afbeelding (voor slechtzienden)<br><input type="text" class="widefat" name="' . esc_attr($naam('alt')) . '" value="' . esc_attr((string) ($s['alt'] ?? '')) . '" maxlength="160"></label></p></div></li>';
    }

    public static function toonMetaBox(\WP_Post $post): void
    {
        $g = self::gegevens($post->ID);
        wp_nonce_field(self::NONCE, 'km_artikel_nonce');
        echo '<style>.km-ad-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px 20px}.km-ad-stappen{list-style:none;margin:0;padding:0}.km-ad-stap{border:1px solid #c3c4c7;padding:12px 16px;margin:0 0 12px;background:#fff}.km-ad-stapkop{display:flex;justify-content:space-between;align-items:center;gap:8px}.km-ad-nr{font-size:16px}.km-ad-thumb{display:block;height:auto;max-width:120px;margin-bottom:8px}.km-ad-box h4{margin:20px 0 6px}.km-ad-box textarea{width:100%}</style>';
        echo '<div class="km-ad-box"><div class="km-ad-grid">';
        echo '<p><strong>Soort artikel</strong><br>';
        foreach (self::SOORTEN as $k => $naam) {
            echo '<label style="margin-right:16px"><input type="radio" name="km[soort]" value="' . esc_attr($k) . '"' . checked($g['soort'], $k, false) . '> ' . esc_html($naam) . '</label>';
        }
        echo '</p><p><label><strong>Moeilijkheid</strong><br><select name="km[moeilijkheid]">';
        foreach (self::MOEILIJKHEID as $n => [$naam, $uitleg]) {
            echo '<option value="' . $n . '"' . selected($g['moeilijkheid'], $n, false) . '>' . esc_html($naam . ': ' . $uitleg) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label><strong>Tijd in minuten</strong><br><input type="number" min="0" max="10080" name="km[minuten]" value="' . (int) $g['minuten'] . '" class="small-text"> <span class="description">120 = twee uur</span></label></p>';
        echo '<p><label><strong>Kosten (indicatie)</strong><br><input type="text" name="km[kosten]" class="regular-text" maxlength="80" value="' . esc_attr($g['kosten']) . '" placeholder="Ongeveer 40 tot 80 euro"></label></p></div>';
        echo '<p class="description">Kies rechts bij "Soorten klussen" de soort klus. Het artikel verwijst dan met één klik naar de werkbon met die klus al ingevuld.</p>';
        echo '<div class="km-ad-grid"><div><h4>Gereedschap (één per regel)</h4><textarea rows="6" name="km[gereedschap]">' . esc_textarea(implode("\n", $g['gereedschap'])) . '</textarea></div>';
        echo '<div><h4>Materiaal (één per regel)</h4><textarea rows="6" name="km[materiaal]">' . esc_textarea(implode("\n", $g['materiaal'])) . '</textarea></div></div>';
        echo '<h4>Stappenplan</h4><p class="description">Alleen voor een stappenplan. Houd elke stap klein: één handeling, één afbeelding.</p><ol class="km-ad-stappen" id="km-ad-stappen">';
        foreach ($g['stappen'] as $i => $s) {
            echo self::stapRij((string) $i, $s);
        }
        echo '</ol><p><button type="button" class="button button-primary" id="km-ad-nieuw">Stap toevoegen</button></p>';
        echo '<template id="km-ad-sjabloon">' . self::stapRij('__i__', []) . '</template>';
        echo '<div class="km-ad-grid"><div><h4>Tips (één per regel)</h4><textarea rows="5" name="km[tips]">' . esc_textarea(implode("\n", $g['tips'])) . '</textarea></div>';
        echo '<div><h4>Laat het aan een klusser over als (één per regel)</h4><textarea rows="5" name="km[klusser_als]">' . esc_textarea(implode("\n", $g['klusser_als'])) . '</textarea></div></div></div>';
        echo '<script>(function(){var l=document.getElementById("km-ad-stappen"),t=document.getElementById("km-ad-sjabloon"),teller=1000;if(!l||!t){return}
function nummer(){var r=l.children;for(var i=0;i<r.length;i++){r[i].querySelector(".km-ad-nr").textContent="Stap "+(i+1);r[i].querySelectorAll("[name]").forEach(function(e){e.name=e.name.replace(/km\[stappen\]\[[^\]]*\]/,"km[stappen]["+i+"]")})}}
document.getElementById("km-ad-nieuw").addEventListener("click",function(){var d=document.createElement("div");d.innerHTML=t.innerHTML.replace(/__i__/g,String(teller++));l.appendChild(d.firstElementChild);nummer()});
l.addEventListener("click",function(e){var b=e.target.closest("button");if(!b){return}var r=b.closest(".km-ad-stap");
if(b.classList.contains("km-ad-weg")){r.remove();nummer()}
else if(b.classList.contains("km-ad-omhoog")&&r.previousElementSibling){l.insertBefore(r,r.previousElementSibling);nummer()}
else if(b.classList.contains("km-ad-omlaag")&&r.nextElementSibling){l.insertBefore(r.nextElementSibling,r);nummer()}
else if(b.classList.contains("km-ad-wis")){r.querySelector(".km-ad-id").value="0";r.querySelector(".km-ad-voorbeeld").innerHTML="";b.hidden=true}
else if(b.classList.contains("km-ad-kies")&&window.wp&&wp.media){var k=wp.media({title:"Afbeelding voor deze stap",button:{text:"Gebruik deze afbeelding"},multiple:false,library:{type:"image"}});k.on("select",function(){var a=k.state().get("selection").first().toJSON();r.querySelector(".km-ad-id").value=a.id;var u=(a.sizes&&a.sizes.thumbnail?a.sizes.thumbnail.url:a.url);r.querySelector(".km-ad-voorbeeld").innerHTML="<img class=\"km-ad-thumb\" alt=\"\" src=\""+u+"\">";r.querySelector(".km-ad-wis").hidden=false;var alt=r.querySelector("input[name$=\"[alt]\"]");if(alt&&!alt.value){alt.value=a.alt||""}});k.open()}});
nummer()})();</script>';
    }

    public static function kolommen(array $k): array
    {
        $nieuw = [];
        foreach ($k as $sleutel => $naam) {
            $nieuw[$sleutel] = $naam;
            if ($sleutel === 'title') {
                $nieuw['km_soort'] = 'Soort';
                $nieuw['km_moeilijk'] = 'Moeilijkheid';
            }
        }
        return $nieuw;
    }

    public static function kolomWaarde(string $kolom, int $id): void
    {
        $g = self::gegevens($id);
        if ($kolom === 'km_soort') {
            echo esc_html(self::SOORTEN[$g['soort']]);
        } elseif ($kolom === 'km_moeilijk') {
            echo $g['soort'] === 'tip' ? '-' : esc_html(self::MOEILIJKHEID[$g['moeilijkheid']][0]);
        }
    }

    // Weergave

    private static function vakjes(int $niveau): string
    {
        $h = '<span class="km-moeilijk" aria-hidden="true">';
        for ($i = 1; $i <= 3; $i++) {
            $h .= '<span class="km-vakje-m' . ($i <= $niveau ? ' aan' : '') . '"></span>';
        }
        return $h . '</span>';
    }

    private static function duur(int $min): string
    {
        if ($min <= 0) {
            return '';
        }
        if ($min < 60) {
            return $min . ' minuten';
        }
        $u = intdiv($min, 60);
        $r = $min % 60;
        return $u . ' uur' . ($r ? ' en ' . $r . ' minuten' : '');
    }

    /** Eerste soort klus die aan het artikel hangt, voor de link naar de werkbon. */
    private static function klusterm(int $id): ?\WP_Term
    {
        $t = wp_get_post_terms($id, WpCatalogus::KLUSTYPE);
        return (is_array($t) && $t !== [] && $t[0] instanceof \WP_Term) ? $t[0] : null;
    }

    private static function lijst(string $kop, array $items, string $klasse = 'km-checklijst'): string
    {
        if ($items === []) {
            return '';
        }
        $h = '<div><h3>' . esc_html($kop) . '</h3><ul class="' . esc_attr($klasse) . '">';
        foreach ($items as $i) {
            $h .= '<li>' . esc_html($i) . '</li>';
        }
        return $h . '</ul></div>';
    }

    public static function inhoud(string $inhoud): string
    {
        if (!is_singular(self::TYPE) || !in_the_loop() || !is_main_query()) {
            return $inhoud;
        }
        $id = (int) get_the_ID();
        $g = self::gegevens($id);
        $stap = $g['soort'] === 'stappenplan';
        $h = '<div class="km-artikel">';
        $lead = has_excerpt($id) ? get_the_excerpt($id) : '';
        if ($lead !== '') {
            $h .= '<p class="km-inleiding">' . esc_html($lead) . '</p>';
        }
        $h .= '<dl class="km-feiten"><div><dt>Soort</dt><dd>' . esc_html(self::SOORTEN[$g['soort']]) . '</dd></div>';
        if ($stap) {
            [$mn, $mu] = self::MOEILIJKHEID[$g['moeilijkheid']];
            $h .= '<div><dt>Moeilijkheid</dt><dd>' . self::vakjes($g['moeilijkheid']) . ' ' . esc_html($mn) . '<span class="km-feit-hulp">' . esc_html($mu) . '</span></dd></div>';
            if ($g['minuten'] > 0) {
                $h .= '<div><dt>Tijd</dt><dd>' . esc_html(self::duur($g['minuten'])) . '</dd></div>';
            }
            if ($g['kosten'] !== '') {
                $h .= '<div><dt>Kosten</dt><dd>' . esc_html($g['kosten']) . '</dd></div>';
            }
        }
        $h .= '</dl>';
        $h .= '<div class="km-artikel-tekst">' . $inhoud . '</div>';
        if ($stap && ($g['gereedschap'] !== [] || $g['materiaal'] !== [])) {
            $h .= '<section class="km-artikel-sectie"><h2>Dit heb je nodig</h2><div class="km-benodigd">' . self::lijst('Gereedschap', $g['gereedschap']) . self::lijst('Materiaal', $g['materiaal']) . '</div></section>';
        }
        if ($stap && $g['stappen'] !== []) {
            $h .= '<section class="km-artikel-sectie"><h2>Stap voor stap</h2><ol class="km-artikel-stappen">';
            foreach ($g['stappen'] as $n => $s) {
                $h .= '<li id="stap-' . ($n + 1) . '"><span class="km-cijfer" aria-hidden="true">' . ($n + 1) . '</span><div class="km-stap-inhoud">';
                $h .= '<h3><span class="screen-reader-text">Stap ' . ($n + 1) . ': </span>' . esc_html($s['titel']) . '</h3>';
                if ($s['tekst'] !== '') {
                    $h .= '<p>' . nl2br(esc_html($s['tekst'])) . '</p>';
                }
                if ($s['afbeelding'] > 0) {
                    $img = wp_get_attachment_image($s['afbeelding'], 'large', false, ['loading' => 'lazy', 'alt' => $s['alt']]);
                    $h .= $img !== '' ? '<figure class="km-stapbeeld">' . $img . '</figure>' : '';
                }
                $h .= '</div></li>';
            }
            $h .= '</ol></section>';
        }
        if ($g['tips'] !== []) {
            $h .= '<section class="km-artikel-sectie"><h2>Tips</h2><ul class="km-tips">';
            foreach ($g['tips'] as $t) {
                $h .= '<li>' . esc_html($t) . '</li>';
            }
            $h .= '</ul></section>';
        }
        $h .= self::klusserblok($id, $g);
        return $h . '<p class="km-terug"><a href="' . esc_url(Start::url()) . '">Alle artikelen over zelf klussen</a></p></div>';
    }

    private static function klusserblok(int $id, array $g): string
    {
        $term = self::klusterm($id);
        $link = Kern::url('werkbon');
        if ($term) {
            $link = add_query_arg('klus', $term->slug, $link);
        }
        $h = '<aside class="km-artikel-klusser"><h2>Liever een klusser?</h2>';
        if ($g['klusser_als'] !== []) {
            $h .= '<p>Laat het aan een klusser over als:</p><ul class="km-tips">';
            foreach ($g['klusser_als'] as $t) {
                $h .= '<li>' . esc_html($t) . '</li>';
            }
            $h .= '</ul>';
        }
        $h .= '<p>Zet je klus op de werkbon. Goedgekeurde klussers uit je buurt pakken hem op. Dat kost jou niets.</p>';
        return $h . '<a class="km-knop" href="' . esc_url($link) . '">Zet de klus op de werkbon</a></aside>';
    }

    // Overzicht

    /** [klusmaat_artikelen aantal="12" compact="0"] */
    public static function overzicht($atts = []): string
    {
        $a = shortcode_atts(['aantal' => '12', 'compact' => '0', 'soort' => '', 'kop' => 'Eerst zelf proberen?'], (array) $atts);
        $compact = $a['compact'] === '1';
        $soort = sanitize_key($compact ? (string) $a['soort'] : (string) ($_GET['soort'] ?? ''));
        $soort = isset(self::SOORTEN[$soort]) ? $soort : '';
        $klus = sanitize_title((string) ($_GET['klus'] ?? ''));
        $pagina = max(1, absint($_GET['pagina'] ?? 1));
        $args = ['post_type' => self::TYPE, 'post_status' => 'publish', 'posts_per_page' => max(1, min(48, (int) $a['aantal'])), 'paged' => $compact ? 1 : $pagina, 'orderby' => 'date', 'order' => 'DESC'];
        if ($soort !== '') {
            $args['meta_query'] = [['key' => '_km_soort', 'value' => $soort]];
        }
        if (!$compact && $klus !== '') {
            $args['tax_query'] = [['taxonomy' => WpCatalogus::KLUSTYPE, 'field' => 'slug', 'terms' => $klus, 'include_children' => true]];
        }
        $q = new \WP_Query($args);
        $basis = Start::url();
        if ($compact && !$q->have_posts()) {
            return '';
        }
        $h = $compact ? '<div class="km-artikelrijen-blok"><h2 class="km-sectiekop">' . esc_html((string) $a['kop']) . '</h2>' : '<div class="km-pagina km-overzicht">';
        if (!$compact) {
            $h .= '<h1 class="km-paginatitel">Blog</h1><p class="km-inleiding">Stappenplannen en tips van klussers. Doe het zelf als het kan, en bel een klusser als het niet meer leuk is.</p>';
            $chip = static function (string $tekst, string $url, bool $actief): string {
                return '<a class="km-chip' . ($actief ? ' actief' : '') . '" href="' . esc_url($url) . '"' . ($actief ? ' aria-current="true"' : '') . '>' . esc_html($tekst) . '</a>';
            };
            $h .= '<nav class="km-filters" aria-label="Filter artikelen"><div class="km-chips">' . $chip('Alles', add_query_arg(array_filter(['klus' => $klus]), $basis), $soort === '');
            foreach (self::SOORTEN as $k => $n) {
                $h .= $chip($n === 'Tips' ? 'Tips' : 'Stappenplannen', add_query_arg(array_filter(['soort' => $k, 'klus' => $klus]), $basis), $soort === $k);
            }
            $h .= '</div><div class="km-chips">' . $chip('Alle klussen', add_query_arg(array_filter(['soort' => $soort]), $basis), $klus === '');
            foreach (WpCatalogus::soorten() as $t) {
                $h .= $chip($t->name, add_query_arg(array_filter(['soort' => $soort, 'klus' => $t->slug]), $basis), $klus === $t->slug);
            }
            $h .= '</div></nav>';
        }
        if (!$q->have_posts()) {
            $h .= '<p>Er staan nog geen artikelen' . (($soort !== '' || $klus !== '') && !$compact ? ' bij deze keuze' : '') . '. Kom snel terug.</p>';
        } else {
            $h .= '<ul class="km-artikelrijen">';
            while ($q->have_posts()) {
                $q->the_post();
                $pid = (int) get_the_ID();
                $g = self::gegevens($pid);
                $beeld = '';
                $thumb = get_post_thumbnail_id($pid);
                if (!$thumb) {
                    foreach ($g['stappen'] as $s) {
                        if ($s['afbeelding'] > 0) {
                            $thumb = $s['afbeelding'];
                            break;
                        }
                    }
                }
                if ($thumb) {
                    $beeld = '<span class="km-rij-beeld">' . wp_get_attachment_image((int) $thumb, 'medium', false, ['loading' => 'lazy', 'alt' => '']) . '</span>';
                }
                $meta = [self::SOORTEN[$g['soort']] === 'Tips' ? 'Tips' : 'Stappenplan'];
                if ($g['soort'] === 'stappenplan') {
                    $meta[] = self::MOEILIJKHEID[$g['moeilijkheid']][0];
                    if ($g['minuten'] > 0) {
                        $meta[] = self::duur($g['minuten']);
                    }
                }
                $h .= '<li><a class="km-artikelrij" href="' . esc_url(get_permalink($pid)) . '">' . $beeld . '<span class="km-rij-tekst"><span class="km-rij-naam">' . esc_html(get_the_title($pid)) . '</span>';
                $uit = get_the_excerpt($pid);
                if ($uit !== '') {
                    $h .= '<span class="km-rij-uitleg">' . esc_html(wp_trim_words($uit, 28)) . '</span>';
                }
                $h .= '<span class="km-rij-hulp">' . ($g['soort'] === 'stappenplan' ? self::vakjes($g['moeilijkheid']) . ' ' : '') . esc_html(implode(' · ', $meta)) . '</span></span></a></li>';
            }
            $h .= '</ul>';
            wp_reset_postdata();
            if (!$compact && $q->max_num_pages > 1) {
                $h .= '<nav class="km-paginering" aria-label="Pagina\'s">' . paginate_links(['base' => add_query_arg('pagina', '%#%', $basis), 'format' => '', 'current' => $pagina, 'total' => (int) $q->max_num_pages, 'add_args' => array_filter(['soort' => $soort, 'klus' => $klus])]) . '</nav>';
            }
            if ($compact) {
                $h .= '<p><a class="km-knop km-knop-rand" href="' . esc_url($soort !== '' ? add_query_arg('soort', $soort, $basis) : $basis) . '">' . ($soort === 'tip' ? 'Alle tips' : ($soort === 'stappenplan' ? 'Alle stappenplannen' : 'Alle artikelen')) . '</a></p>';
            }
        }
        return $h . '</div>';
    }

    // Zoekmachines

    public static function gestructureerdeGegevens(): void
    {
        if (!is_singular(self::TYPE)) {
            return;
        }
        $id = (int) get_queried_object_id();
        $g = self::gegevens($id);
        if ($g['soort'] !== 'stappenplan' || $g['stappen'] === []) {
            return;
        }
        $data = ['@context' => 'https://schema.org', '@type' => 'HowTo', 'name' => get_the_title($id), 'description' => get_the_excerpt($id)];
        if ($g['minuten'] > 0) {
            $data['totalTime'] = 'PT' . $g['minuten'] . 'M';
        }
        if ($g['gereedschap'] !== []) {
            $data['tool'] = array_map(static fn ($t) => ['@type' => 'HowToTool', 'name' => $t], $g['gereedschap']);
        }
        if ($g['materiaal'] !== []) {
            $data['supply'] = array_map(static fn ($t) => ['@type' => 'HowToSupply', 'name' => $t], $g['materiaal']);
        }
        foreach ($g['stappen'] as $n => $s) {
            $stap = ['@type' => 'HowToStep', 'position' => $n + 1, 'name' => $s['titel'] !== '' ? $s['titel'] : 'Stap ' . ($n + 1), 'text' => $s['tekst'] !== '' ? $s['tekst'] : $s['titel'], 'url' => get_permalink($id) . '#stap-' . ($n + 1)];
            if ($s['afbeelding'] > 0 && ($u = wp_get_attachment_image_url($s['afbeelding'], 'large'))) {
                $stap['image'] = $u;
            }
            $data['step'][] = $stap;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>' . "\n";
    }
}
