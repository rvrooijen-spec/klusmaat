<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

/** Spamremmen en veilige antwoordkoppen voor de formulieren. */
final class Beveiliging
{
    /** Verborgen veld dat mensen niet zien en spambots wel invullen. */
    public static function honeypotVeld(): string
    {
        return '<div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Laat dit veld leeg<input type="text" name="km_website" tabindex="-1" autocomplete="off" value=""></label></div>';
    }

    /** Verborgen veld met het tijdstip waarop het formulier is getoond, ondertekend. */
    public static function tijdVeld(): string
    {
        $t = time();
        return '<input type="hidden" name="km_t" value="' . esc_attr($t . '.' . self::teken($t)) . '">';
    }

    private static function teken(int $t): string
    {
        return substr(hash_hmac('sha256', 'km-tijd|' . $t, wp_salt('nonce')), 0, 20);
    }

    /** @return string|null Een foutmelding in gewone taal, of null als alles klopt. */
    public static function controleer(array $post, int $minSeconden = 3): ?string
    {
        if (!empty($post['km_website'])) {
            return 'Het formulier kon niet worden verstuurd. Probeer het opnieuw.';
        }
        $delen = explode('.', (string) ($post['km_t'] ?? ''), 2);
        $t = (int) ($delen[0] ?? 0);
        if ($t < 1 || !hash_equals(self::teken($t), (string) ($delen[1] ?? ''))) {
            return 'Het formulier is verlopen. Vul het opnieuw in.';
        }
        $verschil = time() - $t;
        if ($verschil < $minSeconden) {
            return 'Dat ging heel snel. Controleer je gegevens en verstuur opnieuw.';
        }
        if ($verschil > 12 * HOUR_IN_SECONDS) {
            return 'Het formulier is verlopen. Vul het opnieuw in.';
        }
        return null;
    }

    /**
     * Telt een poging en zegt of er nog ruimte is. De teller zit per bezoeker (gehasht IP-adres) in een transient.
     * Achter een proxy zoals Cloudflare: definieer KLUSMAAT_IP_HEADER, bijvoorbeeld 'HTTP_CF_CONNECTING_IP'.
     */
    public static function mag(string $soort, int $maxPerUur): bool
    {
        $ip = (string) ($_SERVER[defined('KLUSMAAT_IP_HEADER') ? KLUSMAAT_IP_HEADER : 'REMOTE_ADDR'] ?? '');
        $sleutel = 'km_rl_' . $soort . '_' . substr(hash_hmac('sha256', $ip, wp_salt('auth')), 0, 24);
        $n = (int) get_transient($sleutel);
        if ($n >= $maxPerUur) {
            return false;
        }
        set_transient($sleutel, $n + 1, HOUR_IN_SECONDS);
        return true;
    }

    /** Koppen voor pagina's met een persoonlijke link of persoonsgegevens: niet bewaren, niet indexeren, geen referrer. */
    public static function privekoppen(): void
    {
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('X-Content-Type-Options: nosniff');
    }
}
