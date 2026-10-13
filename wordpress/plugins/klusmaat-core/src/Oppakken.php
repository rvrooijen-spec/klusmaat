<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Een klusser pakt een klus op: plek vastleggen, credits afschrijven en gegevens vrijgeven,
 * alles in één transactie. Gaat er iets mis, dan wordt alles teruggedraaid.
 *
 * Gelijktijdige aanvragen: de plek wordt vastgelegd met één voorwaardelijke UPDATE op het
 * tellertje van de klus, en de credits met één voorwaardelijke UPDATE op het saldo. De database
 * handelt twee tegelijk dus na elkaar af; de tweede ziet de uitkomst van de eerste.
 */
final class Oppakken
{
    public function __construct(
        private Database $db,
        private Config $config,
        private Credits $credits,
        private Logboek $logboek,
    ) {
    }

    /** @return array{aanvraag_id:int, kosten:int, saldo:int|null} */
    public function uitvoeren(int $aanvraagId, int $userId): array
    {
        return $this->db->transactional(function () use ($aanvraagId, $userId): array {
            $status = $this->db->fetchValue('SELECT status FROM ' . $this->db->tabel('klussers') . ' WHERE user_id = ?', [$userId]);
            if ($status !== 'goedgekeurd') {
                throw new KlusmaatFout(KlusmaatFout::NIET_GOEDGEKEURD, 'Je account is nog niet goedgekeurd.');
            }

            $aanvragen = $this->db->tabel('aanvragen');
            $klus = $this->db->fetchRow("SELECT status FROM {$aanvragen} WHERE id = ?", [$aanvraagId]);
            if ($klus === null) {
                throw new KlusmaatFout(KlusmaatFout::NIET_GEVONDEN, 'Deze klus is niet gevonden.');
            }
            if ($klus['status'] !== 'open') {
                throw new KlusmaatFout(KlusmaatFout::KLUS_NIET_OPEN, 'Deze klus is gesloten.');
            }
            $al = (int) $this->db->fetchValue('SELECT COUNT(*) FROM ' . $this->db->tabel('ontgrendelingen') . ' WHERE aanvraag_id = ? AND user_id = ?', [$aanvraagId, $userId]);
            if ($al > 0) {
                throw new KlusmaatFout(KlusmaatFout::AL_OPGEPAKT, 'Je hebt deze klus al opgepakt.');
            }

            $plek = $this->db->execute(
                "UPDATE {$aanvragen} SET opgepakt_aantal = opgepakt_aantal + 1 WHERE id = ? AND status = 'open' AND opgepakt_aantal < max_klussers",
                [$aanvraagId]
            );
            if ($plek === 0) {
                $nu = $this->db->fetchValue("SELECT status FROM {$aanvragen} WHERE id = ?", [$aanvraagId]);
                if ($nu !== 'open') {
                    throw new KlusmaatFout(KlusmaatFout::KLUS_NIET_OPEN, 'Deze klus is gesloten.');
                }
                throw new KlusmaatFout(KlusmaatFout::KLUS_VOL, 'Deze klus heeft al het maximale aantal klussers.');
            }

            $kosten = $this->config->creditsAan ? $this->config->kostenPerKlus : 0;
            try {
                $this->db->insert(
                    'INSERT INTO ' . $this->db->tabel('ontgrendelingen') . ' (aanvraag_id, user_id, kosten, aangemaakt_op) VALUES (?, ?, ?, ?)',
                    [$aanvraagId, $userId, $kosten, gmdate('Y-m-d H:i:s')]
                );
            } catch (DatabaseFout $e) {
                if ($e->duplicaat) {
                    throw new KlusmaatFout(KlusmaatFout::AL_OPGEPAKT, 'Je hebt deze klus al opgepakt.');
                }
                throw $e;
            }

            $saldo = null;
            if ($kosten > 0) {
                $saldo = $this->credits->boek($userId, -$kosten, 'oppakken', '', $aanvraagId, 0);
            }
            $this->logboek->schrijf('klusser', $userId, 'klus_opgepakt', 'aanvraag', $aanvraagId, ['kosten' => $kosten]);

            return ['aanvraag_id' => $aanvraagId, 'kosten' => $kosten, 'saldo' => $saldo];
        });
    }
}
