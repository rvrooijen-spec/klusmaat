<?php
declare(strict_types=1);

namespace Klusmaat\Core\Tests;

use Klusmaat\Core\Config;
use Klusmaat\Core\Kern;
use Klusmaat\Core\KlusmaatFout;
use Klusmaat\Core\VasteCatalogus;

final class BeheerPrivacyTest extends TestCase
{
    private function metCatalogus(): void
    {
        $this->kern = new Kern($this->db, new Config(), new VasteCatalogus([2, 3], [5, 6]));
    }

    public function testOnbekendePlaatsOfKlustypeGeweigerd(): void
    {
        $this->metCatalogus();
        $f = $this->verwachtFout(KlusmaatFout::ONGELDIG, fn() => $this->kern->aanvragen->plaats($this->klusInvoer(['plaats_id' => '99', 'klustype_id' => '98'])));
        $this->assertTrue(isset($f->velden['plaats_id']));
        $this->assertTrue(isset($f->velden['klustype_id']));
        $this->assertSame(1, $this->kern->aanvragen->plaats($this->klusInvoer())['id']);
    }

    public function testContactgegevensInOmschrijvingGeweigerd(): void
    {
        foreach (['Bel mij op 06 12345678 voor details', 'Mail naar jan@example.nl graag', 'Telefoon 0612-345-678 na 18 uur'] as $tekst) {
            $f = $this->verwachtFout(KlusmaatFout::ONGELDIG, fn() => $this->kern->aanvragen->plaats($this->klusInvoer(['omschrijving' => $tekst])));
            $this->assertTrue(isset($f->velden['omschrijving']), $tekst);
        }
        // Maten en aantallen mogen gewoon.
        $this->assertSame(1, $this->kern->aanvragen->plaats($this->klusInvoer(['omschrijving' => 'Muur van 4,5 x 2,60 m schilderen, 2 lagen, 40 m2.']))['id']);
    }

    public function testEigenOpgepakteKlusStaatNietMeerInDeLijst(): void
    {
        $id = $this->kern->aanvragen->plaats($this->klusInvoer())['id'];
        $k = $this->goedgekeurdeKlusser(40);
        $this->assertSame(1, count($this->kern->aanvragen->lijstVoorKlusser($k, [2], [5])));
        $this->kern->oppakken->uitvoeren($id, $k);
        $this->assertSame(0, count($this->kern->aanvragen->lijstVoorKlusser($k, [2], [5])));
        $this->assertSame(1, count($this->kern->aanvragen->mijnKlussen($k)));
    }

    public function testAnonimiserenWistAlleenOudeGeslotenKlussen(): void
    {
        $oud = $this->kern->aanvragen->plaats($this->klusInvoer());
        $nieuw = $this->kern->aanvragen->plaats($this->klusInvoer(['naam' => 'Piet Jansen', 'email' => 'piet@example.nl']));
        $open = $this->kern->aanvragen->plaats($this->klusInvoer(['naam' => 'Kees Bakker', 'email' => 'kees@example.nl']));
        $this->kern->aanvragen->sluitenMetLink($oud['token']);
        $this->kern->aanvragen->sluitenMetLink($nieuw['token']);
        $t = $this->db->tabel('aanvragen');
        $this->db->execute("UPDATE {$t} SET gesloten_op = ? WHERE id = ?", [gmdate('Y-m-d H:i:s', time() - 400 * 86400), $oud['id']]);
        $this->assertSame(1, $this->kern->aanvragen->anonimiseerOudeKlussen(365));
        $this->assertSame('', $this->kern->aanvragen->volledig($oud['id'])['email']);
        $this->assertSame('piet@example.nl', $this->kern->aanvragen->volledig($nieuw['id'])['email']);
        $this->assertSame('kees@example.nl', $this->kern->aanvragen->volledig($open['id'])['email']);
        $this->assertSame(0, $this->kern->aanvragen->anonimiseerOudeKlussen(365), 'tweede keer geen werk meer');
    }

    public function testVerzoekTotVerwijderenWistEnSluit(): void
    {
        $a = $this->kern->aanvragen->plaats($this->klusInvoer());
        $this->kern->aanvragen->plaats($this->klusInvoer(['email' => 'ander@example.nl']));
        $this->assertSame(1, count($this->kern->aanvragen->gegevensVanEmail('jan@example.nl')));
        $this->assertSame(1, $this->kern->aanvragen->wisGegevensVanEmail('jan@example.nl', 1));
        $rij = $this->kern->aanvragen->volledig($a['id']);
        $this->assertSame('gesloten', $rij['status']);
        $this->assertSame('', $rij['naam']);
        $this->assertSame(0, count($this->kern->aanvragen->gegevensVanEmail('jan@example.nl')));
        $this->assertStringNotContainsString('jan@example.nl', $this->kern->logboek->alsTekst());
    }

    public function testOverzichtBeheerPagineertEnFiltert(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->kern->aanvragen->plaats($this->klusInvoer());
        }
        $r = $this->kern->aanvragen->overzichtBeheer('open', 2, 0);
        $this->assertSame(5, $r['totaal']);
        $this->assertSame(2, count($r['rijen']));
        $this->assertSame(5, (int) $r['rijen'][0]['id']);
        $this->assertSame(0, $this->kern->aanvragen->overzichtBeheer('gesloten', 10, 0)['totaal']);
    }

    public function testControleIsStilBijGezondeData(): void
    {
        $k = $this->goedgekeurdeKlusser(41);
        $id = $this->kern->aanvragen->plaats($this->klusInvoer())['id'];
        $this->kern->oppakken->uitvoeren($id, $k);
        $this->assertSame([], $this->kern->controle->uitvoeren());
    }

    public function testControleVindtAfwijkingen(): void
    {
        $k = $this->goedgekeurdeKlusser(42);
        $id = $this->kern->aanvragen->plaats($this->klusInvoer())['id'];
        $this->kern->oppakken->uitvoeren($id, $k);
        $this->db->execute('UPDATE ' . $this->db->tabel('klussers') . ' SET saldo = 50 WHERE user_id = ?', [$k]);
        $this->db->execute('UPDATE ' . $this->db->tabel('aanvragen') . ' SET opgepakt_aantal = 3 WHERE id = ?', [$id]);
        $m = implode("\n", $this->kern->controle->uitvoeren());
        $this->assertStringContainsString('saldo van klusser 42', $m);
        $this->assertStringContainsString('Klus ' . $id, $m);
    }

    public function testKlussersOverzichtFiltert(): void
    {
        $this->goedgekeurdeKlusser(50);
        $this->kern->klussers->aanmelden(51);
        $this->assertSame(1, count($this->kern->klussers->overzicht('nieuw')));
        $this->assertSame(2, count($this->kern->klussers->overzicht()));
    }
}
