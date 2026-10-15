<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

use Klusmaat\Core\Catalogus;

/** Plaatsen en soorten klussen zijn termen in twee taxonomieën. */
final class WpCatalogus implements Catalogus
{
    public const PLAATS = 'klusmaat_plaats';
    public const KLUSTYPE = 'klusmaat_klustype';

    public function plaatsBestaat(int $id): bool
    {
        return $id > 0 && term_exists($id, self::PLAATS) !== null;
    }

    public function klustypeBestaat(int $id): bool
    {
        return $id > 0 && term_exists($id, self::KLUSTYPE) !== null;
    }

    /** @return list<\WP_Term> */
    public static function plaatsen(): array
    {
        $t = get_terms(['taxonomy' => self::PLAATS, 'hide_empty' => false, 'orderby' => 'name']);
        return is_array($t) ? array_values($t) : [];
    }

    /** @return list<\WP_Term> De soorten klussen op het hoogste niveau, in door de beheerder gekozen volgorde. */
    public static function soorten(): array
    {
        $t = get_terms(['taxonomy' => self::KLUSTYPE, 'hide_empty' => false, 'parent' => 0, 'orderby' => 'term_order', 'order' => 'ASC']);
        $t = is_array($t) ? array_values($t) : [];
        usort($t, static fn (\WP_Term $a, \WP_Term $b): int => [(int) get_term_meta($a->term_id, 'volgorde', true) ?: 999, $a->name] <=> [(int) get_term_meta($b->term_id, 'volgorde', true) ?: 999, $b->name]);
        return $t;
    }

    /** @return list<\WP_Term> */
    public static function categorieen(int $soortId): array
    {
        $t = get_terms(['taxonomy' => self::KLUSTYPE, 'hide_empty' => false, 'parent' => $soortId, 'orderby' => 'name']);
        return is_array($t) ? array_values($t) : [];
    }

    /** Naam van een term, of een streepje als die niet meer bestaat. */
    public static function naam(int $id, string $taxonomie): string
    {
        $t = get_term($id, $taxonomie);
        return $t instanceof \WP_Term ? $t->name : '-';
    }

    /** De soort (hoogste niveau) waar een klustype onder valt. */
    public static function soortVan(int $klustypeId): int
    {
        $t = get_term($klustypeId, self::KLUSTYPE);
        while ($t instanceof \WP_Term && $t->parent > 0) {
            $t = get_term($t->parent, self::KLUSTYPE);
        }
        return $t instanceof \WP_Term ? $t->term_id : 0;
    }

    /** @return list<int> Het type zelf plus alle onderliggende categorieën. */
    public static function metKinderen(int $soortId): array
    {
        $ids = get_term_children($soortId, self::KLUSTYPE);
        return array_map('intval', array_merge([$soortId], is_array($ids) ? $ids : []));
    }
}
