<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/** Bundelt de onderdelen van de kern. Zo bouwen WordPress en de tests hem op dezelfde manier op. */
final class Kern
{
    public readonly Logboek $logboek;
    public readonly Credits $credits;
    public readonly Klussers $klussers;
    public readonly Aanvragen $aanvragen;
    public readonly Oppakken $oppakken;
    public readonly Controle $controle;

    public function __construct(public readonly Database $db, public readonly Config $config, ?Catalogus $catalogus = null)
    {
        $this->logboek = new Logboek($db, $config->logGeheim);
        $this->credits = new Credits($db, $this->logboek);
        $this->klussers = new Klussers($db, $config, $this->credits, $this->logboek);
        $this->aanvragen = new Aanvragen($db, $config, $this->logboek, $catalogus);
        $this->oppakken = new Oppakken($db, $config, $this->credits, $this->logboek);
        $this->controle = new Controle($db, $this->logboek);
    }
}
