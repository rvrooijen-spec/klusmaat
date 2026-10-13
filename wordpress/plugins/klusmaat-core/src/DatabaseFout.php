<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/** Technische databasefout. $duplicaat is waar bij een overtreden unieke sleutel. */
final class DatabaseFout extends \RuntimeException
{
    public function __construct(string $bericht, public readonly bool $duplicaat = false, ?\Throwable $vorige = null)
    {
        parent::__construct($bericht, 0, $vorige);
    }
}
