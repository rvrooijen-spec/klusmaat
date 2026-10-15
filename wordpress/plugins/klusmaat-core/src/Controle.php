<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Controleert of de gegevens nog met elkaar kloppen. Draait in het beheerscherm en kan
 * dagelijks worden uitgevoerd. Een lege lijst betekent: alles klopt.
 */
final class Controle
{
    public function __construct(private Database $db, private Logboek $logboek)
    {
    }

    /** @return list<string> Meldingen in gewone taal. */
    public function uitvoeren(): array
    {
        $meldingen = [];
        $k = $this->db->tabel('klussers');
        $b = $this->db->tabel('credit_boekingen');
        $a = $this->db->tabel('aanvragen');
        $o = $this->db->tabel('ontgrendelingen');

        $afwijkend = $this->db->fetchAll(
            "SELECT k.user_id, k.saldo, COALESCE(s.som, 0) AS som FROM {$k} k LEFT JOIN (SELECT user_id, SUM(delta) AS som FROM {$b} GROUP BY user_id) s ON s.user_id = k.user_id WHERE k.saldo <> COALESCE(s.som, 0)"
        );
        foreach ($afwijkend as $r) {
            $meldingen[] = sprintf('Het saldo van klusser %d (%d) komt niet overeen met de som van de boekingen (%d).', $r['user_id'], $r['saldo'], $r['som']);
        }

        $negatief = $this->db->fetchAll("SELECT user_id, saldo FROM {$k} WHERE saldo < 0");
        foreach ($negatief as $r) {
            $meldingen[] = sprintf('Klusser %d heeft een negatief saldo (%d).', $r['user_id'], $r['saldo']);
        }

        $teller = $this->db->fetchAll(
            "SELECT a.id, a.opgepakt_aantal, a.max_klussers, (SELECT COUNT(*) FROM {$o} x WHERE x.aanvraag_id = a.id) AS echt FROM {$a} a WHERE a.opgepakt_aantal <> (SELECT COUNT(*) FROM {$o} x WHERE x.aanvraag_id = a.id) OR a.opgepakt_aantal > a.max_klussers"
        );
        foreach ($teller as $r) {
            $meldingen[] = sprintf('Klus %d: de teller zegt %d klussers, er zijn er %d en het maximum is %d.', $r['id'], $r['opgepakt_aantal'], $r['echt'], $r['max_klussers']);
        }

        foreach ($this->logboek->verifieer() as $id) {
            $meldingen[] = sprintf('Logboekregel %d is na het schrijven gewijzigd.', $id);
        }

        return $meldingen;
    }
}
