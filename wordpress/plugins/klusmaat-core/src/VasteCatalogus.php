<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/** Catalogus met vaste lijsten van id's. Voor de tests. */
final class VasteCatalogus implements Catalogus
{
    /**
     * @param list<int> $plaatsIds
     * @param list<int> $klustypeIds
     */
    public function __construct(private array $plaatsIds, private array $klustypeIds)
    {
    }

    public function plaatsBestaat(int $id): bool
    {
        return in_array($id, $this->plaatsIds, true);
    }

    public function klustypeBestaat(int $id): bool
    {
        return in_array($id, $this->klustypeIds, true);
    }
}
