<?php
declare(strict_types=1);

namespace Klusmaat\Core\Tests;

use Klusmaat\Core\Config;
use Klusmaat\Core\KlusmaatFout;

final class OppakkenTest extends TestCase
{
    private function klus(): int
    {
        return $this->kern->aanvragen->plaats($this->klusInvoer())['id'];
    }

    public function testHappyPath(): void
    {
        $k = $this->goedgekeurdeKlusser(10);
        $id = $this->klus();
        $r = $this->kern->oppakken->uitvoeren($id, $k);
        $this->assertSame(1, $r['kosten']);
        $this->assertSame(5, $r['saldo']);
        $this->assertSame(5, $this->kern->credits->saldo($k));
        $this->assertTrue($this->kern->credits->klopt($k));
        $this->assertSame('Jan de Vries', $this->kern->aanvragen->voorKlusser($id, $k)['naam']);
    }

    public function testNietGoedgekeurd(): void
    {
        $this->kern->klussers->aanmelden(11);
        $id = $this->klus();
        $this->verwachtFout(KlusmaatFout::NIET_GOEDGEKEURD, fn() => $this->kern->oppakken->uitvoeren($id, 11));
    }

    public function testTeWeinigCreditsRolltTerug(): void
    {
        $this->bouw(new Config(true, 0));
        $k = $this->goedgekeurdeKlusser(12);
        $id = $this->klus();
        $this->verwachtFout(KlusmaatFout::TE_WEINIG_CREDITS, fn() => $this->kern->oppakken->uitvoeren($id, $k));
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT opgepakt_aantal FROM ' . $this->db->tabel('aanvragen') . ' WHERE id = ?', [$id]));
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM ' . $this->db->tabel('ontgrendelingen')));
        $this->assertSame(0, $this->kern->credits->saldo($k));
    }

    public function testDubbelOppakken(): void
    {
        $k = $this->goedgekeurdeKlusser(13);
        $id = $this->klus();
        $this->kern->oppakken->uitvoeren($id, $k);
        $this->verwachtFout(KlusmaatFout::AL_OPGEPAKT, fn() => $this->kern->oppakken->uitvoeren($id, $k));
        $this->assertSame(5, $this->kern->credits->saldo($k));
    }

    public function testVolBijDrie(): void
    {
        $id = $this->klus();
        foreach ([21, 22, 23] as $u) {
            $this->kern->oppakken->uitvoeren($id, $this->goedgekeurdeKlusser($u));
        }
        $vierde = $this->goedgekeurdeKlusser(24);
        $this->verwachtFout(KlusmaatFout::KLUS_VOL, fn() => $this->kern->oppakken->uitvoeren($id, $vierde));
        $this->assertSame(6, $this->kern->credits->saldo($vierde));
    }

    public function testGeslotenKlus(): void
    {
        $k = $this->goedgekeurdeKlusser(14);
        $r = $this->kern->aanvragen->plaats($this->klusInvoer());
        $this->assertTrue($this->kern->aanvragen->sluitenMetLink($r['token']));
        $this->verwachtFout(KlusmaatFout::KLUS_NIET_OPEN, fn() => $this->kern->oppakken->uitvoeren($r['id'], $k));
    }

    public function testCreditsUit(): void
    {
        $this->bouw(new Config(false));
        $k = $this->goedgekeurdeKlusser(15);
        $id = $this->klus();
        $r = $this->kern->oppakken->uitvoeren($id, $k);
        $this->assertSame(0, $r['kosten']);
        $this->assertNull($r['saldo']);
        $this->assertSame(0, $this->kern->credits->saldo($k));
    }

    public function testOnbekendeKlus(): void
    {
        $k = $this->goedgekeurdeKlusser(16);
        $this->verwachtFout(KlusmaatFout::NIET_GEVONDEN, fn() => $this->kern->oppakken->uitvoeren(999, $k));
    }
}
