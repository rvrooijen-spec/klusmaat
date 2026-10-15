<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

use Klusmaat\Core\Wp\Plugin as Kern;

/** Beheerscherm Klusmaat > Mail: status, instellingen, mailtypes met voorbeeld, testmail en log. */
final class Beheer
{
    private const PAGINA = 'klusmaat-mail';

    public static function haken(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_post_km_mail_opslaan', [self::class, 'opslaan']);
        add_action('admin_post_km_mail_test', [self::class, 'test']);
        add_action('admin_post_km_mail_voorbeeld', [self::class, 'voorbeeld']);
        add_action('admin_post_km_mail_logwissen', [self::class, 'logWissen']);
        add_action('admin_post_km_mail_bericht', [self::class, 'logBericht']);
    }

    public static function menu(): void
    {
        add_submenu_page('klusmaat', 'Mail', 'Mail', Kern::BEHEER, self::PAGINA, [self::class, 'scherm']);
    }

    private static function eis(string $nonce): void
    {
        if (!current_user_can(Kern::BEHEER)) {
            wp_die('Geen toegang.', '', ['response' => 403]);
        }
        check_admin_referer($nonce);
    }

    private static function terug(string $melding): void
    {
        wp_safe_redirect(add_query_arg(['page' => self::PAGINA, 'km_melding' => rawurlencode($melding)], admin_url('admin.php')));
        exit;
    }

    public static function opslaan(): void
    {
        self::eis('km_mail_opslaan');
        update_option(Instellingen::OPTIE, Instellingen::schoon(wp_unslash($_POST)), false);
        Start::plan();
        self::terug('Opgeslagen.');
    }

    public static function test(): void
    {
        self::eis('km_mail_test');
        $type = sanitize_key((string) ($_POST['type'] ?? ''));
        $naar = sanitize_email((string) wp_unslash($_POST['naar'] ?? ''));
        if (!isset(Typen::alle()[$type]) || $naar === '') {
            self::terug('Kies een mailtype en vul een geldig adres in.');
        }
        $ok = Verzender::stuur($type, $naar, Berichten::voorbeeld($type), ['forceer' => true, 'afmelden_user' => Typen::alle()[$type]['doelgroep'] === Typen::KLUSSER ? get_current_user_id() : 0]);
        self::terug($ok ? 'Testmail verstuurd naar ' . $naar . '. Kijk ook in je spam.' : 'Versturen mislukt. Zie het log voor de reden.');
    }

    public static function voorbeeld(): void
    {
        if (!current_user_can(Kern::BEHEER)) {
            wp_die('Geen toegang.', '', ['response' => 403]);
        }
        check_admin_referer('km_mail_voorbeeld');
        $type = sanitize_key((string) ($_GET['type'] ?? ''));
        if (!isset(Typen::alle()[$type])) {
            wp_die('Onbekend mailtype.');
        }
        $b = Berichten::maak($type, Berichten::voorbeeld($type), Typen::alle()[$type]['doelgroep'] === Typen::KLUSSER ? home_url('/?km_mail=afmelden') : '');
        $inh = Sjabloon::bouw($b['opbouw'], (string) Instellingen::alle()['voettekst']);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; img-src data: https:; style-src 'unsafe-inline'");
        echo $inh['html'];
        exit;
    }

    public static function logBericht(): void
    {
        if (!current_user_can(Kern::BEHEER)) {
            wp_die('Geen toegang.', '', ['response' => 403]);
        }
        check_admin_referer('km_mail_bericht');
        $b = Log::bericht(absint($_GET['id'] ?? 0));
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; img-src data: https:; style-src 'unsafe-inline'");
        echo $b ?? '<p>Geen bericht bewaard. De tekst wordt alleen bewaard in de testmodus.</p>';
        exit;
    }

    public static function logWissen(): void
    {
        self::eis('km_mail_logwissen');
        Log::wisAlles();
        self::terug('Log gewist.');
    }

    /** @return array{string,string} [klasse, tekst] */
    private static function dns(string $domein, string $voorvoegsel, string $begin): array
    {
        if (!function_exists('dns_get_record') || $domein === '' || !str_contains($domein, '.')) {
            return ['', 'Niet te controleren op deze server.'];
        }
        $recs = @dns_get_record($voorvoegsel . $domein, DNS_TXT);
        foreach (is_array($recs) ? $recs : [] as $r) {
            if (str_starts_with((string) ($r['txt'] ?? ''), $begin)) {
                return ['ok', 'Gevonden'];
            }
        }
        return ['let-op', 'Niet gevonden'];
    }

