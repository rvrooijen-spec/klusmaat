<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

/** Opstart van klusmaat-mail: koppelt aan de haken van klusmaat-core en regelt activatie en plannen. */
final class Start
{
    public const VERSIE = '0.1.0';
    private const OPTIE_VERSIE = 'klusmaat_mail_versie';

    public static function start(): void
    {
        add_action('plugins_loaded', [self::class, 'opstarten'], 20);
    }

    public static function opstarten(): void
    {
        if (!class_exists(\Klusmaat\Core\Wp\Plugin::class)) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>Klusmaat Mail heeft de plugin Klusmaat Core nodig en doet niets zonder.</p></div>';
            });
            return;
        }
        if (get_option(self::OPTIE_VERSIE) !== self::VERSIE) {
            self::activeer();
        }
        Gebeurtenissen::haken();
        Meldingen::haken();
        add_action('klusmaat_mail_digest', [Meldingen::class, 'digest']);
        add_action('klusmaat_mail_opruimen', [Log::class, 'opruimen']);
        if (is_admin()) {
            Beheer::haken();
        }
    }

    public static function activeer(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(Log::schema());
        update_option(self::OPTIE_VERSIE, self::VERSIE, false);
        self::plan();
    }

    /** Dagelijkse overzichtsmail op het ingestelde uur (Nederlandse tijd) en dagelijks opruimen van het log. */
    public static function plan(): void
    {
        wp_clear_scheduled_hook('klusmaat_mail_digest');
        $uur = (int) Instellingen::alle()['digest_uur'];
        $nu = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Amsterdam'));
        $doel = $nu->setTime($uur, 0);
        if ($doel <= $nu) {
            $doel = $doel->modify('+1 day');
        }
        wp_schedule_event($doel->getTimestamp(), 'daily', 'klusmaat_mail_digest');
        if (!wp_next_scheduled('klusmaat_mail_opruimen')) {
            wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', 'klusmaat_mail_opruimen');
        }
    }

    public static function deactiveer(): void
    {
        wp_clear_scheduled_hook('klusmaat_mail_digest');
        wp_clear_scheduled_hook('klusmaat_mail_opruimen');
    }
}
