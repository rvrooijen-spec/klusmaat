<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

/** Logboek van verstuurde mail. Bewaart de tekst alleen in de testmodus. Wordt na de ingestelde tijd gewist. */
final class Log
{
    public static function tabel(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'klusmaat_mail_log';
    }

    public static function schema(): string
    {
        global $wpdb;
        $t = self::tabel();
        return "CREATE TABLE {$t} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  tijd datetime NOT NULL,
  type varchar(40) NOT NULL,
  naar varchar(190) NOT NULL,
  onderwerp varchar(255) NOT NULL,
  status varchar(12) NOT NULL,
  fout varchar(255) NOT NULL DEFAULT '',
  bericht longtext,
  PRIMARY KEY  (id),
  KEY tijd (tijd)
) " . $wpdb->get_charset_collate() . ';';
    }

    public static function schrijf(string $type, string $naar, string $onderwerp, string $status, string $fout = '', ?string $bericht = null): void
    {
        global $wpdb;
        $wpdb->insert(self::tabel(), [
            'tijd' => gmdate('Y-m-d H:i:s'), 'type' => substr($type, 0, 40), 'naar' => substr($naar, 0, 190), 'onderwerp' => substr($onderwerp, 0, 255),
            'status' => $status, 'fout' => substr($fout, 0, 255), 'bericht' => $bericht,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function laatste(int $aantal = 50): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT id, tijd, type, naar, onderwerp, status, fout FROM ' . self::tabel() . ' ORDER BY id DESC LIMIT %d', $aantal), ARRAY_A) ?: [];
    }

    /** @return array<string,int> Aantal per status in de afgelopen 24 uur. */
    public static function tellingen(): array
    {
        global $wpdb;
        $rijen = $wpdb->get_results($wpdb->prepare('SELECT status, COUNT(*) n FROM ' . self::tabel() . ' WHERE tijd >= %s GROUP BY status', gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)), ARRAY_A) ?: [];
        $uit = [];
        foreach ($rijen as $r) {
            $uit[(string) $r['status']] = (int) $r['n'];
        }
        return $uit;
    }

    public static function bericht(int $id): ?string
    {
        global $wpdb;
        $b = $wpdb->get_var($wpdb->prepare('SELECT bericht FROM ' . self::tabel() . ' WHERE id = %d', $id));
        return is_string($b) ? $b : null;
    }

    public static function opruimen(): void
    {
        global $wpdb;
        $dagen = (int) Instellingen::alle()['log_bewaar_dagen'];
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::tabel() . ' WHERE tijd < %s', gmdate('Y-m-d H:i:s', time() - $dagen * DAY_IN_SECONDS)));
    }

    public static function wisAlles(): void
    {
        global $wpdb;
        $wpdb->query('DELETE FROM ' . self::tabel());
    }
}
