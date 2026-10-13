<?php
declare(strict_types=1);

namespace Klusmaat\Core\Tests;

use Klusmaat\Core\Aanvragen;
use Klusmaat\Core\KlusmaatFout;
use Klusmaat\Core\Schema;

final class KlantlinkValidatieTest extends TestCase
{
    public function testTokenLangEnAlleenHashOpgeslagen(): void
    {
        $r = $this->kern->aanvragen->plaats($this->klusInvoer());
        $this->assertTrue(strlen($r['token']) >= 43);
        $rijen = json_encode($this->db->fetchAll('SELECT * FROM ' . $this->db->tabel('aanvragen')));
        $this->assertStringNotContainsString($r['token'], $rijen);
        $this->assertStringContainsString(Aanvragen::hashToken($r['token']), $rijen);
    }

    public function testKlantLinkWerktEnFoutTokenNiet(): void
    {
        $r = $this->kern->aanvragen->plaats($this->klusInvoer());
        $this->assertSame($r['id'], (int) $this->kern->aanvragen->voorKlant($r['token'])['id']);
        $this->assertNull($this->kern->aanvragen->voorKlant('onzin'));
        $this->verwachtFout(KlusmaatFout::NIET_GEVONDEN, fn() => $this->kern->aanvragen->sluitenMetLink('onzin'));
    }

    public function testNederlandseValidatieMeldingen(): void
    {
        $f = $this->verwachtFout(KlusmaatFout::ONGELDIG, fn() => $this->kern->aanvragen->plaats($this->klusInvoer([
            'omschrijving' => 'kort', 'email' => 'geen-mail', 'telefoon' => '12', 'postcode' => '1234',
        ])));
        foreach (['omschrijving', 'email', 'telefoon', 'postcode'] as $veld) {
            $this->assertTrue(isset($f->velden[$veld]), "veld $veld mist");
        }
    }

    public function testPostcodeGenormaliseerd(): void
    {
        $id = $this->kern->aanvragen->plaats($this->klusInvoer())['id'];
        $this->assertSame('4701 AB', $this->kern->aanvragen->volledig($id)['postcode']);
    }

    public function testSchemaKolommenKomenOvereen(): void
    {
        foreach (Schema::KOLOMMEN as $tabel => $kolommen) {
            $echt = array_column($this->db->fetchAll('PRAGMA table_info(' . $this->db->tabel($tabel) . ')'), 'name');
            foreach ($kolommen as $k) {
                $this->assertTrue(in_array($k, $echt, true), "$tabel.$k ontbreekt");
            }
        }
    }
}
