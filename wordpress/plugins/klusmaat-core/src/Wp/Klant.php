<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

use Klusmaat\Core\KlusmaatFout;

/** De kant van de klant: de werkbon invullen en de klus volgen met een persoonlijke link. Geen account nodig. */
final class Klant
{
    /** @var array{id:int, token:string}|null */
    private static ?array $geplaatst = null;
    /** @var array<string,string> */
    private static array $fouten = [];
    /** @var array<string,string> */
    private static array $waarden = [];
    private static string $melding = '';
    private static string $sluitMelding = '';
    private static string $sluitSoort = 'goed';

    public static function haken(): void
    {
        add_action('template_redirect', [self::class, 'verwerk'], 2);
    }

    public static function verwerk(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        $actie = (string) ($_POST['km_actie'] ?? '');
        if ($actie === 'werkbon_versturen' && Plugin::isPagina('werkbon')) {
            self::versturen();
        } elseif ($actie === 'klus_sluiten' && Plugin::isPagina('mijn_klus')) {
            self::sluiten();
        }
    }

    private static function versturen(): void
    {
        $post = wp_unslash($_POST);
        foreach (['klustype_id', 'plaats_id', 'omschrijving', 'gewenst_moment', 'naam', 'email', 'telefoon', 'adres', 'postcode'] as $veld) {
            self::$waarden[$veld] = is_scalar($post[$veld] ?? null) ? (string) $post[$veld] : '';
        }
        if (!wp_verify_nonce((string) ($post['km_nonce'] ?? ''), 'km_werkbon')) {
            self::$melding = 'Het formulier is verlopen. Je gegevens staan er nog; verstuur opnieuw.';
            return;
        }
        $spam = Beveiliging::controleer($post);
        if ($spam !== null) {
            self::$melding = $spam;
            return;
        }
        if (!Beveiliging::mag('werkbon', 5)) {
            self::$melding = 'Je hebt het afgelopen uur al meerdere werkbonnen verstuurd. Probeer het over een uur opnieuw.';
            return;
        }
        try {
            self::$geplaatst = Plugin::kern()->aanvragen->plaats(self::$waarden);
        } catch (KlusmaatFout $f) {
            self::$fouten = array_map('strval', $f->velden);
            self::$melding = $f->velden === [] ? $f->getMessage() : 'Controleer de gemarkeerde velden.';
            return;
        } catch (\Throwable $e) {
            error_log('Klusmaat werkbon: ' . $e->getMessage());
            self::$melding = 'Er ging iets mis aan onze kant. Je werkbon is niet verstuurd. Probeer het zo opnieuw.';
            return;
        }
        // Geen redirect: de persoonlijke link bestaat alleen in dit antwoord en wordt nergens bewaard.
        Beveiliging::privekoppen();
        // Bedoeld voor later: hier kan een mail met de link worden verstuurd zodra mail aan staat.
        do_action('klusmaat_klus_geplaatst', self::$geplaatst['id'], self::$geplaatst['token'], self::$waarden['email']);
    }

    private static function sluiten(): void
    {
        $post = wp_unslash($_POST);
        $token = (string) ($post['k'] ?? '');
        if (!wp_verify_nonce((string) ($post['km_nonce'] ?? ''), 'km_sluiten')) {
            self::$sluitMelding = 'Het scherm was verlopen. Probeer het opnieuw.';
            self::$sluitSoort = 'fout';
            return;
        }
        try {
            $gesloten = Plugin::kern()->aanvragen->sluitenMetLink($token);
            self::$sluitMelding = $gesloten ? 'Je klus is gesloten. Klussers kunnen hem niet meer oppakken.' : 'Deze klus was al gesloten.';
        } catch (KlusmaatFout $f) {
            self::$sluitMelding = $f->getMessage();
            self::$sluitSoort = 'fout';
        }
    }

