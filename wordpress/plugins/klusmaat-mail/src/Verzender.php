<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

/**
 * Verstuurt mail. Faalt nooit hard: een mislukte mail mag een klant of klusser nooit een foutpagina geven.
 * SMTP stel je in via wp-config.php: KLUSMAAT_SMTP_HOST, _PORT, _USER, _PASS en _SECURE ('tls' of 'ssl').
 */
final class Verzender
{
    private static string $tekst = '';
    private static string $fout = '';
    /** @var list<callable> */
    private static array $wachtrij = [];
    private static bool $haakGezet = false;

    /** @param array<string,mixed> $ctx @param array{forceer?:bool, afmelden_user?:int} $opt */
    public static function stuur(string $type, string $naar, array $ctx, array $opt = []): bool
    {
        $onderwerp = $type;
        try {
            $t = Instellingen::type($type);
            if ($t === [] || (!$t['aan'] && empty($opt['forceer']))) {
                return false;
            }
            if (!is_email($naar)) {
                Log::schrijf($type, $naar, $onderwerp, 'mislukt', 'Ongeldig e-mailadres');
                return false;
            }
            $i = Instellingen::alle();
            $afmeld = !empty($opt['afmelden_user']) ? Meldingen::afmeldUrl((int) $opt['afmelden_user']) : '';
            $b = Berichten::maak($type, $ctx, $afmeld);
            $onderwerp = $b['onderwerp'];
            $inh = Sjabloon::bouw($b['opbouw'], (string) $i['voettekst']);

            if (!empty($i['testmodus']) && empty($opt['forceer'])) {
                Log::schrijf($type, $naar, $onderwerp, 'test', '', $inh['html']);
                return true;
            }
            $headers = ['From: ' . self::naam((string) $i['afzender_naam']) . ' <' . $i['afzender_email'] . '>', 'X-Klusmaat-Type: ' . $type];
            if ($i['antwoord_naar'] !== '') {
                $headers[] = 'Reply-To: ' . $i['antwoord_naar'];
            }
            if ($afmeld !== '') {
                $headers[] = 'List-Unsubscribe: <' . $afmeld . '>';
                $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
            }
            self::$tekst = $inh['tekst'];
            self::$fout = '';
            $ct = static fn (): string => 'text/html';
            $fail = static function (\WP_Error $e): void {
                self::$fout = $e->get_error_message();
            };
            add_filter('wp_mail_content_type', $ct);
            add_action('phpmailer_init', [self::class, 'phpmailer']);
            add_action('wp_mail_failed', $fail);
            try {
                $ok = wp_mail($naar, $onderwerp, $inh['html'], $headers);
            } finally {
                remove_filter('wp_mail_content_type', $ct);
                remove_action('phpmailer_init', [self::class, 'phpmailer']);
                remove_action('wp_mail_failed', $fail);
            }
            Log::schrijf($type, $naar, $onderwerp, $ok ? 'verstuurd' : 'mislukt', $ok ? '' : (self::$fout ?: 'wp_mail gaf false'));
            return (bool) $ok;
        } catch (\Throwable $e) {
            error_log('Klusmaat Mail: ' . $e->getMessage());
            try {
                Log::schrijf($type, $naar, $onderwerp, 'mislukt', $e->getMessage());
            } catch (\Throwable) {
            }
            return false;
        }
    }

    private static function naam(string $n): string
    {
        return '"' . str_replace(['"', "\r", "\n"], '', $n) . '"';
    }

    /** Tekstversie erbij en, als in wp-config ingesteld, via SMTP versturen. */
    public static function phpmailer(\PHPMailer\PHPMailer\PHPMailer $m): void
    {
        $m->AltBody = self::$tekst;
        $m->CharSet = 'UTF-8';
        $m->Sender = (string) Instellingen::alle()['afzender_email'];
        if (defined('KLUSMAAT_SMTP_HOST') && KLUSMAAT_SMTP_HOST !== '') {
            $m->isSMTP();
            $m->Host = (string) KLUSMAAT_SMTP_HOST;
            $m->Port = defined('KLUSMAAT_SMTP_PORT') ? (int) KLUSMAAT_SMTP_PORT : 587;
            if (defined('KLUSMAAT_SMTP_USER') && KLUSMAAT_SMTP_USER !== '') {
                $m->SMTPAuth = true;
                $m->Username = (string) KLUSMAAT_SMTP_USER;
                $m->Password = defined('KLUSMAAT_SMTP_PASS') ? (string) KLUSMAAT_SMTP_PASS : '';
            }
            $m->SMTPSecure = defined('KLUSMAAT_SMTP_SECURE') ? (string) KLUSMAAT_SMTP_SECURE : 'tls';
        }
    }

    public static function transport(): string
    {
        return defined('KLUSMAAT_SMTP_HOST') && KLUSMAAT_SMTP_HOST !== '' ? 'SMTP (' . KLUSMAAT_SMTP_HOST . ')' : 'PHP mail() van de host';
    }

    /** Voert werk uit nadat het antwoord naar de bezoeker is gestuurd, zodat het formulier niet wacht op de mailserver. */
    public static function naAfloop(callable $werk): void
    {
        self::$wachtrij[] = $werk;
        if (!self::$haakGezet) {
            self::$haakGezet = true;
            add_action('shutdown', [self::class, 'afloop'], 0);
        }
    }

    public static function afloop(): void
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        while (self::$wachtrij !== []) {
            $w = array_shift(self::$wachtrij);
            try {
                $w();
            } catch (\Throwable $e) {
                error_log('Klusmaat Mail: ' . $e->getMessage());
            }
        }
    }
}
