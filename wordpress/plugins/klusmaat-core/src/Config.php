<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/** De instellingen van versie 1. In WordPress komen ze uit het beheerscherm. */
final class Config
{
    public const MOMENTEN = ['zo_snel_mogelijk', 'binnen_een_week', 'binnen_een_maand', 'flexibel'];

    public function __construct(
        public readonly bool $creditsAan = true,
        public readonly int $gratisCredits = 6,
        public readonly int $kostenPerKlus = 1,
        public readonly int $maxKlussersPerKlus = 3,
        public readonly string $logGeheim = '',
    ) {
        if ($gratisCredits < 0 || $kostenPerKlus < 0 || $maxKlussersPerKlus < 1) {
            throw new \InvalidArgumentException('Ongeldige instelling: credits mogen niet negatief zijn en er moet minstens 1 klusser per klus kunnen zijn.');
        }
    }
}