    public static function werkbon(): string
    {
        Beveiliging::privekoppen();
        if (self::$geplaatst !== null) {
            return self::bevestiging(self::$geplaatst['token']);
        }
        $w = self::$waarden;
        if ($w === []) {
            $w = ['klustype_id' => '', 'plaats_id' => '', 'omschrijving' => '', 'gewenst_moment' => 'zo_snel_mogelijk', 'naam' => '', 'email' => '', 'telefoon' => '', 'adres' => '', 'postcode' => ''];
            $voorkeurSoort = sanitize_title((string) ($_GET['klus'] ?? ''));
            if ($voorkeurSoort !== '') {
                $t = get_term_by('slug', $voorkeurSoort, WpCatalogus::KLUSTYPE);
                $w['klustype_id'] = $t instanceof \WP_Term ? (string) $t->term_id : '';
            }
            $w['plaats_id'] = (string) absint($_GET['plaats'] ?? 0);
        }
        $f = self::$fouten;
        $h = '';
        if (self::$melding !== '') {
            $h .= Weergave::melding(self::$melding);
        }

        $plaatsen = ['' => 'Kies een plaats'];
        foreach (WpCatalogus::plaatsen() as $p) {
            $plaatsen[(string) $p->term_id] = $p->name;
        }

        $h .= '<form class="km-bladen" method="post" action="' . esc_url(Plugin::url('werkbon')) . '" novalidate>';
        $h .= Weergave::verborgen('werkbon_versturen', 'km_werkbon') . Beveiliging::honeypotVeld() . Beveiliging::tijdVeld();

        $h .= '<div class="km-stapel km-stapel-formulier"><div class="km-bon">';
        $h .= '<div class="km-bon-kop"><h2>Blad 1: de klus</h2><span>Blad 1 van 2</span></div>';
        $h .= self::selectVeld('klustype_id', 'Wat moet er gebeuren?', self::klustypeOpties(), $w['klustype_id'], $f);
        $h .= self::selectVeld('plaats_id', 'Plaats van de klus', $plaatsen, $w['plaats_id'], $f);
        $h .= Weergave::tekstvak('omschrijving', 'Beschrijf de klus (minimaal 10 tekens)', $w['omschrijving'], $f, 'Bijvoorbeeld: woonkamer en gang schilderen, ongeveer 40 m2, muren zijn al geplamuurd.', 'Zet hier geen telefoonnummer of e-mailadres in. Die vul je op blad 2 in.');
        $h .= Weergave::keuzerondjes('gewenst_moment', 'Wanneer moet het gebeuren?', Weergave::MOMENTEN, $w['gewenst_moment'], $f);
        $h .= '</div></div>';

        $h .= '<div class="km-bon km-bon-geel km-bon-blad2">';
        $h .= '<div class="km-bon-kop"><h2>Blad 2: jij</h2><span>Blad 2 van 2</span></div>';
        $h .= Weergave::veld('naam', 'Naam', $w['naam'], $f, 'text', ['autocomplete' => 'name']);
        $h .= Weergave::veld('email', 'E-mailadres', $w['email'], $f, 'email', ['autocomplete' => 'email', 'inputmode' => 'email']);
        $h .= Weergave::veld('telefoon', 'Telefoonnummer', $w['telefoon'], $f, 'tel', ['autocomplete' => 'tel', 'inputmode' => 'tel']);
        $h .= Weergave::veld('adres', 'Straat en huisnummer', $w['adres'], $f, 'text', ['autocomplete' => 'street-address']);
        $h .= Weergave::veld('postcode', 'Postcode', $w['postcode'], $f, 'text', ['autocomplete' => 'postal-code']);
        $h .= '<p class="km-hulp-klein">Alleen klussers die jouw klus oppakken zien deze gegevens. Dat zijn er maximaal ' . (int) Instellingen::alle()['max_klussers'] . '.</p>';
        $h .= Weergave::knop('Werkbon versturen');
        $h .= '</div></form>';
        return Weergave::pagina('<h1 class="km-paginatitel">Vul je werkbon in</h1><p class="km-inleiding">Twee bladen: eerst de klus, dan jij.</p>' . $h);
    }

    /** @return array<string,string|array<string,string>> */
    private static function klustypeOpties(): array
    {
        $opties = ['' => 'Kies een klus'];
        foreach (WpCatalogus::soorten() as $soort) {
            $kinderen = WpCatalogus::categorieen($soort->term_id);
            if ($kinderen === []) {
                $opties[(string) $soort->term_id] = $soort->name;
                continue;
            }
            $groep = [];
            foreach ($kinderen as $k) {
                $groep[(string) $k->term_id] = $k->name;
            }
            $groep[(string) $soort->term_id] = 'Iets anders bij ' . mb_strtolower($soort->name);
            $opties[$soort->name] = $groep;
        }
        return $opties;
    }

    /**
     * @param array<string,string|array<string,string>> $opties
     * @param array<string,string> $fouten
     */
    private static function selectVeld(string $naam, string $label, array $opties, string $gekozen, array $fouten): string
    {
        $id = 'km-' . $naam;
        $fout = $fouten[$naam] ?? '';
        $html = '<div class="km-veldgroep"><label class="km-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        $html .= '<select class="km-veld' . ($fout !== '' ? ' km-veld-fout' : '') . '" id="' . esc_attr($id) . '" name="' . esc_attr($naam) . '"' . ($fout !== '' ? ' aria-invalid="true" aria-describedby="' . esc_attr($id) . '-fout"' : '') . '>';
        foreach ($opties as $waarde => $tekst) {
            if (is_array($tekst)) {
                $html .= '<optgroup label="' . esc_attr((string) $waarde) . '">';
                foreach ($tekst as $w2 => $t2) {
                    $html .= '<option value="' . esc_attr((string) $w2) . '"' . selected($gekozen, (string) $w2, false) . '>' . esc_html($t2) . '</option>';
                }
                $html .= '</optgroup>';
            } else {
                $html .= '<option value="' . esc_attr((string) $waarde) . '"' . selected($gekozen, (string) $waarde, false) . '>' . esc_html($tekst) . '</option>';
            }
        }
        $html .= '</select>';
        if ($fout !== '') {
            $html .= '<p class="km-fout" id="' . esc_attr($id) . '-fout">' . esc_html($fout) . '</p>';
        }
        return $html . '</div>';
    }

