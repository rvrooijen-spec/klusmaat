<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Logboek dat alleen wordt aangevuld. Elke regel krijgt een controlehash, zodat een
 * achteraf gewijzigde regel opvalt. Zet hier GEEN persoonsgegevens in (naam, mail,
 * telefoon, adres): alleen id's en aantallen.
 */
final class Logboek
{
    public function __construct(private Database $db, private string $geheim = '')
    {
    }

    /** @param array<string,scalar|null> $details */
    public function schrijf(string $actorType, int $actorId, string $actie, string $objectType, int $objectId, array $details = []): void
    {
        $tijd = gmdate('Y-m-d H:i:s');
        $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->db->execute(
            'INSERT INTO ' . $this->db->tabel('logboek') . ' (tijd, actor_type, actor_id, actie, object_type, object_id, details, hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$tijd, $actorType, $actorId, $actie, $objectType, $objectId, $json, $this->hash($tijd, $actorType, $actorId, $actie, $objectType, $objectId, $json)]
        );
    }

    /** @return list<int> Id's van regels waarvan de inhoud niet meer bij de hash past. */
    public function verifieer(): array
    {
        $fout = [];
        foreach ($this->db->fetchAll('SELECT * FROM ' . $this->db->tabel('logboek') . ' ORDER BY id') as $r) {
            $verwacht = $this->hash($r['tijd'], $r['actor_type'], (int) $r['actor_id'], $r['actie'], $r['object_type'], (int) $r['object_id'], $r['details']);
            if (!hash_equals($verwacht, (string) $r['hash'])) {
                $fout[] = (int) $r['id'];
            }
        }
        return $fout;
    }

    /** Het logboek als tekstbestand, voor de download in het beheerscherm. */
    public function alsTekst(): string
    {
        $regels = [];
        foreach ($this->db->fetchAll('SELECT * FROM ' . $this->db->tabel('logboek') . ' ORDER BY id') as $r) {
            $regels[] = sprintf('%s UTC | %s %d | %s | %s %d | %s', $r['tijd'], $r['actor_type'], $r['actor_id'], $r['actie'], $r['object_type'], $r['object_id'], $r['details']);
        }
        return implode("\n", $regels) . ($regels ? "\n" : '');
    }

    private function hash(string $tijd, string $actorType, int $actorId, string $actie, string $objectType, int $objectId, string $json): string
    {
        $sleutel = $this->geheim === '' ? 'klusmaat' : $this->geheim;
        return hash_hmac('sha256', implode('|', [$tijd, $actorType, $actorId, $actie, $objectType, $objectId, $json]), $sleutel);
    }
}
