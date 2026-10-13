<?php
declare(strict_types=1);

namespace Klusmaat\Core\Tests;

use Klusmaat\Core\KlusmaatFout;

final class CreditsLogboekTest extends TestCase
{
    public function testGratisCreditsEenKeer(): void
    {
        $this->kern->klussers->aanmelden(1);
        $this->kern->klussers->goedkeuren(1, 99);
        $this->kern->klussers->goedkeuren(1, 99);
        $this->assertSame(6, $this->kern->credits->saldo(1));
    }

    public function testSaldoNooitNegatief(): void
    {
        $this->goedgekeurdeKlusser(2);
        $this->verwachtFout(KlusmaatFout::TE_WEINIG_CREDITS, fn() => $this->kern->credits->boek(2, -7, 'test'));
        $this->assertSame(6, $this->kern->credits->saldo(2));
        $this->kern->credits->boek(2, -6, 'test');
        $this->assertSame(0, $this->kern->credits->saldo(2));
        $this->assertTrue($this->kern->credits->klopt(2));
    }

    public function testDubbeleAanmeldingGeweigerd(): void
    {
        $this->kern->klussers->aanmelden(3);
        $this->verwachtFout(KlusmaatFout::ONGELDIG, fn() => $this->kern->klussers->aanmelden(3));
    }

    public function testBoekingenZijnAlleenToevoegen(): void
    {
        $this->goedgekeurdeKlusser(4);
        $t = $this->db->tabel('credit_boekingen');
        $this->verwachtDatabaseFout(fn() => $this->db->execute("UPDATE {$t} SET delta = 100"));
        $this->verwachtDatabaseFout(fn() => $this->db->execute("DELETE FROM {$t}"));
    }

    public function testLogboekAlleenToevoegenEnManipulatieZichtbaar(): void
    {
        $this->goedgekeurdeKlusser(5);
        $t = $this->db->tabel('logboek');
        $this->assertSame([], $this->kern->logboek->verifieer());
        $this->verwachtDatabaseFout(fn() => $this->db->execute("UPDATE {$t} SET actie = 'x'"));
        $this->verwachtDatabaseFout(fn() => $this->db->execute("DELETE FROM {$t}"));
    }

    public function testLogboekHashDetecteertWijziging(): void
    {
        $this->goedgekeurdeKlusser(6);
        $t = $this->db->tabel('logboek');
        $pdo = (new \ReflectionProperty($this->db, 'pdo'))->getValue($this->db);
        $pdo->exec("DROP TRIGGER IF EXISTS logboek_geen_update");
        foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='trigger' AND tbl_name='{$t}'")->fetchAll(\PDO::FETCH_COLUMN) as $n) {
            $pdo->exec("DROP TRIGGER \"{$n}\"");
        }
        $pdo->exec("UPDATE {$t} SET actie = 'gewijzigd' WHERE id = 1");
        $this->assertSame([1], $this->kern->logboek->verifieer());
    }
}