    private static function bevestiging(string $token): string
    {
        $url = Plugin::url('mijn_klus', ['k' => $token]);
        $h = '<h1 class="km-paginatitel">Je werkbon is verstuurd</h1>';
        $h .= '<p class="km-inleiding">Goedgekeurde klussers uit jouw buurt kunnen je klus nu oppakken. Wie dat doet, belt je.</p>';
        $h .= '<div class="km-bon km-sleutel"><div class="km-bon-kop"><h2>Je persoonlijke link</h2></div>';
        $h .= '<p>Bewaar deze link. Je hebt hem nodig om je klus terug te vinden en te sluiten. We kunnen hem niet voor je opzoeken.</p>';
        $h .= '<label class="km-label" for="km-link">Persoonlijke link</label><input class="km-veld km-link" id="km-link" type="text" readonly value="' . esc_attr($url) . '">';
        $h .= '<p class="km-acties"><button class="km-knop" type="button" data-kopieer="km-link">Kopieer link</button> <a class="km-knop km-knop-rand" href="' . esc_url($url) . '">Naar mijn klus</a></p></div>';
        return Weergave::pagina($h);
    }

    public static function mijnKlus(): string
    {
        Beveiliging::privekoppen();
        $token = (string) wp_unslash($_REQUEST['k'] ?? '');
        $klus = $token !== '' ? Plugin::kern()->aanvragen->voorKlant($token) : null;
        if ($klus === null) {
            return Weergave::pagina('<h1 class="km-paginatitel">Klus niet gevonden</h1><p class="km-inleiding">Deze link klopt niet of is niet compleet. Kopieer de hele link uit je werkbon-bevestiging en probeer het opnieuw.</p>');
        }
        $h = '<h1 class="km-paginatitel">Jouw klus</h1>';
        if (self::$sluitMelding !== '') {
            $h .= Weergave::melding(self::$sluitMelding, self::$sluitSoort);
            $klus = Plugin::kern()->aanvragen->voorKlant($token) ?? $klus;
        }
        $open = $klus['status'] === 'open';
        $soort = WpCatalogus::naam((int) $klus['klustype_id'], WpCatalogus::KLUSTYPE);
        $h .= '<div class="km-bon"><div class="km-bon-kop"><h2>' . esc_html($soort) . '</h2><span>' . ($open ? 'Open' : 'Gesloten') . '</span></div>';
        if ($klus['geanonimiseerd_op'] ?? null) {
            $h .= '<p>De gegevens van deze klus zijn gewist.</p></div>';
            return Weergave::pagina($h);
        }
        $h .= '<dl class="km-gegevens">';
        $h .= '<dt>Plaats</dt><dd>' . esc_html(WpCatalogus::naam((int) $klus['plaats_id'], WpCatalogus::PLAATS)) . '</dd>';
        $h .= '<dt>Wanneer</dt><dd>' . esc_html(Weergave::moment((string) $klus['gewenst_moment'])) . '</dd>';
        $h .= '<dt>Omschrijving</dt><dd>' . nl2br(esc_html((string) $klus['omschrijving'])) . '</dd>';
        $h .= '<dt>Geplaatst op</dt><dd>' . esc_html(Weergave::datum((string) $klus['aangemaakt_op'])) . '</dd>';
        $h .= '<dt>Jouw gegevens</dt><dd>' . esc_html((string) $klus['naam']) . ', ' . esc_html((string) $klus['telefoon']) . ', ' . esc_html((string) $klus['adres']) . ', ' . esc_html((string) $klus['postcode']) . '</dd>';
        $h .= '</dl></div>';

        $h .= '<h2 class="km-sectiekop km-klein">Wie heeft je klus opgepakt?</h2>';
        $klussers = $klus['klussers'];
        if ($klussers === []) {
            $h .= '<p>Nog niemand. Zodra een klusser je klus oppakt, staat hier zijn naam en belt hij je.</p>';
        } else {
            $h .= '<ul class="km-lijst">';
            foreach ($klussers as $r) {
                $u = get_userdata((int) $r['user_id']);
                $h .= '<li><strong>' . esc_html($u ? $u->display_name : 'Klusser') . '</strong> pakte je klus op op ' . esc_html(Weergave::datum((string) $r['aangemaakt_op'])) . '.</li>';
            }
            $h .= '</ul>';
        }
        if ($open) {
            $h .= '<form method="post" action="' . esc_url(Plugin::url('mijn_klus')) . '" class="km-acties">' . Weergave::verborgen('klus_sluiten', 'km_sluiten')
                . '<input type="hidden" name="k" value="' . esc_attr($token) . '">' . Weergave::knop('Klus sluiten') . '<p class="km-hulp-klein">Sluit je klus als hij klaar is of als je geen klusser meer nodig hebt.</p></form>';
        }
        return Weergave::pagina($h);
    }
}
