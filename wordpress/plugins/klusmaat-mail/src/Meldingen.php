<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

use Klusmaat\Core\Wp\Instellingen as KernInstellingen;
use Klusmaat\Core\Wp\Plugin as Kern;
use Klusmaat\Core\Wp\WpCatalogus;

/** Meldingen voor klussers: voorkeur, matchen op plaats en soort, directe mail, dagoverzicht en afmelden. */
final class Meldingen
{
    public const META = 'klusmaat_mail_meldingen';
    private const KEUZES = ['direct' => 'Direct bij elke nieuwe klus', 'dagelijks' => 'Eén overzicht per dag', 'uit' => 'Geen meldingen'];

    public static function haken(): void
    {
        add_filter('klusmaat_dashboard_blokken', [self::class, 'blok'], 10, 2);
        add_action('template_redirect', [self::class, 'verwerk'], 2);
    }

    public static function voorkeur(int $uid): string
    {
        $v = (string) get_user_meta($uid, self::META, true);
        return isset(self::KEUZES[$v]) ? $v : (string) Instellingen::alle()['meldingen_standaard'];
    }

    // Afmelden

    public static function token(int $uid): string
    {
        return hash_hmac('sha256', 'afmelden|' . $uid, wp_salt('auth'));
    }

    public static function afmeldUrl(int $uid): string
    {
        return add_query_arg(['km_mail' => 'afmelden', 'u' => $uid, 't' => self::token($uid)], home_url('/'));
    }

    // Matchen

    /** @return list<int> Goedgekeurde klussers die deze klus mogen zien, met meldingen niet uit. */
    private static function ontvangers(array $klus, string $voorkeur): array
    {
        $soort = WpCatalogus::soortVan((int) $klus['klustype_id']);
        $uit = [];
        foreach (get_users(['role' => Kern::ROL_KLUSSER, 'fields' => 'ID', 'number' => 500]) as $uid) {
            $uid = (int) $uid;
            if (Kern::kern()->klussers->status($uid) !== 'goedgekeurd' || self::voorkeur($uid) !== $voorkeur) {
                continue;
            }
            $plaatsen = array_map('intval', (array) get_user_meta($uid, 'klusmaat_plaatsen', true));
            $soorten = array_map('intval', (array) get_user_meta($uid, 'klusmaat_soorten', true));
            if (in_array((int) $klus['plaats_id'], $plaatsen, true) && (in_array($soort, $soorten, true) || in_array((int) $klus['klustype_id'], $soorten, true))) {
                $uit[] = $uid;
            }
        }
        return $uit;
    }

    private static function magNogKrijgen(int $uid): bool
    {
        $max = (int) Instellingen::alle()['meldingen_max_per_dag'];
        $sleutel = 'km_mail_' . $uid . '_' . gmdate('Ymd');
        $n = (int) get_transient($sleutel);
        if ($n >= $max) {
            return false;
        }
        set_transient($sleutel, $n + 1, DAY_IN_SECONDS);
        return true;
    }

    /** @param array<string,mixed> $klus @param array<string,mixed> $ctx */
    public static function directeMeldingen(array $klus, array $ctx): void
    {
        $creditsAan = (int) KernInstellingen::alle()['credits_aan'] === 1;
        foreach (self::ontvangers($klus, 'direct') as $uid) {
            if ($creditsAan && Kern::kern()->credits->saldo($uid) < max(1, (int) KernInstellingen::alle()['kosten_per_klus'])) {
                continue;
            }
            $u = get_userdata($uid);
            if ($u && self::magNogKrijgen($uid)) {
                Verzender::stuur('klusser_nieuwe_klus', $u->user_email, array_merge($ctx, ['voornaam' => preg_split('/\s+/', trim($u->display_name))[0] ?? '']), ['afmelden_user' => $uid]);
            }
        }
    }

    /** Dagelijks: één overzicht met de klussen van de laatste 24 uur. */
    public static function digest(): void
    {
        if (!Instellingen::type('klusser_overzicht')['aan']) {
            return;
        }
        $grens = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $creditsAan = (int) KernInstellingen::alle()['credits_aan'] === 1;
        foreach (get_users(['role' => Kern::ROL_KLUSSER, 'fields' => 'ID', 'number' => 500]) as $uid) {
            $uid = (int) $uid;
            if (Kern::kern()->klussers->status($uid) !== 'goedgekeurd' || self::voorkeur($uid) !== 'dagelijks') {
                continue;
            }
            if ($creditsAan && Kern::kern()->credits->saldo($uid) < 1) {
                continue;
            }
            $typen = [];
            foreach (array_map('intval', (array) get_user_meta($uid, 'klusmaat_soorten', true)) as $s) {
                $typen = array_merge($typen, WpCatalogus::metKinderen($s));
            }
            try {
                $open = Kern::kern()->aanvragen->lijstVoorKlusser($uid, array_map('intval', (array) get_user_meta($uid, 'klusmaat_plaatsen', true)), array_values(array_unique($typen)));
            } catch (\Throwable) {
                continue;
            }
            $nieuw = array_values(array_filter($open, static fn (array $r): bool => (string) $r['aangemaakt_op'] >= $grens));
            $u = get_userdata($uid);
            if ($nieuw === [] || !$u) {
                continue;
            }
            $lijst = array_map(static function (array $r): array {
                $c = Gebeurtenissen::klusContext($r);
                return ['klus_soort' => $c['klus_soort'], 'plaats' => $c['plaats'], 'omschrijving_kort' => $c['omschrijving_kort'], 'plekken' => $c['plekken']];
            }, array_slice($nieuw, 0, 10));
            Verzender::stuur('klusser_overzicht', $u->user_email, [
                'voornaam' => preg_split('/\s+/', trim($u->display_name))[0] ?? '', 'aantal' => count($nieuw), 'lijst' => $lijst, 'dashboard_url' => Kern::url('dashboard'),
            ], ['afmelden_user' => $uid]);
        }
    }

