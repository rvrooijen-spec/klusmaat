<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

use Klusmaat\Core\KlusmaatFout;

/** De kant van de klusser: aanmelden, inloggen en het dashboard met klussen en credits. */
final class Klusser
{
    private const META_PLAATSEN = 'klusmaat_plaatsen';
    private const META_SOORTEN = 'klusmaat_soorten';
    private const META_TELEFOON = 'klusmaat_telefoon';

    /** @var array<string,string> */
    private static array $fouten = [];
    /** @var array<string,mixed> */
    private static array $waarden = [];
    private static string $melding = '';
    private static string $meldingSoort = 'fout';

    public static function haken(): void
    {
        add_action('template_redirect', [self::class, 'verwerk'], 2);
    }

    public static function verwerk(): void
    {
        if (Plugin::isPagina('dashboard') && !is_user_logged_in()) {
            wp_safe_redirect(Plugin::url('inloggen'));
            exit;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        $actie = (string) ($_POST['km_actie'] ?? '');
        match (true) {
            $actie === 'klusser_aanmelden' && Plugin::isPagina('klusser_worden') => self::aanmeldenVerwerken(),
            $actie === 'inloggen' && Plugin::isPagina('inloggen') => self::inloggenVerwerken(),
            $actie === 'oppakken' && Plugin::isPagina('dashboard') => self::oppakkenVerwerken(),
            default => null,
        };
    }

    // Aanmelden

    private static function aanmeldenVerwerken(): void
    {
        $post = wp_unslash($_POST);
        $w = [
            'naam' => trim((string) ($post['naam'] ?? '')),
            'email' => trim((string) ($post['email'] ?? '')),
            'telefoon' => trim((string) ($post['telefoon'] ?? '')),
            'plaatsen' => array_values(array_unique(array_map('absint', (array) ($post['plaatsen'] ?? [])))),
            'soorten' => array_values(array_unique(array_map('absint', (array) ($post['soorten'] ?? [])))),
            'akkoord' => !empty($post['akkoord']),
        ];
        $wachtwoord = (string) ($post['wachtwoord'] ?? '');
        self::$waarden = $w;
        if (!wp_verify_nonce((string) ($post['km_nonce'] ?? ''), 'km_aanmelden')) {
            self::$melding = 'Het formulier is verlopen. Je gegevens staan er nog; verstuur opnieuw.';
            return;
        }
        if (($spam = Beveiliging::controleer($post)) !== null) {
            self::$melding = $spam;
            return;
        }
        $f = [];
        if (mb_strlen($w['naam']) < 2 || mb_strlen($w['naam']) > 80) {
            $f['naam'] = 'Vul je naam in.';
        }
        if (!is_email($w['email'])) {
            $f['email'] = 'Vul een geldig e-mailadres in.';
        } elseif (email_exists($w['email'])) {
            $f['email'] = 'Met dit e-mailadres is al een account. Log in of gebruik een ander adres.';
        }
        $cijfers = preg_replace('/\D/', '', $w['telefoon']) ?? '';
        if (strlen($cijfers) < 9 || strlen($cijfers) > 13) {
            $f['telefoon'] = 'Vul een geldig telefoonnummer in.';
        }
        if (mb_strlen($wachtwoord) < 12) {
            $f['wachtwoord'] = 'Kies een wachtwoord van minstens 12 tekens.';
        }
        $geldigePlaatsen = array_filter($w['plaatsen'], static fn (int $id): bool => (new WpCatalogus())->plaatsBestaat($id));
        $geldigeSoorten = array_filter($w['soorten'], static fn (int $id): bool => (new WpCatalogus())->klustypeBestaat($id));
        if ($geldigePlaatsen === []) {
            $f['plaatsen'] = 'Kies minstens één plaats waar je werkt.';
        }
        if ($geldigeSoorten === []) {
            $f['soorten'] = 'Kies minstens één soort klus die je doet.';
        }
        if (!$w['akkoord']) {
            $f['akkoord'] = 'Ga akkoord met de voorwaarden om je aan te melden.';
        }
        if ($f !== []) {
            self::$fouten = $f;
            self::$melding = 'Controleer de gemarkeerde velden.';
            return;
        }
        if (!Beveiliging::mag('klusser', 3)) {
            self::$melding = 'Er zijn al meerdere aanmeldingen vanaf dit apparaat gedaan. Probeer het over een uur opnieuw.';
            return;
        }
        $id = wp_insert_user([
            'user_login' => $w['email'], 'user_email' => $w['email'], 'user_pass' => $wachtwoord, 'display_name' => $w['naam'],
            'first_name' => $w['naam'], 'nickname' => $w['naam'], 'role' => Plugin::ROL_KLUSSER, 'show_admin_bar_front' => 'false',
        ]);
        if (is_wp_error($id)) {
            self::$melding = 'Aanmelden is niet gelukt. Probeer het later opnieuw.';
            return;
        }
        update_user_meta($id, self::META_PLAATSEN, array_values($geldigePlaatsen));
        update_user_meta($id, self::META_SOORTEN, array_values($geldigeSoorten));
        update_user_meta($id, self::META_TELEFOON, $w['telefoon']);
        try {
            Plugin::kern()->klussers->aanmelden((int) $id);
        } catch (\Throwable $e) {
            error_log('Klusmaat aanmelden: ' . $e->getMessage());
        }
        wp_set_current_user((int) $id);
        wp_set_auth_cookie((int) $id, true);
        wp_safe_redirect(Plugin::url('dashboard', ['welkom' => 1]));
        exit;
    }

    public static function aanmelden(): string
    {
        Beveiliging::privekoppen();
        if (is_user_logged_in()) {
            return Weergave::pagina('<h1 class="km-paginatitel">Je bent al ingelogd</h1><p><a class="km-knop" href="' . esc_url(Plugin::url('dashboard')) . '">Naar mijn klussen</a></p>');
        }
        $w = self::$waarden + ['naam' => '', 'email' => '', 'telefoon' => '', 'plaatsen' => [], 'soorten' => [], 'akkoord' => false];
        $f = self::$fouten;
        $plaatsen = [];
        foreach (WpCatalogus::plaatsen() as $p) {
            $plaatsen[$p->term_id] = $p->name;
        }
        $soorten = [];
        foreach (WpCatalogus::soorten() as $s) {
            $soorten[$s->term_id] = $s->name;
        }
        $h = '<h1 class="km-paginatitel">Aanmelden als klusser</h1><p class="km-inleiding">Meld je aan. Wij bekijken je aanmelding. Na goedkeuring zie je de klussen in jouw regio.</p>';
        $h .= self::melding();
        $h .= '<form class="km-bon km-formulier-smal" method="post" action="' . esc_url(Plugin::url('klusser_worden')) . '" novalidate>' . Weergave::verborgen('klusser_aanmelden', 'km_aanmelden') . Beveiliging::honeypotVeld() . Beveiliging::tijdVeld();
        $h .= '<div class="km-bon-kop"><h2>Aanmelding</h2></div>';
        $h .= Weergave::veld('naam', 'Naam', (string) $w['naam'], $f, 'text', ['autocomplete' => 'name']);
        $h .= Weergave::veld('email', 'E-mailadres (hiermee log je in)', (string) $w['email'], $f, 'email', ['autocomplete' => 'email', 'inputmode' => 'email']);
        $h .= Weergave::veld('telefoon', 'Telefoonnummer', (string) $w['telefoon'], $f, 'tel', ['autocomplete' => 'tel', 'inputmode' => 'tel'], 'Klanten bellen je op dit nummer.');
        $h .= Weergave::veld('wachtwoord', 'Wachtwoord', '', $f, 'password', ['autocomplete' => 'new-password'], 'Minstens 12 tekens.');
        $h .= Weergave::aanvinken('plaatsen', 'In welke plaatsen werk je?', $plaatsen, $w['plaatsen'], $f);
        $h .= Weergave::aanvinken('soorten', 'Welke klussen doe je?', $soorten, $w['soorten'], $f);
        $fout = $f['akkoord'] ?? '';
        $h .= '<div class="km-veldgroep"><label class="km-optie"><input type="checkbox" name="akkoord" value="1"' . checked(!empty($w['akkoord']), true, false) . ($fout !== '' ? ' aria-invalid="true" aria-describedby="km-akkoord-fout"' : '') . '><span>Ik ga akkoord met de <a href="' . esc_url(Plugin::url('voorwaarden')) . '">voorwaarden</a> en de <a href="' . esc_url(Plugin::url('privacy')) . '">privacyverklaring</a>.</span></label>';
        $h .= $fout !== '' ? '<p class="km-fout" id="km-akkoord-fout">' . esc_html($fout) . '</p>' : '';
        $h .= '</div>' . Weergave::knop('Aanmelden') . '</form>';
        return Weergave::pagina($h);
    }

    // Inloggen

    private static function inloggenVerwerken(): void
    {
        $post = wp_unslash($_POST);
        self::$waarden = ['email' => trim((string) ($post['email'] ?? ''))];
        if (!wp_verify_nonce((string) ($post['km_nonce'] ?? ''), 'km_inloggen')) {
            self::$melding = 'Het formulier is verlopen. Probeer het opnieuw.';
            return;
        }
        if (!Beveiliging::mag('login', 10)) {
            self::$melding = 'Te veel pogingen. Probeer het over een uur opnieuw.';
            return;
        }
        $u = wp_signon(['user_login' => self::$waarden['email'], 'user_password' => (string) ($post['wachtwoord'] ?? ''), 'remember' => true], is_ssl());
        if (is_wp_error($u)) {
            self::$melding = 'Het e-mailadres of wachtwoord klopt niet.';
            return;
        }
        wp_safe_redirect(user_can($u, Plugin::BEHEER) ? admin_url('admin.php?page=klusmaat') : Plugin::url('dashboard'));
        exit;
    }

    public static function inloggen(): string
    {
        Beveiliging::privekoppen();
        if (is_user_logged_in()) {
            return Weergave::pagina('<h1 class="km-paginatitel">Je bent ingelogd</h1><p><a class="km-knop" href="' . esc_url(Plugin::url('dashboard')) . '">Naar mijn klussen</a></p>');
        }
        $h = '<h1 class="km-paginatitel">Inloggen</h1><p class="km-inleiding">Alleen voor klussers. Als klant heb je geen account nodig.</p>' . self::melding();
        $h .= '<form class="km-bon km-formulier-smal" method="post" action="' . esc_url(Plugin::url('inloggen')) . '" novalidate>' . Weergave::verborgen('inloggen', 'km_inloggen');
        $h .= '<div class="km-bon-kop"><h2>Inloggen</h2></div>';
        $h .= Weergave::veld('email', 'E-mailadres', (string) (self::$waarden['email'] ?? ''), [], 'email', ['autocomplete' => 'username', 'inputmode' => 'email']);
        $h .= Weergave::veld('wachtwoord', 'Wachtwoord', '', [], 'password', ['autocomplete' => 'current-password']);
        $h .= Weergave::knop('Inloggen') . '<p class="km-hulp-klein">Nog geen account? <a href="' . esc_url(Plugin::url('klusser_worden')) . '">Meld je aan als klusser</a>.</p></form>';
        return Weergave::pagina($h);
    }

    // Dashboard

    private static function oppakkenVerwerken(): void
    {
        $post = wp_unslash($_POST);
        $klusId = absint($post['klus_id'] ?? 0);
        if (!is_user_logged_in() || !wp_verify_nonce((string) ($post['km_nonce'] ?? ''), 'km_oppakken_' . $klusId)) {
            self::$melding = 'Het scherm was verlopen. Probeer het opnieuw.';
            return;
        }
        try {
            $r = Plugin::kern()->oppakken->uitvoeren($klusId, get_current_user_id());
            self::$meldingSoort = 'goed';
            self::$melding = 'Je hebt de klus opgepakt. De gegevens van de klant staan bij Mijn opgepakte klussen.' . ($r['kosten'] > 0 ? ' Je saldo is nu ' . $r['saldo'] . '.' : '');
        } catch (KlusmaatFout $f) {
            self::$meldingSoort = 'fout';
            self::$melding = $f->getMessage();
        }
    }

    public static function dashboard(): string
    {
        Beveiliging::privekoppen();
        if (!is_user_logged_in()) {
            return '';
        }
        $uid = get_current_user_id();
        $kern = Plugin::kern();
        $status = $kern->klussers->status($uid);
        if ($status === null) {
            return Weergave::pagina('<h1 class="km-paginatitel">Mijn klussen</h1><p>Dit account is geen klusseraccount. <a href="' . esc_url(wp_logout_url(home_url('/'))) . '">Uitloggen</a></p>');
        }
        $h = '<h1 class="km-paginatitel">Mijn klussen</h1>' . self::melding();
        if (!empty($_GET['welkom'])) {
            $h .= Weergave::melding('Je aanmelding is ontvangen. We bekijken hem zo snel mogelijk.', 'goed');
        }
        $uitloggen = '<p class="km-hulp-klein"><a href="' . esc_url(wp_logout_url(home_url('/'))) . '">Uitloggen</a></p>';

        if ($status !== 'goedgekeurd') {
            $tekst = match ($status) {
                'nieuw' => ['Je aanmelding wordt bekeken', 'Zodra je bent goedgekeurd, zie je hier de klussen in jouw regio. Dat is nog niet gebeurd.'],
                'afgekeurd' => ['Je aanmelding is niet goedgekeurd', 'Je kunt geen klussen oppakken. Vragen? Neem contact met ons op.'],
                default => ['Je account is geblokkeerd', 'Je kunt geen klussen oppakken. Neem contact met ons op voor meer informatie.'],
            };
            return Weergave::pagina($h . '<div class="km-bon"><div class="km-bon-kop"><h2>' . esc_html($tekst[0]) . '</h2></div><p>' . esc_html($tekst[1]) . '</p></div>' . $uitloggen);
        }

        $i = Instellingen::alle();
        $saldo = $kern->credits->saldo($uid);
        $h .= '<div class="km-saldo"><span class="km-saldo-getal">' . (int) $saldo . '</span> <span>credits' . ($i['credits_aan'] ? ' &middot; een klus oppakken kost ' . (int) $i['kosten_per_klus'] . '' : ' &middot; credits zijn nu uitgeschakeld, oppakken is gratis') . '</span></div>';

        // Klussen in de regio van deze klusser.
        $plaatsIds = array_map('intval', (array) get_user_meta($uid, self::META_PLAATSEN, true));
        $soortIds = array_map('intval', (array) get_user_meta($uid, self::META_SOORTEN, true));
        $typen = [];
        foreach ($soortIds as $s) {
            $typen = array_merge($typen, WpCatalogus::metKinderen($s));
        }
        $open = $kern->aanvragen->lijstVoorKlusser($uid, $plaatsIds, array_values(array_unique($typen)));
        $h .= '<h2 class="km-sectiekop km-klein">Open klussen in jouw regio</h2>';
        if ($open === []) {
            $h .= '<p>Er staan nu geen open klussen voor jouw plaatsen en soorten. Kom later terug; nieuwe klussen verschijnen hier zodra ze binnenkomen.</p>';
        } else {
            $h .= '<ul class="km-klussen">';
            foreach ($open as $k) {
                $h .= self::klusRij($k, $uid, (int) $i['kosten_per_klus'], (bool) $i['credits_aan']);
            }
            $h .= '</ul>';
        }

        $mijn = $kern->aanvragen->mijnKlussen($uid);
        $h .= '<h2 class="km-sectiekop km-klein">Mijn opgepakte klussen</h2>';
        if ($mijn === []) {
            $h .= '<p>Je hebt nog geen klussen opgepakt.</p>';
        } else {
            $h .= '<ul class="km-klussen">';
            foreach ($mijn as $k) {
                $h .= '<li class="km-klus"><div class="km-klus-kop"><strong>' . esc_html(WpCatalogus::naam((int) $k['klustype_id'], WpCatalogus::KLUSTYPE)) . '</strong> in ' . esc_html(WpCatalogus::naam((int) $k['plaats_id'], WpCatalogus::PLAATS)) . '</div>';
                $h .= '<p>' . nl2br(esc_html((string) $k['omschrijving'])) . '</p>';
                $h .= '<dl class="km-gegevens"><dt>Klant</dt><dd>' . esc_html((string) $k['naam']) . '</dd><dt>Telefoon</dt><dd>' . esc_html((string) $k['telefoon']) . '</dd><dt>E-mail</dt><dd>' . esc_html((string) $k['email']) . '</dd><dt>Adres</dt><dd>' . esc_html((string) $k['adres']) . ', ' . esc_html((string) $k['postcode']) . '</dd><dt>Wanneer</dt><dd>' . esc_html(Weergave::moment((string) $k['gewenst_moment'])) . '</dd><dt>Opgepakt op</dt><dd>' . esc_html(Weergave::datum((string) $k['opgepakt_op'])) . '</dd></dl></li>';
            }
            $h .= '</ul>';
        }

        $boekingen = array_slice($kern->credits->overzicht($uid), 0, 15);
        if ($boekingen !== []) {
            $h .= '<h2 class="km-sectiekop km-klein">Mijn credits</h2><div class="km-tabelrand"><table class="km-tabel"><thead><tr><th scope="col">Datum</th><th scope="col">Reden</th><th scope="col">Aantal</th><th scope="col">Saldo</th></tr></thead><tbody>';
            foreach ($boekingen as $b) {
                $h .= '<tr><td>' . esc_html(Weergave::datum((string) $b['aangemaakt_op'])) . '</td><td>' . esc_html(self::redenTekst((string) $b['reden'], (string) $b['toelichting'])) . '</td><td>' . esc_html(((int) $b['delta'] > 0 ? '+' : '') . (int) $b['delta']) . '</td><td>' . (int) $b['saldo_na'] . '</td></tr>';
            }
            $h .= '</tbody></table></div>';
        }
        return Weergave::pagina($h . $uitloggen);
    }

    /** @param array<string,mixed> $k */
    private static function klusRij(array $k, int $uid, int $kosten, bool $creditsAan): string
    {
        $id = (int) $k['id'];
        $kostTekst = $creditsAan && $kosten > 0 ? 'Oppakken voor ' . $kosten . ($kosten === 1 ? ' credit' : ' credits') : 'Oppakken';
        $h = '<li class="km-klus"><div class="km-klus-kop"><strong>' . esc_html(WpCatalogus::naam((int) $k['klustype_id'], WpCatalogus::KLUSTYPE)) . '</strong> in ' . esc_html(WpCatalogus::naam((int) $k['plaats_id'], WpCatalogus::PLAATS)) . '</div>';
        $h .= '<p>' . nl2br(esc_html((string) $k['omschrijving'])) . '</p>';
        $h .= '<p class="km-hulp-klein">' . esc_html(Weergave::moment((string) $k['gewenst_moment'])) . '. Geplaatst op ' . esc_html(Weergave::datum((string) $k['aangemaakt_op'])) . '. Nog ' . (int) $k['plekken_vrij'] . ' van ' . (int) $k['max_klussers'] . ' plekken vrij.</p>';
        $h .= '<form method="post" action="' . esc_url(Plugin::url('dashboard')) . '">' . Weergave::verborgen('oppakken', 'km_oppakken_' . $id) . '<input type="hidden" name="klus_id" value="' . $id . '">' . Weergave::knop($kostTekst) . '</form></li>';
        return $h;
    }

    private static function redenTekst(string $reden, string $toelichting): string
    {
        $tekst = match ($reden) {
            'gratis_start' => 'Gratis credits bij goedkeuring',
            'oppakken' => 'Klus opgepakt',
            'terugbetaling' => 'Terugbetaling',
            'correctie' => 'Correctie',
            'aankoop' => 'Credits gekocht',
            default => $reden,
        };
        return $toelichting !== '' && $reden !== 'gratis_start' ? $tekst . ': ' . $toelichting : $tekst;
    }

    private static function melding(): string
    {
        return self::$melding !== '' ? Weergave::melding(self::$melding, self::$meldingSoort) : '';
    }
}