    public static function scherm(): void
    {
        if (!current_user_can(Kern::BEHEER)) {
            wp_die('Geen toegang.', '', ['response' => 403]);
        }
        $i = Instellingen::alle();
        $domein = (string) preg_replace('/^.*@/', '', (string) $i['afzender_email']);
        $spf = self::dns($domein, '', 'v=spf1');
        $dmarc = self::dns($domein, '_dmarc.', 'v=DMARC1');
        $tel = Log::tellingen();
        $post = admin_url('admin-post.php');
        echo '<div class="wrap"><h1>Mail</h1>';
        if (!empty($_GET['km_melding'])) {
            echo '<div class="notice notice-info is-dismissible"><p>' . esc_html(rawurldecode((string) wp_unslash($_GET['km_melding']))) . '</p></div>';
        }
        if (!empty($i['testmodus'])) {
            echo '<div class="notice notice-warning"><p><strong>Testmodus staat aan.</strong> Er wordt niets echt verstuurd. Berichten komen alleen in het log hieronder. Zet dit uit zodra de site live gaat.</p></div>';
        }

        echo '<h2>Status</h2><table class="widefat striped" style="max-width:48rem"><tbody>';
        echo '<tr><td>Verzenden via</td><td>' . esc_html(Verzender::transport()) . (defined('KLUSMAAT_SMTP_HOST') ? '' : '<br><small>Aanrader op de echte site: een SMTP-dienst. Zet KLUSMAAT_SMTP_HOST, _PORT, _USER, _PASS en _SECURE in wp-config.php.</small>') . '</td></tr>';
        echo '<tr><td>Afzender</td><td>' . esc_html($i['afzender_naam'] . ' <' . $i['afzender_email'] . '>') . '</td></tr>';
        echo '<tr><td>SPF van ' . esc_html($domein) . '</td><td>' . esc_html($spf[1]) . '</td></tr>';
        echo '<tr><td>DMARC van ' . esc_html($domein) . '</td><td>' . esc_html($dmarc[1]) . '</td></tr>';
        echo '<tr><td>Laatste 24 uur</td><td>' . (int) ($tel['verstuurd'] ?? 0) . ' verstuurd, ' . (int) ($tel['mislukt'] ?? 0) . ' mislukt, ' . (int) ($tel['test'] ?? 0) . ' in testmodus</td></tr>';
        echo '</tbody></table>';

        echo '<form method="post" action="' . esc_url($post) . '"><input type="hidden" name="action" value="km_mail_opslaan">';
        wp_nonce_field('km_mail_opslaan');
        echo '<h2>Instellingen</h2><table class="form-table" role="presentation">';
        $veld = static function (string $naam, string $label, string $waarde, string $hulp = '', string $type = 'text'): void {
            echo '<tr><th scope="row"><label for="' . esc_attr($naam) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" id="' . esc_attr($naam) . '" name="' . esc_attr($naam) . '" type="' . esc_attr($type) . '" value="' . esc_attr($waarde) . '">' . ($hulp !== '' ? '<p class="description">' . esc_html($hulp) . '</p>' : '') . '</td></tr>';
        };
        $veld('afzender_naam', 'Naam afzender', (string) $i['afzender_naam']);
        $veld('afzender_email', 'E-mailadres afzender', (string) $i['afzender_email'], 'Gebruik een adres op je eigen domein. Anders komt mail vaker in de spam.', 'email');
        $veld('antwoord_naar', 'Antwoorden naar', (string) $i['antwoord_naar'], 'Leeg laten om antwoorden naar het afzenderadres te sturen.', 'email');
        $veld('beheer_email', 'Beheer-mail naar', (string) $i['beheer_email'], 'Hier komen meldingen over nieuwe klussers, klussen en controles.', 'email');
        $veld('voettekst', 'Regel onderaan elke mail', (string) $i['voettekst'], 'Bijvoorbeeld bedrijfsnaam en plaats.');
        echo '<tr><th scope="row">Testmodus</th><td><label><input type="checkbox" name="testmodus" value="1"' . checked(!empty($i['testmodus']), true, false) . '> Niet echt versturen, alleen in het log zetten</label></td></tr>';
        echo '<tr><th scope="row"><label for="meldingen_standaard">Meldingen voor nieuwe klussers</label></th><td><select id="meldingen_standaard" name="meldingen_standaard">';
        foreach (['direct' => 'Direct', 'dagelijks' => 'Eén overzicht per dag', 'uit' => 'Uit'] as $k => $n) {
            echo '<option value="' . esc_attr($k) . '"' . selected($i['meldingen_standaard'], $k, false) . '>' . esc_html($n) . '</option>';
        }
        echo '</select><p class="description">Een klusser kan dit zelf aanpassen op zijn dashboard.</p></td></tr>';
        $veld('meldingen_max_per_dag', 'Maximaal per klusser per dag', (string) $i['meldingen_max_per_dag'], 'Directe meldingen. Voorkomt dat iemand te veel mail krijgt.', 'number');
        $veld('digest_uur', 'Uur van het dagoverzicht', (string) $i['digest_uur'], 'Nederlandse tijd, 0 tot 23.', 'number');
        $veld('credits_drempel', 'Waarschuw bij saldo van of onder', (string) $i['credits_drempel'], 'Alleen als credits aan staan.', 'number');
        $veld('log_bewaar_dagen', 'Log bewaren (dagen)', (string) $i['log_bewaar_dagen'], 'Het log bevat e-mailadressen. Korter bewaren is beter voor de privacy.', 'number');

        echo '</table><h2>Mails</h2><table class="widefat striped"><thead><tr><th>Aan</th><th>Mail en wanneer</th><th>Onderwerp en inleiding</th><th>Voorbeeld</th></tr></thead><tbody>';
        foreach (['klant' => 'Voor klanten', 'klusser' => 'Voor klussers', 'beheer' => 'Voor jou'] as $groep => $titel) {
            echo '<tr><td colspan="4"><strong>' . esc_html($titel) . '</strong></td></tr>';
            foreach (Typen::alle() as $id => $def) {
                if ($def['doelgroep'] !== $groep) {
                    continue;
                }
                $t = Instellingen::type($id);
                $url = wp_nonce_url(admin_url('admin-post.php?action=km_mail_voorbeeld&type=' . $id), 'km_mail_voorbeeld');
                echo '<tr><td><input type="checkbox" name="typen[' . esc_attr($id) . '][aan]" value="1"' . checked($t['aan'], true, false) . ' aria-label="' . esc_attr($def['naam']) . ' aan"></td>';
                echo '<td><strong>' . esc_html($def['naam']) . '</strong><br><small>' . esc_html($def['uitleg']) . '</small></td>';
                echo '<td><input class="large-text" name="typen[' . esc_attr($id) . '][onderwerp]" value="' . esc_attr($t['onderwerp']) . '"><textarea class="large-text" rows="3" name="typen[' . esc_attr($id) . '][intro]">' . esc_textarea($t['intro']) . '</textarea><small>Variabelen: ' . esc_html($def['variabelen']) . '</small></td>';
                echo '<td><a href="' . esc_url($url) . '" target="km_voorbeeld">Bekijken</a></td></tr>';
            }
        }
        echo '</tbody></table><p><button class="button button-primary" type="submit">Opslaan</button></p></form>';

        echo '<h2>Testmail sturen</h2><form method="post" action="' . esc_url($post) . '"><input type="hidden" name="action" value="km_mail_test">';
        wp_nonce_field('km_mail_test');
        echo '<select name="type">';
        foreach (Typen::alle() as $id => $def) {
            echo '<option value="' . esc_attr($id) . '">' . esc_html($def['naam']) . '</option>';
        }
        $me = wp_get_current_user();
        echo '</select> <input type="email" name="naar" value="' . esc_attr($me->user_email) . '" class="regular-text"> <button class="button" type="submit">Verstuur testmail</button> <small>Wordt altijd echt verstuurd, ook in de testmodus, met voorbeeldgegevens.</small></form>';

        echo '<h2>Log</h2><table class="widefat striped"><thead><tr><th>Tijd</th><th>Mail</th><th>Naar</th><th>Onderwerp</th><th>Status</th></tr></thead><tbody>';
        $rijen = Log::laatste(50);
        if ($rijen === []) {
            echo '<tr><td colspan="5">Nog niets verstuurd.</td></tr>';
        }
        foreach ($rijen as $r) {
            $status = esc_html((string) $r['status']) . ($r['fout'] !== '' ? '<br><small>' . esc_html((string) $r['fout']) . '</small>' : '');
            if ($r['status'] === 'test') {
                $status .= '<br><a target="km_voorbeeld" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=km_mail_bericht&id=' . (int) $r['id']), 'km_mail_bericht')) . '">bekijk</a>';
            }
            echo '<tr><td>' . esc_html(get_date_from_gmt((string) $r['tijd'], 'd-m-Y H:i')) . '</td><td>' . esc_html((string) $r['type']) . '</td><td>' . esc_html((string) $r['naar']) . '</td><td>' . esc_html((string) $r['onderwerp']) . '</td><td>' . $status . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url($post) . '" style="margin-top:1em"><input type="hidden" name="action" value="km_mail_logwissen">';
        wp_nonce_field('km_mail_logwissen');
        echo '<button class="button" type="submit">Log wissen</button></form></div>';
    }
}