    // Dashboardblok en verwerken

    public static function blok(string $html, int $uid): string
    {
        $v = self::voorkeur($uid);
        $h = '<h2 class="km-sectiekop km-klein">Meldingen per mail</h2>';
        if (isset($_GET['mail']) && $_GET['mail'] === 'ok') {
            $h .= '<p class="km-melding km-goed" role="status">Opgeslagen.</p>';
        }
        $h .= '<form method="post" class="km-formulier" action="' . esc_url(Kern::url('dashboard')) . '"><p class="km-hulp-klein">Wil je een mail als er een klus in jouw plaatsen en vakgebieden bijkomt?</p>';
        foreach (self::KEUZES as $k => $naam) {
            $h .= '<label class="km-keuze"><input type="radio" name="km_mail_voorkeur" value="' . esc_attr($k) . '"' . checked($v, $k, false) . '> ' . esc_html($naam) . '</label> ';
        }
        $h .= wp_nonce_field('km_mail_voorkeur', 'km_mail_nonce', true, false) . '<p><button class="km-knop km-knop-licht" type="submit">Opslaan</button></p></form>';
        return $html . $h;
    }

    public static function verwerk(): void
    {
        // Voorkeur opslaan vanuit het dashboard.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['km_mail_voorkeur'])) {
            $nonce = (string) wp_unslash($_POST['km_mail_nonce'] ?? '');
            $keuze = (string) wp_unslash($_POST['km_mail_voorkeur']);
            if (is_user_logged_in() && wp_verify_nonce($nonce, 'km_mail_voorkeur') && isset(self::KEUZES[$keuze])) {
                update_user_meta(get_current_user_id(), self::META, $keuze);
                wp_safe_redirect(add_query_arg('mail', 'ok', Kern::url('dashboard')));
                exit;
            }
        }
        // Afmeldpagina: GET toont een bevestiging, POST voert uit (ook de one-click van mailprogramma's).
        if (($_GET['km_mail'] ?? '') !== 'afmelden') {
            return;
        }
        $uid = absint($_GET['u'] ?? 0);
        $geldig = $uid > 0 && hash_equals(self::token($uid), (string) ($_GET['t'] ?? ''));
        $klaar = false;
        if ($geldig && $_SERVER['REQUEST_METHOD'] === 'POST') {
            update_user_meta($uid, self::META, 'uit');
            $klaar = true;
        }
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        status_header($geldig ? 200 : 403);
        header('Content-Type: text/html; charset=utf-8');
        echo self::afmeldPagina($geldig, $klaar);
        exit;
    }

    private static function afmeldPagina(bool $geldig, bool $klaar): string
    {
        $dash = esc_url(Kern::url('dashboard'));
        if (!$geldig) {
            $inh = '<h1>Deze link werkt niet</h1><p>De link is niet geldig. Log in en pas je meldingen aan op je dashboard.</p><p><a href="' . $dash . '">Naar mijn dashboard</a></p>';
        } elseif ($klaar) {
            $inh = '<h1>Je krijgt geen meldingen meer</h1><p>Dat is gelukt. Je kunt dit altijd weer aanzetten op je dashboard.</p><p><a href="' . $dash . '">Naar mijn dashboard</a></p>';
        } else {
            $inh = '<h1>Meldingen uitzetten?</h1><p>Je krijgt dan geen mail meer over nieuwe klussen. Mails over je account en over klussen die je hebt opgepakt blijven komen.</p><form method="post"><button type="submit">Ja, zet meldingen uit</button></form><p><a href="' . $dash . '">Nee, terug naar mijn dashboard</a></p>';
        }
        return '<!doctype html><html lang="nl-NL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Meldingen - Klusmaat</title>'
            . '<style>body{margin:0;background:#E7E5E0;font:17px/1.5 Arial,sans-serif;color:#111}main{max-width:32rem;margin:10vh auto;padding:1.5rem;background:#FBFAF7;border-top:6px solid #111}h1{font-size:1.6rem;margin:0 0 1rem}a{color:#2547B5}button{background:#111;color:#fff;border:0;padding:.8rem 1.2rem;font:inherit;font-weight:700;cursor:pointer}</style></head><body><main>' . $inh . '</main></body></html>';
    }
}
