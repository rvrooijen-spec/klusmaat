<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Dunne database-laag. De kern kent geen WordPress: in WordPress draait hij op
 * WpdbDatabase, in de tests op PdoDatabase. Placeholders zijn altijd "?".
 */
interface Database
{
    /** Voert een query uit en geeft het aantal gewijzigde rijen terug. */
    public function execute(string $sql, array $params = []): int;

    /** Voegt een rij toe en geeft het nieuwe id terug. */
    public function insert(string $sql, array $params = []): int;

    /** @return list<array<string,mixed>> */
    public function fetchAll(string $sql, array $params = []): array;

    /** @return array<string,mixed>|null */
    public function fetchRow(string $sql, array $params = []): ?array;

    public function fetchValue(string $sql, array $params = []): mixed;

    /**
     * Voert $werk uit in een transactie. Bij een uitzondering wordt alles teruggedraaid.
     * Een geneste aanroep doet gewoon mee met de buitenste transactie.
     */
    public function transactional(callable $werk): mixed;

    /** Volledige tabelnaam, bijvoorbeeld "wp_klusmaat_aanvragen". */
    public function tabel(string $naam): string;
}
