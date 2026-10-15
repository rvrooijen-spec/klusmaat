<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

use Klusmaat\Core\KlusmaatFout;

/** De beheerschermen onder "Klusmaat" in het WordPress-menu. Alleen voor wie het recht klusmaat_beheer heeft. */
final class Beheer
{
    private const PER_PAGINA = 20;

    public static function haken(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 5);
        add_action('admin_menu', [self::class, 'menuOpruimen'], 999);
        add_action('admin_post_klusmaat_klusser_status', [self::class, 'klusserStatus']);
        add_action('admin_post_klusmaat_credits', [self::class, 'credits']);
        add_action('admin_post_klusmaat_klus_sluiten', [self::class, 'klusSluiten']);
        add_action('admin_post_klusmaat_instellingen', [self::class, 'instellingenOpslaan']);
        add_action('admin_post_klusmaat_logboek_download', [self::class, 'logboekDownload']);
        add_action('admin_post_klusmaat_controle_nu', [self::class, 'controleNu']);
        add_action('admin_notices', [self::class, 'waarschuwingen']);
        add_action('admin_head', [self::class, 'stijl']);
    }

    public static function menu(): void
    {
        add_menu_page('Klusmaat', 'Klusmaat', Plugin::BEHEER, 'klusmaat', [self::class, 'overzicht'], 'dashicons-hammer', 26);
        add_submenu_page('klusmaat', 'Overzicht', 'Overzicht', Plugin::BEHEER, 'klusmaat', [self::class, 'overzicht']);
        add_submenu_page('klusmaat', 'Klussen', 'Klussen', Plugin::BEHEER, 'klusmaat-klussen', [self::class, 'klussen']);
        add_submenu_page('klusmaat', 'Klussers', 'Klussers', Plugin::BEHEER, 'klusmaat-klussers', [self::class, 'klussers']);
        add_submenu_page('klusmaat', 'Logboek', 'Logboek', Plugin::BEHEER, 'klusmaat-logboek', [self::class, 'logboek']);
        add_submenu_page('klusmaat', 'Instellingen', 'Instellingen', Plugin::BEHEER, 'klusmaat-instellingen', [self::class, 'instellingen']);
    }

    /** Het berichttype voegt zelf een menu-item toe; dat hebben we niet nodig. */
    public static function menuOpruimen(): void
    {
        remove_submenu_page('klusmaat', 'edit.php?post_type=klusmaat_klus');
        remove_submenu_page('klusmaat', 'post-new.php?post_type=klusmaat_klus');
    }

    public static function stijl(): void
    {
        echo '<style>.km-adm .km-kaart{background:#fff;border:1px solid #c3c4c7;padding:12px 16px;margin:12px 0;max-width:900px}.km-adm .km-tellers{display:flex;gap:16px;flex-wrap:wrap}.km-adm .km-teller{background:#fff;border:1px solid #c3c4c7;padding:12px 20px;min-width:140px}.km-adm .km-teller b{display:block;font-size:28px;line-height:1.2}.km-adm table.widefat td form{display:inline-block;margin:0 4px 4px 0}.km-adm .km-klein{color:#50575e;font-size:12px}</style>';
    }

    public static function waarschuwingen(): void
    {
        if (!current_user_can(Plugin::BEHEER)) {
            return;
        }
        $scherm = get_current_screen();
        if ($scherm === null || !str_contains((string) $scherm->id, 'klusmaat')) {
            return;
        }
        if (get_option('klusmaat_triggers') === 'mislukt') {
            echo '<div class="notice notice-warning"><p><strong>Klusmaat:</strong> de database-beveiliging voor het logboek en de credit-boekingen kon niet worden aangezet (de host geeft de rechten niet). Alles werkt, maar vraag je host om TRIGGER-rechten voor de database en deactiveer en activeer de plugin daarna opnieuw.</p></div>';
        }
        $c = get_option('klusmaat_controle');
        if (is_array($c) && !empty($c['meldingen'])) {
            echo '<div class="notice notice-error"><p><strong>Klusmaat: de gegevenscontrole vond iets.</strong></p><ul style="list-style:disc;margin-left:20px">';
            foreach ((array) $c['meldingen'] as $m) {
                echo '<li>' . esc_html((string) $m) . '</li>';
            }
            echo '</ul></div>';
        }
        if (isset($_GET['km_melding'])) {
            $soort = (($_GET['km_soort'] ?? '') === 'fout') ? 'error' : 'success';
            echo '<div class="notice notice-' . esc_attr($soort) . ' is-dismissible"><p>' . esc_html(wp_unslash((string) $_GET['km_melding'])) . '</p></div>';
        }
    }

    private static function eis(string $nonce): void
    {
        if (!current_user_can(Plugin::BEHEER)) {
            wp_die('Je hebt hier geen toegang toe.', 403);
        }
        check_admin_referer($nonce);
    }

    private static function terug(string $pagina, string $tekst, bool $goed = true, array $extra = []): never
    {
        wp_safe_redirect(add_query_arg(['page' => $pagina, 'km_melding' => rawurlencode($tekst), 'km_soort' => $goed ? 'goed' : 'fout'] + $extra, admin_url('admin.php')));
        exit;
    }

    private static function formulierStart(string $actie, string $extra = ''): string
    {
        return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr($actie) . '">' . wp_nonce_field($actie, '_wpnonce', true, false) . $extra;
    }

    // Overzicht

    public static function overzicht(): void
    {
        $kern = Plugin::kern();
        $klussen = $kern->aanvragen->overzichtBeheer('open', 1, 0)['totaal'];
        $gesloten = $kern->aanvragen->overzichtBeheer('gesloten', 1, 0)['totaal'];
        $nieuw = count($kern->klussers->overzicht('nieuw'));
        $goed = count($kern->klussers->overzicht('goedgekeurd'));
        global $wpdb;
        $motor = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->prefix . 'klusmaat_aanvragen'));
        $c = get_option('klusmaat_controle');
        echo '<div class="wrap km-adm"><h1>Klusmaat</h1><div class="km-tellers">';
        foreach ([['Open klussen', $klussen], ['Gesloten klussen', $gesloten], ['Klussers wachten op goedkeuring', $nieuw], ['Goedgekeurde klussers', $goed]] as [$naam, $n]) {
            echo '<div class="km-teller"><b>' . (int) $n . '</b>' . esc_html($naam) . '</div>';
        }
        echo '</div><div class="km-kaart"><h2>Systeemcontrole</h2><table class="widefat striped"><tbody>';
        echo '<tr><td>Database-motor</td><td>' . esc_html((string) ($motor ?: 'onbekend')) . ($motor && strtolower((string) $motor) !== 'innodb' ? ' <strong>(moet InnoDB zijn, anders werken transacties niet)</strong>' : '') . '</td></tr>';
        echo '<tr><td>Beveiliging logboek en boekingen</td><td>' . (get_option('klusmaat_triggers') === 'aan' ? 'Aan' : '<strong>Uit</strong> (zie melding bovenaan)') . '</td></tr>';
        echo '<tr><td>Laatste gegevenscontrole</td><td>' . (is_array($c) ? esc_html(Weergave::tijdstip((int) $c['tijd'])) . (empty($c['meldingen']) ? ': alles klopt' : ': ' . count((array) $c['meldingen']) . ' melding(en)') : 'nog niet uitgevoerd') . '</td></tr>';
        echo '<tr><td>Mail</td><td>Niet ingesteld. Klanten krijgen hun persoonlijke link alleen op het scherm.</td></tr>';
        echo '<tr><td>PHP-versie</td><td>' . esc_html(PHP_VERSION) . '</td></tr>';
        echo '</tbody></table>' . self::formulierStart('klusmaat_controle_nu') . '<p><button class="button">Controle nu uitvoeren</button></p></form></div>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=klusmaat-klussers&status=nieuw')) . '">Aanmeldingen bekijken</a> <a class="button" href="' . esc_url(admin_url('edit-tags.php?taxonomy=' . WpCatalogus::PLAATS . '&post_type=klusmaat_klus')) . '">Plaatsen beheren</a> <a class="button" href="' . esc_url(admin_url('edit-tags.php?taxonomy=' . WpCatalogus::KLUSTYPE . '&post_type=klusmaat_klus')) . '">Soorten klussen beheren</a></p></div>';
    }

    public static function controleNu(): void
    {
        self::eis('klusmaat_controle_nu');
        Plugin::dagelijks();
        $c = get_option('klusmaat_controle');
        $n = is_array($c) ? count((array) $c['meldingen']) : 0;
        self::terug('klusmaat', $n === 0 ? 'Controle uitgevoerd: alles klopt.' : "Controle uitgevoerd: {$n} melding(en).", $n === 0);
    }

    // Klussen

    public static function klussen(): void
    {
        $status = in_array($_GET['status'] ?? '', ['open', 'gesloten'], true) ? (string) $_GET['status'] : null;
        $pagina = max(1, absint($_GET['paged'] ?? 1));
        $r = Plugin::kern()->aanvragen->overzichtBeheer($status, self::PER_PAGINA, ($pagina - 1) * self::PER_PAGINA);
        echo '<div class="wrap km-adm"><h1>Klussen</h1><p>';
        foreach ([null => 'Alle', 'open' => 'Open', 'gesloten' => 'Gesloten'] as $s => $naam) {
            echo '<a class="button' . ($s === $status ? ' button-primary' : '') . '" href="' . esc_url(add_query_arg(['page' => 'klusmaat-klussen', 'status' => $s], admin_url('admin.php'))) . '">' . esc_html($naam) . '</a> ';
        }
        echo '</p><table class="widefat striped"><thead><tr><th>Nr</th><th>Klus</th><th>Plaats</th><th>Wanneer</th><th>Klant</th><th>Status</th><th>Klussers</th><th>Geplaatst</th><th></th></tr></thead><tbody>';
        foreach ($r['rijen'] as $k) {
            $weg = !empty($k['geanonimiseerd_op']);
            echo '<tr><td>' . (int) $k['id'] . '</td><td><strong>' . esc_html(WpCatalogus::naam((int) $k['klustype_id'], WpCatalogus::KLUSTYPE)) . '</strong><br>' . ($weg ? '<em>gewist</em>' : nl2br(esc_html(mb_strimwidth((string) $k['omschrijving'], 0, 220, '...')))) . '</td>';
            echo '<td>' . esc_html(WpCatalogus::naam((int) $k['plaats_id'], WpCatalogus::PLAATS)) . '</td><td>' . esc_html(Weergave::moment((string) $k['gewenst_moment'])) . '</td>';
            echo '<td>' . ($weg ? '<em>gewist</em>' : esc_html((string) $k['naam']) . '<br>' . esc_html((string) $k['telefoon']) . '<br>' . esc_html((string) $k['email']) . '<br>' . esc_html((string) $k['adres'] . ', ' . $k['postcode'])) . '</td>';
            echo '<td>' . ($k['status'] === 'open' ? 'Open' : 'Gesloten') . '</td><td>' . (int) $k['opgepakt_aantal'] . ' van ' . (int) $k['max_klussers'] . '</td><td>' . esc_html(Weergave::datum((string) $k['aangemaakt_op'])) . '</td><td>';
            if ($k['status'] === 'open') {
                echo self::formulierStart('klusmaat_klus_sluiten', '<input type="hidden" name="klus_id" value="' . (int) $k['id'] . '">') . '<button class="button">Sluiten</button></form>';
            }
            echo '</td></tr>';
        }
        if ($r['rijen'] === []) {
            echo '<tr><td colspan="9">Geen klussen.</td></tr>';
        }
        echo '</tbody></table>';
        echo paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $pagina, 'total' => max(1, (int) ceil($r['totaal'] / self::PER_PAGINA))]) ?: '';
        echo '</div>';
    }

    public static function klusSluiten(): void
    {
        self::eis('klusmaat_klus_sluiten');
        $klusId = absint($_POST['klus_id'] ?? 0);
        $gelukt = Plugin::kern()->aanvragen->sluitenDoorBeheerder($klusId, get_current_user_id());
        if ($gelukt) {
            do_action('klusmaat_klus_gesloten', $klusId, 'beheer');
        }
        self::terug('klusmaat-klussen', $gelukt ? 'Klus gesloten.' : 'Deze klus was al gesloten.', $gelukt);
    }

    // Klussers

    public static function klussers(): void
    {
        $status = in_array($_GET['status'] ?? '', ['nieuw', 'goedgekeurd', 'afgekeurd', 'geblokkeerd'], true) ? (string) $_GET['status'] : null;
        $rijen = Plugin::kern()->klussers->overzicht($status);
        $labels = ['nieuw' => 'Nieuw', 'goedgekeurd' => 'Goedgekeurd', 'afgekeurd' => 'Afgekeurd', 'geblokkeerd' => 'Geblokkeerd'];
        echo '<div class="wrap km-adm"><h1>Klussers</h1><p>';
        foreach ([null => 'Alle'] + $labels as $s => $naam) {
            echo '<a class="button' . ($s === $status ? ' button-primary' : '') . '" href="' . esc_url(add_query_arg(['page' => 'klusmaat-klussers', 'status' => $s], admin_url('admin.php'))) . '">' . esc_html($naam) . '</a> ';
        }
        echo '</p><table class="widefat striped"><thead><tr><th>Klusser</th><th>Werkt in</th><th>Doet</th><th>Status</th><th>Credits</th><th>Acties</th></tr></thead><tbody>';
        foreach ($rijen as $k) {
            $uid = (int) $k['user_id'];
            $u = get_userdata($uid);
            $namen = static function (string $meta, string $tax) use ($uid): string {
                return esc_html(implode(', ', array_map(static fn ($id) => WpCatalogus::naam((int) $id, $tax), (array) get_user_meta($uid, $meta, true))));
            };
            echo '<tr><td><strong>' . esc_html($u ? $u->display_name : 'Onbekend (' . $uid . ')') . '</strong><br>' . esc_html($u ? $u->user_email : '') . '<br>' . esc_html((string) get_user_meta($uid, 'klusmaat_telefoon', true)) . '<br><span class="km-klein">Aangemeld ' . esc_html(Weergave::datum((string) $k['aangemaakt_op'])) . '</span></td>';
            echo '<td>' . $namen('klusmaat_plaatsen', WpCatalogus::PLAATS) . '</td><td>' . $namen('klusmaat_soorten', WpCatalogus::KLUSTYPE) . '</td>';
            echo '<td>' . esc_html($labels[$k['status']] ?? $k['status']) . '</td><td>' . (int) $k['saldo'] . '</td><td>';
            foreach ([['goedkeuren', 'Goedkeuren', $k['status'] !== 'goedgekeurd'], ['afkeuren', 'Afkeuren', $k['status'] !== 'afgekeurd'], ['blokkeren', 'Blokkeren', $k['status'] !== 'geblokkeerd']] as [$aktie, $tekst, $toon]) {
                if ($toon) {
                    echo self::formulierStart('klusmaat_klusser_status', '<input type="hidden" name="user_id" value="' . $uid . '"><input type="hidden" name="wijziging" value="' . esc_attr($aktie) . '">') . '<button class="button' . ($aktie === 'goedkeuren' ? ' button-primary' : '') . '">' . esc_html($tekst) . '</button></form>';
                }
            }
            if ($k['status'] === 'goedgekeurd') {
                echo '<br>' . self::formulierStart('klusmaat_credits', '<input type="hidden" name="user_id" value="' . $uid . '">')
                    . '<label class="screen-reader-text" for="aantal-' . $uid . '">Aantal credits</label><input id="aantal-' . $uid . '" type="number" name="aantal" step="1" style="width:70px" placeholder="+1 of -1" required> '
                    . '<label class="screen-reader-text" for="reden-' . $uid . '">Reden</label><select id="reden-' . $uid . '" name="reden"><option value="terugbetaling">Terugbetaling</option><option value="correctie">Correctie</option><option value="aankoop">Credits gekocht</option></select> '
                    . '<label class="screen-reader-text" for="toel-' . $uid . '">Toelichting</label><input id="toel-' . $uid . '" type="text" name="toelichting" maxlength="255" placeholder="Toelichting" required> <button class="button">Boeken</button></form>';
            }
            echo '</td></tr>';
        }
        if ($rijen === []) {
            echo '<tr><td colspan="6">Geen klussers.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function klusserStatus(): void
    {
        self::eis('klusmaat_klusser_status');
        $uid = absint($_POST['user_id'] ?? 0);
        $wijziging = (string) ($_POST['wijziging'] ?? '');
        $beheerder = get_current_user_id();
        try {
            $k = Plugin::kern()->klussers;
            match ($wijziging) {
                'goedkeuren' => $k->goedkeuren($uid, $beheerder),
                'afkeuren' => $k->afkeuren($uid, $beheerder, 'door beheerder'),
                'blokkeren' => $k->blokkeren($uid, $beheerder, 'door beheerder'),
                default => throw new KlusmaatFout(KlusmaatFout::ONGELDIG, 'Onbekende actie.'),
            };
            do_action('klusmaat_klusser_status_gewijzigd', $uid, ['goedkeuren' => 'goedgekeurd', 'afkeuren' => 'afgekeurd', 'blokkeren' => 'geblokkeerd'][$wijziging], $beheerder);
            self::terug('klusmaat-klussers', 'Klusser bijgewerkt.');
        } catch (KlusmaatFout $f) {
            self::terug('klusmaat-klussers', $f->getMessage(), false);
        }
    }

    public static function credits(): void
    {
        self::eis('klusmaat_credits');
        $reden = in_array($_POST['reden'] ?? '', ['terugbetaling', 'correctie', 'aankoop'], true) ? (string) $_POST['reden'] : 'correctie';
        try {
            $saldo = Plugin::kern()->credits->boek(absint($_POST['user_id'] ?? 0), (int) ($_POST['aantal'] ?? 0), $reden, sanitize_text_field(wp_unslash((string) ($_POST['toelichting'] ?? ''))), null, get_current_user_id());
            self::terug('klusmaat-klussers', 'Geboekt. Nieuw saldo: ' . $saldo . '.');
        } catch (KlusmaatFout $f) {
            self::terug('klusmaat-klussers', $f->getMessage(), false);
        }
    }

    // Logboek

    public static function logboek(): void
    {
        $kern = Plugin::kern();
        global $wpdb;
        $afwijkend = $kern->logboek->verifieer();
        $rijen = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'klusmaat_logboek ORDER BY id DESC LIMIT 200', ARRAY_A) ?: [];
        echo '<div class="wrap km-adm"><h1>Logboek</h1>';
        echo '<p>' . ($afwijkend === [] ? 'De controle van het logboek klopt.' : '<strong>Let op: ' . count($afwijkend) . ' regel(s) zijn na het schrijven gewijzigd.</strong>') . ' Het logboek bevat geen namen, adressen of telefoonnummers, alleen nummers en aantallen.</p>';
        echo self::formulierStart('klusmaat_logboek_download') . '<p><button class="button">Logboek downloaden (tekstbestand)</button></p></form>';
        echo '<table class="widefat striped"><thead><tr><th>Tijd</th><th>Door</th><th>Actie</th><th>Onderwerp</th><th>Details</th></tr></thead><tbody>';
        foreach ($rijen as $r) {
            echo '<tr><td>' . esc_html(Weergave::datum((string) $r['tijd'])) . '</td><td>' . esc_html($r['actor_type'] . ' ' . $r['actor_id']) . '</td><td>' . esc_html((string) $r['actie']) . '</td><td>' . esc_html($r['object_type'] . ' ' . $r['object_id']) . '</td><td><code>' . esc_html((string) $r['details']) . '</code></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function logboekDownload(): void
    {
        self::eis('klusmaat_logboek_download');
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="klusmaat-logboek-' . gmdate('Ymd-His') . '.txt"');
        echo Plugin::kern()->logboek->alsTekst();
        exit;
    }

    // Instellingen

    public static function instellingen(): void
    {
        $i = Instellingen::alle();
        echo '<div class="wrap km-adm"><h1>Instellingen</h1>' . self::formulierStart('klusmaat_instellingen');
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Credits gebruiken</th><td><label><input type="checkbox" name="credits_aan" value="1"' . checked($i['credits_aan'], 1, false) . '> Klussers betalen credits om een klus op te pakken</label><p class="description">Uit = oppakken is gratis. Handig om te starten.</p></td></tr>';
        foreach ([['gratis_credits', 'Gratis credits bij goedkeuring', 0, 100, 'Eenmalig, bij de eerste goedkeuring.'], ['kosten_per_klus', 'Kosten per klus (credits)', 0, 20, ''], ['max_klussers', 'Maximaal aantal klussers per klus', 1, 10, 'Geldt voor nieuwe klussen. Bestaande klussen houden hun eigen maximum.'], ['bewaar_dagen', 'Persoonsgegevens bewaren na sluiten (dagen)', 30, 3650, 'Daarna worden naam, adres, telefoon en mail van gesloten klussen gewist.']] as [$naam, $label, $min, $max, $hulp]) {
            echo '<tr><th scope="row"><label for="' . esc_attr($naam) . '">' . esc_html($label) . '</label></th><td><input id="' . esc_attr($naam) . '" name="' . esc_attr($naam) . '" type="number" min="' . $min . '" max="' . $max . '" value="' . (int) $i[$naam] . '" class="small-text">' . ($hulp ? '<p class="description">' . esc_html($hulp) . '</p>' : '') . '</td></tr>';
        }
        echo '<tr><th scope="row"><label for="contact_email">Contact-e-mailadres</label></th><td><input id="contact_email" name="contact_email" type="email" value="' . esc_attr((string) $i['contact_email']) . '" class="regular-text"><p class="description">Wordt later gebruikt voor mail aan klanten en klussers.</p></td></tr>';
        echo '</tbody></table><p><button class="button button-primary">Opslaan</button></p></form></div>';
    }

    public static function instellingenOpslaan(): void
    {
        self::eis('klusmaat_instellingen');
        $nieuw = Instellingen::schoon(wp_unslash($_POST));
        update_option(Instellingen::OPTIE, $nieuw, false);
        Plugin::vergeetKern();
        self::terug('klusmaat-instellingen', 'Instellingen opgeslagen.');
    }
}
