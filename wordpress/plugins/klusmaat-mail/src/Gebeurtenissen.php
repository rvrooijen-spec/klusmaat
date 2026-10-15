<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

use Klusmaat\Core\Wp\Instellingen as KernInstellingen;
use Klusmaat\Core\Wp\Plugin as Kern;
use Klusmaat\Core\Wp\Weergave;
use Klusmaat\Core\Wp\WpCatalogus;

/** Luistert naar de haken van klusmaat-core en stuurt de bijbehorende mails. Alles na het antwoord aan de bezoeker. */
final class Gebeurtenissen
{
    public static function haken(): void
    {
        add_action('klusmaat_klus_geplaatst', [self::class, 'klusGeplaatst'], 10, 3);
        add_action('klusmaat_klusser_aangemeld', [self::class, 'klusserAangemeld']);
        add_action('klusmaat_klusser_status_gewijzigd', [self::class, 'statusGewijzigd'], 10, 2);
        add_action('klusmaat_klus_opgepakt', [self::class, 'klusOpgepakt'], 10, 3);
        add_action('klusmaat_controle_mislukt', [self::class, 'controle']);
    }

    /** Gegevens van een klus voor in de mail. Nooit contactgegevens van de klant voor klussers. */
    public static function klusContext(array $klus): array
    {
        $omschr = trim((string) $klus['omschrijving']);
        return [
            'voornaam' => self::voornaam((string) ($klus['naam'] ?? '')),
            'plaats' => WpCatalogus::naam((int) $klus['plaats_id'], WpCatalogus::PLAATS),
            'klus_soort' => WpCatalogus::naam((int) $klus['klustype_id'], WpCatalogus::KLUSTYPE),
            'wanneer' => Weergave::moment((string) $klus['gewenst_moment']),
            'omschrijving_kort' => mb_strlen($omschr) > 140 ? mb_substr($omschr, 0, 137) . '...' : $omschr,
            'plekken' => max(0, (int) $klus['max_klussers'] - (int) $klus['opgepakt_aantal']),
            'max' => (int) $klus['max_klussers'],
            'n' => (int) $klus['opgepakt_aantal'],
            'dashboard_url' => Kern::url('dashboard'),
            'beheer_url' => admin_url('admin.php?page=klusmaat-klussen'),
        ];
    }

    private static function voornaam(string $naam): string
    {
        $d = preg_split('/\s+/', trim($naam));
        return (string) ($d[0] ?? '');
    }

    public static function klusGeplaatst(int $id, string $token, string $email): void
    {
        Verzender::naAfloop(static function () use ($id, $token, $email): void {
            $klus = Kern::kern()->aanvragen->volledig($id);
            if ($klus === null) {
                return;
            }
            $ctx = self::klusContext($klus) + ['link' => Kern::url('mijn_klus', ['k' => $token])];
            Verzender::stuur('klant_bevestiging', $email, $ctx);
            Verzender::stuur('beheer_nieuwe_klus', (string) Instellingen::alle()['beheer_email'], $ctx);
            Meldingen::directeMeldingen($klus, $ctx);
        });
    }

    public static function klusserAangemeld(int $uid): void
    {
        Verzender::naAfloop(static function () use ($uid): void {
            $u = get_userdata($uid);
            if (!$u) {
                return;
            }
            $ctx = ['voornaam' => self::voornaam($u->display_name), 'naam' => $u->display_name, 'beheer_url' => admin_url('admin.php?page=klusmaat-klussers')];
            Verzender::stuur('klusser_ontvangen', $u->user_email, $ctx);
            Verzender::stuur('beheer_nieuwe_klusser', (string) Instellingen::alle()['beheer_email'], $ctx);
        });
    }

    public static function statusGewijzigd(int $uid, string $status): void
    {
        $type = ['goedgekeurd' => 'klusser_goedgekeurd', 'afgekeurd' => 'klusser_afgekeurd', 'geblokkeerd' => 'klusser_geblokkeerd'][$status] ?? null;
        $u = get_userdata($uid);
        if ($type === null || !$u) {
            return;
        }
        Verzender::naAfloop(static function () use ($type, $u, $uid): void {
            $k = KernInstellingen::alle();
            $ctx = [
                'voornaam' => self::voornaam($u->display_name), 'naam' => $u->display_name, 'dashboard_url' => Kern::url('dashboard'),
                'saldo' => Kern::kern()->credits->saldo($uid), 'kosten' => (int) $k['kosten_per_klus'],
            ];
            Verzender::stuur($type, $u->user_email, $ctx, $type === 'klusser_goedgekeurd' ? ['afmelden_user' => $uid] : []);
        });
    }

    /** @param array<string,mixed> $resultaat */
    public static function klusOpgepakt(int $klusId, int $uid, array $resultaat): void
    {
        Verzender::naAfloop(static function () use ($klusId, $uid, $resultaat): void {
            $klus = Kern::kern()->aanvragen->volledig($klusId);
            $u = get_userdata($uid);
            if ($klus === null || !$u) {
                return;
            }
            $saldo = $resultaat['saldo'] ?? null;
            $ctx = array_merge(self::klusContext($klus), ['voornaam' => self::voornaam($u->display_name), 'saldo' => $saldo ?? 0]);
            Verzender::stuur('klusser_opgepakt', $u->user_email, $ctx);
            // De klant krijgt een melding zonder gegevens van de klusser.
            Verzender::stuur('klant_opgepakt', (string) $klus['email'], self::klusContext($klus));
            if ($saldo !== null && (int) $saldo <= (int) Instellingen::alle()['credits_drempel'] && (int) KernInstellingen::alle()['credits_aan'] === 1) {
                Verzender::stuur('klusser_credits_laag', $u->user_email, $ctx + ['saldo' => (int) $saldo], ['afmelden_user' => $uid]);
            }
        });
    }

    /** @param list<string> $meldingen */
    public static function controle(array $meldingen): void
    {
        Verzender::stuur('beheer_controle', (string) Instellingen::alle()['beheer_email'], ['meldingen' => $meldingen, 'beheer_url' => admin_url('admin.php?page=klusmaat')]);
    }
}
