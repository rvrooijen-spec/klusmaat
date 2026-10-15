<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

/** Instellingen van de mailplugin, in één optie. */
final class Instellingen
{
    public const OPTIE = 'klusmaat_mail';

    public const STANDAARD = [
        'afzender_naam' => 'Klusmaat',
        'afzender_email' => '',
        'antwoord_naar' => '',
        'beheer_email' => '',
        'voettekst' => 'Klusmaat, klussers in Roosendaal, Bergen op Zoom en omgeving.',
        'testmodus' => 0,
        'meldingen_standaard' => 'direct',
        'meldingen_max_per_dag' => 10,
        'digest_uur' => 7,
        'credits_drempel' => 1,
        'log_bewaar_dagen' => 30,
        'typen' => [],
    ];

    /** @return array<string,mixed> */
    public static function alle(): array
    {
        $opgeslagen = get_option(self::OPTIE, []);
        $i = array_merge(self::STANDAARD, is_array($opgeslagen) ? $opgeslagen : []);
        $i['afzender_email'] = $i['afzender_email'] !== '' ? $i['afzender_email'] : self::standaardAfzender();
        $i['beheer_email'] = $i['beheer_email'] !== '' ? $i['beheer_email'] : (string) get_option('admin_email');
        return $i;
    }

    /** Afzender op het domein van de site, zodat SPF en DKIM kloppen. */
    public static function standaardAfzender(): string
    {
        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $host = preg_replace('/^www\./', '', $host);
        return ($host !== '' && str_contains($host, '.')) ? 'klus@' . $host : (string) get_option('admin_email');
    }

    /** Instellingen van één mailtype: standaard, met wat de beheerder heeft aangepast. */
    public static function type(string $id): array
    {
        $def = Typen::alle()[$id] ?? null;
        if ($def === null) {
            return [];
        }
        $i = self::alle();
        $eigen = is_array($i['typen'][$id] ?? null) ? $i['typen'][$id] : [];
        return [
            'aan' => array_key_exists('aan', $eigen) ? (bool) $eigen['aan'] : $def['aan'],
            'onderwerp' => ($eigen['onderwerp'] ?? '') !== '' ? (string) $eigen['onderwerp'] : $def['onderwerp'],
            'intro' => ($eigen['intro'] ?? '') !== '' ? (string) $eigen['intro'] : $def['intro'],
        ];
    }

    /** Maakt van ruwe formulierinvoer nette instellingen. */
    public static function schoon(array $p): array
    {
        $mail = static fn ($v): string => (string) (sanitize_email((string) $v) ?: '');
        $grens = static fn ($v, int $min, int $max): int => max($min, min($max, (int) $v));
        $typen = [];
        foreach (Typen::alle() as $id => $def) {
            $t = is_array($p['typen'][$id] ?? null) ? $p['typen'][$id] : [];
            $onderwerp = trim(sanitize_text_field((string) ($t['onderwerp'] ?? '')));
            $intro = trim(sanitize_textarea_field((string) ($t['intro'] ?? '')));
            $typen[$id] = [
                'aan' => empty($t['aan']) ? 0 : 1,
                'onderwerp' => $onderwerp === $def['onderwerp'] ? '' : $onderwerp,
                'intro' => $intro === $def['intro'] ? '' : $intro,
            ];
        }
        return [
            'afzender_naam' => trim(sanitize_text_field((string) ($p['afzender_naam'] ?? ''))) ?: 'Klusmaat',
            'afzender_email' => $mail($p['afzender_email'] ?? ''),
            'antwoord_naar' => $mail($p['antwoord_naar'] ?? ''),
            'beheer_email' => $mail($p['beheer_email'] ?? ''),
            'voettekst' => trim(sanitize_text_field((string) ($p['voettekst'] ?? ''))),
            'testmodus' => empty($p['testmodus']) ? 0 : 1,
            'meldingen_standaard' => in_array($p['meldingen_standaard'] ?? '', ['direct', 'dagelijks', 'uit'], true) ? (string) $p['meldingen_standaard'] : 'direct',
            'meldingen_max_per_dag' => $grens($p['meldingen_max_per_dag'] ?? 10, 1, 50),
            'digest_uur' => $grens($p['digest_uur'] ?? 7, 0, 23),
            'credits_drempel' => $grens($p['credits_drempel'] ?? 1, 0, 20),
            'log_bewaar_dagen' => $grens($p['log_bewaar_dagen'] ?? 30, 1, 365),
            'typen' => $typen,
        ];
    }
}
