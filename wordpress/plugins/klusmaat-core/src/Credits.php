<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Credit-boekhouding. Elke wijziging is een nieuwe boeking; het saldo op de klusser
 * is een snelle kopie die in dezelfde transactie wordt bijgewerkt. Het saldo kan nooit
 * onder nul komen: dat bewaakt één voorwaardelijke UPDATE, ook bij gelijktijdige aanvragen.
 */
final class Credits
{
    public function __construct(private Database $db, private Logboek $logboek)
    {
    }

    public function saldo(int $userId): int
    {
        $saldo = $this->db->fetchValue('SELECT saldo FROM ' . $this->db->tabel('klussers') . ' WHERE user_id = ?', [$userId]);
        if ($saldo === null) {
            throw new KlusmaatFout(KlusmaatFout::NIET_GEVONDEN, 'Deze klusser bestaat niet.');
        }
        return (int) $saldo;
    }

    /** Geeft het nieuwe saldo terug. $delta is positief (erbij) of negatief (eraf). */
    public function boek(int $userId, int $delta, string $reden, string $toelichting = '', ?int $aanvraagId = null, int $actorId = 0): int
    {
        if ($delta === 0) {
            throw new KlusmaatFout(KlusmaatFout::ONGELDIG, 'Een boeking van 0 credits heeft geen zin.');
        }
        return (int) $this->db->transactional(function () use ($userId, $delta, $reden, $toelichting, $aanvraagId, $actorId): int {
            $klussers = $this->db->tabel('klussers');
            $geraakt = $this->db->execute(
                "UPDATE {$klussers} SET saldo = saldo + ? WHERE user_id = ? AND saldo + ? >= 0",
                [$delta, $userId, $delta]
            );
            if ($geraakt === 0) {
                $bestaat = $this->db->fetchValue("SELECT COUNT(*) FROM {$klussers} WHERE user_id = ?", [$userId]);
                if ((int) $bestaat === 0) {
                    throw new KlusmaatFout(KlusmaatFout::NIET_GEVONDEN, 'Deze klusser bestaat niet.');
                }
                throw new KlusmaatFout(KlusmaatFout::TE_WEINIG_CREDITS, 'Er zijn te weinig credits.');
            }
            $saldoNa = (int) $this->db->fetchValue("SELECT saldo FROM {$klussers} WHERE user_id = ?", [$userId]);
            $this->db->insert(
                'INSERT INTO ' . $this->db->tabel('credit_boekingen') . ' (user_id, delta, saldo_na, reden, toelichting, aanvraag_id, actor_id, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, $delta, $saldoNa, $reden, mb_substr($toelichting, 0, 255), $aanvraagId, $actorId, gmdate('Y-m-d H:i:s')]
            );
            $this->logboek->schrijf($actorId > 0 ? 'beheerder' : 'systeem', $actorId, 'credits_geboekt', 'klusser', $userId, ['delta' => $delta, 'reden' => $reden, 'saldo_na' => $saldoNa]);
            return $saldoNa;
        });
    }

    /** Klopt het saldo met de som van alle boekingen? Voor controles en tests. */
    public function klopt(int $userId): bool
    {
        $som = (int) $this->db->fetchValue('SELECT COALESCE(SUM(delta), 0) FROM ' . $this->db->tabel('credit_boekingen') . ' WHERE user_id = ?', [$userId]);
        return $som === $this->saldo($userId);
    }

    /** @return list<array<string,mixed>> Alle boekingen van een klusser, nieuwste eerst. */
    public function overzicht(int $userId): array
    {
        return $this->db->fetchAll('SELECT delta, saldo_na, reden, toelichting, aanvraag_id, aangemaakt_op FROM ' . $this->db->tabel('credit_boekingen') . ' WHERE user_id = ? ORDER BY id DESC', [$userId]);
    }
}
