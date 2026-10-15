<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

/** Kleine bouwstenen voor de HTML van de formulieren. Alles wat naar de pagina gaat, wordt hier ontsnapt. */
final class Weergave
{
    public const MOMENTEN = [
        'zo_snel_mogelijk' => 'Zo snel mogelijk',
        'binnen_een_week' => 'Binnen een week',
        'binnen_een_maand' => 'Binnen een maand',
        'flexibel' => 'Ik ben flexibel',
    ];

    /**
     * Eén invulveld met label, foutmelding en de juiste koppelingen voor schermlezers.
     *
     * @param array<string,string> $fouten
     * @param array<string,string> $extra Extra attributen, bijvoorbeeld autocomplete.
     */
    public static function veld(string $naam, string $label, string $waarde, array $fouten, string $type = 'text', array $extra = [], string $hulp = ''): string
    {
        $id = 'km-' . $naam;
        $fout = $fouten[$naam] ?? '';
        $beschrijving = trim(($hulp !== '' ? $id . '-hulp ' : '') . ($fout !== '' ? $id . '-fout' : ''));
        $attr = '';
        foreach ($extra as $k => $v) {
            $attr .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
        }
        $html = '<div class="km-veldgroep"><label class="km-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        if ($hulp !== '') {
            $html .= '<p class="km-hulp-klein" id="' . esc_attr($id) . '-hulp">' . esc_html($hulp) . '</p>';
        }
        $html .= '<input class="km-veld' . ($fout !== '' ? ' km-veld-fout' : '') . '" id="' . esc_attr($id) . '" name="' . esc_attr($naam) . '" type="' . esc_attr($type) . '" value="' . esc_attr($waarde) . '"' . $attr
            . ($fout !== '' ? ' aria-invalid="true"' : '') . ($beschrijving !== '' ? ' aria-describedby="' . esc_attr($beschrijving) . '"' : '') . '>';
        $html .= self::foutregel($id, $fout);
        return $html . '</div>';
    }

    public static function tekstvak(string $naam, string $label, string $waarde, array $fouten, string $placeholder = '', string $hulp = ''): string
    {
        $id = 'km-' . $naam;
        $fout = $fouten[$naam] ?? '';
        $beschrijving = trim(($hulp !== '' ? $id . '-hulp ' : '') . ($fout !== '' ? $id . '-fout' : ''));
        $html = '<div class="km-veldgroep"><label class="km-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        if ($hulp !== '') {
            $html .= '<p class="km-hulp-klein" id="' . esc_attr($id) . '-hulp">' . esc_html($hulp) . '</p>';
        }
        $html .= '<textarea class="km-veld km-tekstvak' . ($fout !== '' ? ' km-veld-fout' : '') . '" id="' . esc_attr($id) . '" name="' . esc_attr($naam) . '" rows="5" placeholder="' . esc_attr($placeholder) . '"'
            . ($fout !== '' ? ' aria-invalid="true"' : '') . ($beschrijving !== '' ? ' aria-describedby="' . esc_attr($beschrijving) . '"' : '') . '>' . esc_textarea($waarde) . '</textarea>';
        $html .= self::foutregel($id, $fout);
        return $html . '</div>';
    }

    /**
     * @param array<string,string> $opties waarde => tekst
     * @param array<string,string> $fouten
     */
    public static function keuzerondjes(string $naam, string $label, array $opties, string $gekozen, array $fouten): string
    {
        $id = 'km-' . $naam;
        $fout = $fouten[$naam] ?? '';
        $html = '<fieldset class="km-keuze"' . ($fout !== '' ? ' aria-describedby="' . esc_attr($id) . '-fout"' : '') . '><legend class="km-label">' . esc_html($label) . '</legend>';
        foreach ($opties as $waarde => $tekst) {
            $html .= '<label class="km-optie"><input type="radio" name="' . esc_attr($naam) . '" value="' . esc_attr((string) $waarde) . '"' . checked($gekozen, (string) $waarde, false) . '><span>' . esc_html($tekst) . '</span></label>';
        }
        return $html . self::foutregel($id, $fout) . '</fieldset>';
    }

    /**
     * @param array<int,string> $opties id => tekst
     * @param list<int> $gekozen
     */
    public static function aanvinken(string $naam, string $label, array $opties, array $gekozen, array $fouten): string
    {
        $id = 'km-' . $naam;
        $fout = $fouten[$naam] ?? '';
        $html = '<fieldset class="km-keuze"' . ($fout !== '' ? ' aria-describedby="' . esc_attr($id) . '-fout"' : '') . '><legend class="km-label">' . esc_html($label) . '</legend><div class="km-opties-rij">';
        foreach ($opties as $waarde => $tekst) {
            $html .= '<label class="km-optie"><input type="checkbox" name="' . esc_attr($naam) . '[]" value="' . esc_attr((string) $waarde) . '"' . checked(in_array((int) $waarde, $gekozen, true), true, false) . '><span>' . esc_html($tekst) . '</span></label>';
        }
        return $html . '</div>' . self::foutregel($id, $fout) . '</fieldset>';
    }

    private static function foutregel(string $id, string $fout): string
    {
        return $fout === '' ? '' : '<p class="km-fout" id="' . esc_attr($id) . '-fout">' . esc_html($fout) . '</p>';
    }

    /** Een melding bovenaan een formulier. $soort is 'fout' of 'goed'. */
    public static function melding(string $tekst, string $soort = 'fout'): string
    {
        $rol = $soort === 'fout' ? 'alert' : 'status';
        return '<div class="km-melding km-melding-' . esc_attr($soort) . '" role="' . $rol . '"><p>' . esc_html($tekst) . '</p></div>';
    }

    public static function knop(string $tekst, string $naam = '', string $waarde = ''): string
    {
        return '<button class="km-knop" type="submit"' . ($naam !== '' ? ' name="' . esc_attr($naam) . '" value="' . esc_attr($waarde) . '"' : '') . '>' . esc_html($tekst) . '</button>';
    }

    /** Verborgen velden die elke POST nodig heeft. */
    public static function verborgen(string $actie, string $nonceActie): string
    {
        return '<input type="hidden" name="km_actie" value="' . esc_attr($actie) . '">' . wp_nonce_field($nonceActie, 'km_nonce', false, false);
    }

    /** Datum en tijd zoals in Nederland, uit een UTC-waarde in de database. */
    public static function datum(string $utc): string
    {
        $ts = strtotime($utc . ' UTC');
        return $ts ? self::tijdstip($ts) . ' uur' : '-';
    }

    /** "15 oktober 2026, 14:05", altijd Nederlands en in Nederlandse tijd, ook als de site op Engels staat. */
    public static function tijdstip(int $ts): string
    {
        static $maanden = ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
        $d = new \DateTimeImmutable('@' . $ts);
        $d = $d->setTimezone(new \DateTimeZone('Europe/Amsterdam'));
        return $d->format('j') . ' ' . $maanden[(int) $d->format('n')] . ' ' . $d->format('Y, H:i');
    }

    public static function moment(string $sleutel): string
    {
        return self::MOMENTEN[$sleutel] ?? '-';
    }

    public static function pagina(string $html): string
    {
        return '<div class="km-pagina">' . $html . '</div>';
    }
}
