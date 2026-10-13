<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/** De status van een klusser: nieuw, goedgekeurd, afgekeurd of geblokkeerd. */
final class Klussers
{
    public function __construct(
        private Database $db,
        private Config $config,
        private Credits $credits,
        private Logboek $logboek,
    ) {
    }

    /** Een nieuwe klusser meldt zich aan. Nog zonder credits en nog niet goedgekeurd. */
    public function aanmelden(int $userId): void
    {
        try {
            $this->db->insert(
                'INSERT INTO ' . $this->db->tabel('klussers') . ' (user_id, status, saldo, aangemaakt_op) VALUES (?, ?, 0, ?)',
                [$userId, 'nieuw', gmdate('Y-m-d H:i:s')]
            );
        } catch (DatabaseFout $e) {
            if ($e->duplicaat) {
                throw new KlusmaatFout(KlusmaatFout::ONGELDIG, 'Deze klusser is al aangemeld.');
            }
            throw $e;
        }
        $this->logboek->schrijf('klusser', $userId, 'klusser_aangemeld', 'klusser', $userId);
    }

    public function status(int $userId): ?string
    {
        $status = $this->db->fetchValue('SELECT status FROM ' . $this->db->tabel('klussers') . ' WHERE user_id = ?', [$userId]);
        return $status === null ? null : (string) $status;
    }

    /**
     * Keurt een nieuwe klusser goed. Alleen de eerste goedkeuring (van "nieuw" naar "goedgekeurd")
     * geeft de gratis credits; opnieuw goedkeuren na een afkeuring doet dat niet nog eens.
     */
    public function goedkeuren(int $userId, int $beheerderId): void
    {
        $this->db->transactional(function () use ($userId, $beheerderId): void {
            $tabel = $this->db->tabel('klussers');
            $eerste = $this->db->execute("UPDATE {$tabel} SET status = 'goedgekeurd' WHERE user_id = ? AND status = 'nieuw'", [$userId]);
            if ($eerste === 1) {
                $this->logboek->schrijf('beheerder', $beheerderId, 'klusser_goedgekeurd', 'klusser', $userId);
                if ($this->config->creditsAan && $this->config->gratisCredits > 0) {
                    $this->credits->boek($userId, $this->config->gratisCredits, 'gratis_start', 'Gratis credits bij goedkeuring', null, $beheerderId);
                }
                return;
            }
            $weer = $this->db->execute("UPDATE {$tabel} SET status = 'goedgekeurd' WHERE user_id = ? AND status IN ('afgekeurd','geblokkeerd')", [$userId]);
            if ($weer === 0) {
                $this->bestaatOfFout($userId);
                return; // was al goedgekeurd
            }
            $this->logboek->schrijf('beheerder', $beheerderId, 'klusser_opnieuw_goedgekeurd', 'klusser', $userId);
        });
    }

    public function afkeuren(int $userId, int $beheerderId, string $reden): void
    {
        $this->zet($userId, 'afgekeurd', $beheerderId, 'klusser_afgekeurd', $reden);
    }

    public function blokkeren(int $userId, int $beheerderId, string $reden): void
    {
        $this->zet($userId, 'geblokkeerd', $beheerderId, 'klusser_geblokkeerd', $reden);
    }

    private function zet(int $userId, string $status, int $beheerderId, string $actie, string $reden): void
    {
        $this->db->transactional(function () use ($userId, $status, $beheerderId, $actie, $reden): void {
            $geraakt = $this->db->execute('UPDATE ' . $this->db->tabel('klussers') . ' SET status = ? WHERE user_id = ?', [$status, $userId]);
            if ($geraakt === 0) {
                $this->bestaatOfFout($userId);
            }
            $this->logboek->schrijf('beheerder', $beheerderId, $actie, 'klusser', $userId, ['reden' => mb_substr($reden, 0, 200)]);
        });
    }

    private function bestaatOfFout(int $userId): void
    {
        if ($this->status($userId) === null) {
            throw new KlusmaatFout(KlusmaatFout::NIET_GEVONDEN, 'Deze klusser bestaat niet.');
        }
    }
}
