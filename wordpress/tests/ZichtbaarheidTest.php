<?php
declare(strict_types=1);

namespace Klusmaat\Core\Tests;

use Klusmaat\Core\KlusmaatFout;

final class ZichtbaarheidTest extends TestCase
{
    public function testLijstBevatGeenContactgegevens(): void
    {
        $this->kern->aanvragen->plaats($this->klusInvoer());
        $k = $this->goedgekeurdeKlusser(30);
        $lijst = $this->kern->aanvragen->lijstVoorKlusser($k, [2], [5]);
        $this->assertSame(1, count($lijst));
        foreach (['naam', 'email', 'telefoon', 'adres', 'postcode', 'token_hash'] as $sleutel) {
            $this->assertArrayNotHasKey($sleutel, $lijst[0]);
        }
        $this->assertStringNotContainsString('jan@example.nl', json_encode($lijst));
        $this->assertStringNotContainsString('Kerkstraat', json_encode($lijst));
    }

    public function testDetailVoorOpgepaktBevatContactWelNiet(): void
    {
        $id = $this->kern->aanvragen->plaats($this->klusInvoer())['id'];
        $k = $this->goedgekeurdeKlusser(31);
        $voor = $this->kern->aanvragen->voorKlusser($id, $k);
        $this->assertStringNotContainsString('jan@example.nl', json_encode($voor));
        $this->assertStringNotContainsString('06 12345678', json_encode($voor));
        $this->kern->oppakken->uitvoeren($id, $k);
        $na = $this->kern->aanvragen->voorKlusser($id, $k);
        $this->assertSame('jan@example.nl', $na['email']);
    }

    public function testAndereKlusserZietNiets(): void
    {
        $id = $this->kern->aanvragen->plaats($this->klusInvoer())['id'];
        $a = $this->goedgekeurdeKlusser(32);
        $b = $this->goedgekeurdeKlusser(33);
        $this->kern->oppakken->uitvoeren($id, $a);
        $this->assertStringNotContainsString('jan@example.nl', json_encode($this->kern->aanvragen->voorKlusser($id, $b)));
    }

    public function testLogboekBevatGeenPersoonsgegevens(): void
    {
        $id = $this->kern->aanvragen->plaats($this->klusInvoer())['id'];
        $this->kern->oppakken->uitvoeren($id, $this->goedgekeurdeKlusser(34));
        $tekst = $this->kern->logboek->alsTekst();
        foreach (['jan@example.nl', 'Jan de Vries', 'Kerkstraat', '06 12345678', '4701 AB'] as $s) {
            $this->assertStringNotContainsString($s, $tekst);
        }
    }
}
