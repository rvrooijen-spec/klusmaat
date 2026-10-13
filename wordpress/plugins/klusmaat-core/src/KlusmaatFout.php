<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Fout met een vaste kode en een Nederlandse melding die de gebruiker mag zien.
 * Bij ONGELDIG staan de meldingen per veld in $velden.
 */
final class KlusmaatFout extends \RuntimeException
{
    public const ONGELDIG = 'ONGELDIG';
    public const NIET_GEVONDEN = 'NIET_GEVONDEN';
    public const GEEN_TOEGANG = 'GEEN_TOEGANG';
    public const NIET_GOEDGEKEURD = 'NIET_GOEDGEKEURD';
    public const KLUS_NIET_OPEN = 'KLUS_NIET_OPEN';
    public const KLUS_VOL = 'KLUS_VOL';
    public const AL_OPGEPAKT = 'AL_OPGEPAKT';
    public const TE_WEINIG_CREDITS = 'TE_WEINIG_CREDITS';

    /** @param array<string,string> $velden */
    public function __construct(public readonly string $kode, string $bericht, public readonly array $velden = [])
    {
        parent::__construct($bericht);
    }
}
