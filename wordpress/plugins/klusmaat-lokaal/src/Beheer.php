<?php
declare(strict_types=1);

namespace Klusmaat\Lokaal;

use Klusmaat\Core\Wp\Plugin as Kern;

/** Klusmaat > Lokaal: teksten per vakgebied en plaats, en per pagina of die de index in mag. */
final class Beheer
{
    private const PAGINA = 'klusmaat-lokaal';

    public static function haken(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_post_km_lokaal_opslaan', [self::class, 'opslaan']);
    }

    public static function menu(): void
    {
        add_submenu_page('klusmaat', 'Lokaal', 'Lokaal', Kern::BEHEER, self::PAGINA, [self::class, 'scherm']);
    }

    public static function opslaan(): void
    {
        if (!current_user_can(Kern::BEHEER)) {
            wp_die('Geen toegang.', '', ['response' => 403]);
        }
        check_admin_referer('km_lokaal_opslaan');
        update_option(Gegevens::OPTIE, Gegevens::schoon(wp_unslash($_POST)), false);
        foreach (Gegevens::plaatsen() as $p) {
            foreach (array_keys(Gegevens::BEROEPEN) as $b) {
                delete_transient('km_lokaal_k_' . $b . '_' . $p['id']);
            }
        }
        wp_safe_redirect(add_query_arg(['page' => self::PAGINA, 'opgeslagen' => 1], admin_url('admin.php')));
        exit;
    }

    public static function scherm(): void
    {
        if (!current_user_can(Kern::BEHEER)) {
            wp_die('Geen toegang.', '', ['response' => 403]);
        }
        $o = Gegevens::opties();
        $inIndex = 0;
        $totaal = 0;
        foreach (array_keys(Gegevens::BEROEPEN) as $b) {
            foreach (array_merge([null], array_column(Gegevens::plaatsen(), 'slug')) as $pl) {
                $totaal++;
                $inIndex += Gegevens::indexeerbaar($b, $pl)[0] ? 1 : 0;
            }
        }
        echo '<div class="wrap"><h1>Lokale pagina\'s</h1>';
        if (!empty($_GET['opgeslagen'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Opgeslagen.</p></div>';
        }
        echo '<p><strong>' . $inIndex . ' van ' . $totaal . '</strong> pagina\'s mogen in Google. De rest staat op noindex tot er genoeg eigen tekst is en een goedgekeurde klusser voor die plaats en dat vak. Dat voorkomt dat Google de site ziet als een stapel bijna-gelijke pagina\'s.</p>';
        echo '<p>Schrijf per plaats echt eigen tekst: wat voor klussen komen daar vaak voor, wat is typisch voor de buurt, hoe werk je. Kopieer niet dezelfde tekst met een andere plaatsnaam.</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="km_lokaal_opslaan">';
        wp_nonce_field('km_lokaal_opslaan');
        echo '<p><label>Minimaal aantal eigen woorden per plaatspagina <input type="number" name="min_woorden" value="' . (int) $o['min_woorden'] . '" min="40" max="1000" style="width:5em"></label> ';
        echo '<label>per vakgebied-pagina <input type="number" name="min_woorden_hub" value="' . (int) $o['min_woorden_hub'] . '" min="20" max="1000" style="width:5em"></label></p>';

        echo '<h2>Plaatsen</h2><p class="description">Tekst over de plaats zelf. Verschijnt op alle vakgebieden van die plaats, telt niet mee voor de woordtelling.</p>';
        foreach (Gegevens::plaatsen() as $p) {
            echo '<p><label><strong>' . esc_html($p['naam']) . '</strong><br><textarea class="large-text" rows="2" name="plaats[' . esc_attr($p['slug']) . ']">' . esc_textarea((string) ($o['plaats'][$p['slug']] ?? '')) . '</textarea></label></p>';
        }
        foreach (Gegevens::BEROEPEN as $b => $def) {
            $hub = Gegevens::indexeerbaar($b, null);
            echo '<h2>' . esc_html($def[0]) . ' <small><a href="' . esc_url(Pagina::url($b)) . '" target="_blank">' . esc_html('/' . $b . '/') . '</a></small></h2>';
            echo '<p><label>Eigen tekst voor /' . esc_html($b) . '/ <small>(' . ($hub[0] ? 'in Google' : 'noindex: ' . esc_html($hub[1])) . ')</small><br><textarea class="large-text" rows="4" name="beroep[' . esc_attr($b) . ']">' . esc_textarea((string) ($o['beroep'][$b] ?? '')) . '</textarea></label></p>';
            echo '<table class="widefat striped"><thead><tr><th>Plaats</th><th>Klussers</th><th>Status</th><th>Eigen tekst</th></tr></thead><tbody>';
            foreach (Gegevens::plaatsen() as $p) {
                $s = Gegevens::indexeerbaar($b, $p['slug']);
                $tekst = (string) ($o['combo'][$b . '|' . $p['slug']] ?? '');
                echo '<tr><td><a href="' . esc_url(Pagina::url($b, $p['slug'])) . '" target="_blank">' . esc_html($p['naam']) . '</a></td><td>' . Gegevens::klussers($b, $p['id']) . '</td>';
                echo '<td>' . ($s[0] ? '<strong>In Google</strong>' : 'noindex<br><small>' . esc_html($s[1]) . '</small>') . '</td>';
                echo '<td><textarea class="large-text" rows="3" name="combo[' . esc_attr($b . '|' . $p['slug']) . ']">' . esc_textarea($tekst) . '</textarea><small>' . Gegevens::woorden($tekst) . ' woorden</small></td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '<p><button class="button button-primary" type="submit">Opslaan</button></p></form></div>';
    }
}
