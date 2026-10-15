<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * De lijst van geldige plaatsen en soorten klussen. In WordPress zijn dat termen van twee
 * taxonomieën; de kern kent alleen deze twee vragen. Zo kan niemand met een verzonnen id een klus plaatsen.
 */
interface Catalogus
{
    public function plaatsBestaat(int $id): bool;

    public function klustypeBestaat(int $id): bool;
}
