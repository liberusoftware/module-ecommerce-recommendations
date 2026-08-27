<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Illuminate\Support\Carbon;
use Liberu\Ecommerce\Recommendations\Contracts\CatalogueReader;
use Liberu\Ecommerce\Recommendations\Data\CatalogueItem;
use Liberu\Ecommerce\Recommendations\Data\Claim;

/**
 * Overlap of the classification the catalogue reports, never a price band and
 * never a shuffle. A statement about the catalogue rather than about people,
 * which is why no anonymity floor applies to it.
 */
final class ContentSimilarityClaims
{
    /** @return list<Claim> */
    public static function for(string $tenantId, Carbon $since, Carbon $until, CatalogueReader $catalogue): array
    {
        $refs = Windows::signals($tenantId, $since, $until)
            ->distinct()
            ->orderBy('product_ref')
            ->pluck('product_ref')
            ->map(static fn (mixed $ref): string => Cast::str($ref))
            ->all();

        if (count($refs) < 2) {
            return [];
        }

        // Pairwise over the products the window saw: quadratic, and bounded by
        // the window rather than by the catalogue.
        $items = $catalogue->describe($tenantId, array_values($refs));
        $claims = [];

        foreach ($items as $fromRef => $from) {
            foreach ($items as $toRef => $to) {
                if ($fromRef === $toRef) {
                    continue;
                }

                $shared = self::jaccard($from, $to);

                if ($shared[0] === 0) {
                    continue;
                }

                $claims[] = new Claim(Cast::str($fromRef), Cast::str($toRef), $shared[0] / $shared[1], $shared[0], 0);
            }
        }

        return $claims;
    }

    /** @return array{0: int, 1: int} intersection and union sizes */
    private static function jaccard(CatalogueItem $from, CatalogueItem $to): array
    {
        $a = $from->traits();
        $b = $to->traits();
        $union = count(array_unique(array_merge($a, $b)));

        return [count(array_intersect($a, $b)), max(1, $union)];
    }
}
