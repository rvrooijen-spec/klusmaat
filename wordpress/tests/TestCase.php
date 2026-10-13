<?php
declare(strict_types=1);

namespace Klusmaat\Core\Tests;

use Klusmaat\Core\Config;
use Klusmaat\Core\Kern;
use Klusmaat\Core\KlusmaatFout;
use Klusmaat\Core\PdoDatabase;
use Klusmaat\Core\Schema;

/** Kleine testbasis met dezelfde namen als PHPUnit, zodat overstappen later eenvoudig is. */
abstract class TestCase
{
    public int $asserties = 0;
    protected Kern $kern;
    protected PdoDatabase $db;

    protected function setUp(): void
    {
        $this->bouw(new Config());
    }

    protected function bouw(Config $config): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $this->db = new PdoDatabase($pdo);
        foreach (Schema::sqlite() as $sql) {
            $pdo->exec($sql);
        }
        $this->kern = new Kern($this->db, $config);
    }

    /** @return array<string,string> Geldige invoer voor een klus. */
    protected function klusInvoer(array $afwijkend = []): array
    {
        return array_merge([
            'klustype_id' => '5', 'plaats_id' => '2', 'omschrijving' => 'Woonkamer en gang schilderen, 40 m2.',
            'gewenst_moment' => 'binnen_een_week', 'naam' => 'Jan de Vries', 'email' => 'jan@example.nl',
            'telefoon' => '06 12345678', 'adres' => 'Kerkstraat 12', 'postcode' => '4701ab',
        ], $afwijkend);
    }

    /** Maakt een goedgekeurde klusser aan en geeft het user-id terug. */
    protected function goedgekeurdeKlusser(int $userId): int
    {
        $this->kern->klussers->aanmelden($userId);
        $this->kern->klussers->goedkeuren($userId, 1);
        return $userId;
    }

    protected function assertSame(mixed $verwacht, mixed $werkelijk, string $bericht = ''): void
    {
        $this->asserties++;
        if ($verwacht !== $werkelijk) {
            throw new \AssertionError(trim($bericht . ' Verwacht ' . var_export($verwacht, true) . ', kreeg ' . var_export($werkelijk, true)));
        }
    }

    protected function assertTrue(mixed $waarde, string $bericht = ''): void
    {
        $this->assertSame(true, $waarde, $bericht);
    }

    protected function assertFalse(mixed $waarde, string $bericht = ''): void
    {
        $this->assertSame(false, $waarde, $bericht);
    }

    protected function assertNull(mixed $waarde, string $bericht = ''): void
    {
        $this->assertSame(null, $waarde, $bericht);
    }

    protected function assertArrayNotHasKey(string $sleutel, array $lijst, string $bericht = ''): void
    {
        $this->asserties++;
        if (array_key_exists($sleutel, $lijst)) {
            throw new \AssertionError($bericht !== '' ? $bericht : "Sleutel '{$sleutel}' mag er niet in zitten.");
        }
    }

    protected function assertStringNotContainsString(string $zoek, string $tekst, string $bericht = ''): void
    {
        $this->asserties++;
        if (str_contains($tekst, $zoek)) {
            throw new \AssertionError($bericht !== '' ? $bericht : "'{$zoek}' mag niet in de tekst voorkomen.");
        }
    }

    protected function assertStringContainsString(string $zoek, string $tekst, string $bericht = ''): void
    {
        $this->asserties++;
        if (!str_contains($tekst, $zoek)) {
            throw new \AssertionError($bericht !== '' ? $bericht : "'{$zoek}' hoort in de tekst te staan.");
        }
    }

    /** Verwacht een KlusmaatFout met deze kode. Geeft de fout terug voor extra controles. */
    protected function verwachtFout(string $kode, callable $werk): KlusmaatFout
    {
        $this->asserties++;
        try {
            $werk();
        } catch (KlusmaatFout $fout) {
            if ($fout->kode !== $kode) {
                throw new \AssertionError("Verwacht fout {$kode}, kreeg {$fout->kode}: {$fout->getMessage()}");
            }
            return $fout;
        }
        throw new \AssertionError("Verwacht fout {$kode}, maar er ging niets mis.");
    }

    /** Verwacht dat dit werk een databasefout geeft (bijvoorbeeld door een trigger). */
    protected function verwachtDatabaseFout(callable $werk): void
    {
        $this->asserties++;
        try {
            $werk();
        } catch (\Klusmaat\Core\DatabaseFout) {
            return;
        }
        throw new \AssertionError('Verwacht een databasefout, maar er ging niets mis.');
    }

    protected function run(string $methode): void
    {
        $this->setUp();
        $this->$methode();
    }
}
