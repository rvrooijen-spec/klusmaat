<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

use Klusmaat\Core\Config;

/** Instellingen van Klusmaat, bewaard in één WordPress-optie met veilige grenzen. */
final class Instellingen
{
    public const OPTIE = 'klusmaat_instellingen';

    public const STANDAARD = [
        'credits_aan' => 1,
        'gratis_credits' => 6,
        'kosten_per_klus' => 1,
        'max_klussers' => 3,
        'bewaar_dagen' => 365,
        'contact_email' => '',
    ];

    /** @return array<string,mixed> */
    public static function alle(): array
    {
        $opgeslagen = get_option(self::OPTIE, []);
        return self::schoon(is_array($opgeslagen) ? $opgeslagen : []);
    }

    /**
     * @param array<string,mixed> $invoer
     * @return array<string,mixed>
     */
    public static function schoon(array $invoer): array
    {
        $in = array_merge(self::STANDAARD, $invoer);
        $grens = static fn (mixed $v, int $min, int $max): int => max($min, min($max, (int) $v));
        return [
            'credits_aan' => empty($in['credits_aan']) ? 0 : 1,
            'gratis_credits' => $grens($in['gratis_credits'], 0, 100),
            'kosten_per_klus' => $grens($in['kosten_per_klus'], 0, 20),
            'max_klussers' => $grens($in['max_klussers'], 1, 10),
            'bewaar_dagen' => $grens($in['bewaar_dagen'], 30, 3650),
            'contact_email' => is_email((string) $in['contact_email']) ? (string) $in['contact_email'] : '',
        ];
    }

    public static function config(): Config
    {
        $i = self::alle();
        $geheim = defined('KLUSMAAT_LOG_SECRET') ? (string) KLUSMAAT_LOG_SECRET : wp_salt('auth');
        return new Config((bool) $i['credits_aan'], (int) $i['gratis_credits'], (int) $i['kosten_per_klus'], (int) $i['max_klussers'], $geheim);
    }
}
