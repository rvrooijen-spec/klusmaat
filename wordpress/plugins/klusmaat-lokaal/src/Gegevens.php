<?php
declare(strict_types=1);

namespace Klusmaat\Lokaal;

use Klusmaat\Core\Wp\Plugin as Kern;
use Klusmaat\Core\Wp\WpCatalogus;

/** Beroepen, teksten en de indexregel. De regel beschermt tegen dunne pagina's: alleen echte inhoud mag de index in. */
final class Gegevens
{
    public const OPTIE = 'klusmaat_lokaal';

    /** Slug => [naam, soortnaam in de catalogus of null, korte standaardintro]. */
    public const BEROEPEN = [
        'klusser' => ['Klusser', null, 'Voor kleine en middelgrote klussen in en om het huis zoek je een klusser die snel kan komen. Op Klusmaat zet je de klus op de werkbon en goedgekeurde klussers uit jouw buurt nemen contact met je op.'],
        'schilder' => ['Schilder', 'Schilderwerk', 'Een kamer opnieuw schilderen, behang eraf of de kozijnen buiten bijwerken: een schilder uit de buurt pakt het op. Zet je klus op de werkbon en kies zelf met wie je verder gaat.'],
        'loodgieter' => ['Loodgieter', 'Loodgieterswerk', 'Een lekkage, verstopte afvoer of een kraan die moet worden vervangen vraagt snel om hulp. Zet je klus op de werkbon, dan bellen goedgekeurde loodgieters uit de buurt je.'],
        'tuinman' => ['Tuinman', 'Tuinwerk', 'Gras maaien, hagen snoeien, een schutting plaatsen of nieuwe tegels leggen: een tuinman uit de buurt helpt je verder. Zet je klus op de werkbon.'],
        'elektricien' => ['Elektricien', 'Elektra', 'Een storing zoeken, nieuwe stopcontacten of lampen aansluiten, of de groepenkast bekijken: laat het over aan een elektricien. Zet je klus op de werkbon.'],
        'monteur' => ['Monteur', 'Montage', 'Een keuken of kasten monteren, meubels in elkaar zetten of een tv aan de muur: een monteur uit de buurt doet het netjes. Zet je klus op de werkbon.'],
    ];

    /** @return array<string,mixed> */
    public static function opties(): array
    {
        $o = get_option(self::OPTIE, []);
        $o = is_array($o) ? $o : [];
        return [
            'min_woorden' => max(40, min(1000, (int) ($o['min_woorden'] ?? 120))),
            'min_woorden_hub' => max(20, min(1000, (int) ($o['min_woorden_hub'] ?? 60))),
            'beroep' => is_array($o['beroep'] ?? null) ? $o['beroep'] : [],
            'plaats' => is_array($o['plaats'] ?? null) ? $o['plaats'] : [],
            'combo' => is_array($o['combo'] ?? null) ? $o['combo'] : [],
        ];
    }

    /** @param array<string,mixed> $p */
    public static function schoon(array $p): array
    {
        $tekst = static fn ($v): string => trim(sanitize_textarea_field((string) $v));
        $uit = ['min_woorden' => (int) ($p['min_woorden'] ?? 120), 'min_woorden_hub' => (int) ($p['min_woorden_hub'] ?? 60), 'beroep' => [], 'plaats' => [], 'combo' => []];
        foreach (array_keys(self::BEROEPEN) as $b) {
            $uit['beroep'][$b] = $tekst($p['beroep'][$b] ?? '');
        }
        $slugs = array_column(self::plaatsen(), 'slug');
        foreach ($slugs as $pl) {
            $uit['plaats'][$pl] = $tekst($p['plaats'][$pl] ?? '');
            foreach (array_keys(self::BEROEPEN) as $b) {
                $uit['combo'][$b . '|' . $pl] = $tekst($p['combo'][$b . '|' . $pl] ?? '');
            }
        }
        return $uit;
    }

    /** @return list<array{id:int,slug:string,naam:string}> */
    public static function plaatsen(): array
    {
        $uit = [];
        foreach (WpCatalogus::plaatsen() as $t) {
            $uit[] = ['id' => (int) $t->term_id, 'slug' => (string) $t->slug, 'naam' => (string) $t->name];
        }
        return $uit;
    }

    public static function plaats(string $slug): ?array
    {
        foreach (self::plaatsen() as $p) {
            if ($p['slug'] === $slug) {
                return $p;
            }
        }
        return null;
    }

    /** @return array{id:int,slug:string,naam:string}|null De soort in de catalogus, of null voor "klusser" (alle soorten). */
    public static function soort(string $beroep): ?array
    {
        $naam = self::BEROEPEN[$beroep][1] ?? null;
        if ($naam === null) {
            return null;
        }
        foreach (WpCatalogus::soorten() as $t) {
            if ($t->name === $naam) {
                return ['id' => (int) $t->term_id, 'slug' => (string) $t->slug, 'naam' => (string) $t->name];
            }
        }
        return null;
    }

    public static function woorden(string $tekst): int
    {
        return preg_match_all('/\p{L}[\p{L}\p{N}\'-]*/u', $tekst);
    }

    /** Aantal goedgekeurde klussers met deze plaats en dit vak. Een uur gecached. */
    public static function klussers(string $beroep, int $plaatsId): int
    {
        $sleutel = 'km_lokaal_k_' . $beroep . '_' . $plaatsId;
        $c = get_transient($sleutel);
        if ($c !== false) {
            return (int) $c;
        }
        $soort = self::soort($beroep);
        $n = 0;
        foreach (get_users(['role' => Kern::ROL_KLUSSER, 'fields' => 'ID', 'number' => 1000]) as $uid) {
            $uid = (int) $uid;
            if (!in_array($plaatsId, array_map('intval', (array) get_user_meta($uid, 'klusmaat_plaatsen', true)), true)) {
                continue;
            }
            if ($soort !== null && !in_array($soort['id'], array_map('intval', (array) get_user_meta($uid, 'klusmaat_soorten', true)), true)) {
                continue;
            }
            if (Kern::kern()->klussers->status($uid) === 'goedgekeurd') {
                $n++;
            }
        }
        set_transient($sleutel, $n, HOUR_IN_SECONDS);
        return $n;
    }

    /** @return array{bool,string} [mag in de index, reden als niet] */
    public static function indexeerbaar(string $beroep, ?string $plaatsSlug): array
    {
        $o = self::opties();
        if ($plaatsSlug === null) {
            $w = self::woorden((string) ($o['beroep'][$beroep] ?? ''));
            return $w >= $o['min_woorden_hub'] ? [true, ''] : [false, 'eigen tekst voor het vakgebied heeft ' . $w . ' van ' . $o['min_woorden_hub'] . ' woorden'];
        }
        $pl = self::plaats($plaatsSlug);
        if ($pl === null) {
            return [false, 'onbekende plaats'];
        }
        $w = self::woorden((string) ($o['combo'][$beroep . '|' . $plaatsSlug] ?? ''));
        if ($w < $o['min_woorden']) {
            return [false, 'eigen tekst heeft ' . $w . ' van ' . $o['min_woorden'] . ' woorden'];
        }
        if (self::klussers($beroep, $pl['id']) < 1) {
            return [false, 'nog geen goedgekeurde klusser voor deze plaats en dit vak'];
        }
        return [true, ''];
    }
}
