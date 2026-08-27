<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Liberu\Ecommerce\Recommendations\Data\Candidate;

/**
 * Manual beats derived at any score, and the rest of the order is total: same
 * store, same shopper, same catalogue state, same window gives the same list.
 * Variety comes from a seed the placement stores, never from `inRandomOrder()`.
 */
final class Ranking
{
    /**
     * @param  list<Candidate>  $candidates
     * @return list<Candidate>
     */
    public static function order(array $candidates, ?int $seed = null): array
    {
        usort($candidates, static function (Candidate $a, Candidate $b) use ($seed): int {
            return [$b->strategy->isManual(), $b->normalisedScore] <=> [$a->strategy->isManual(), $a->normalisedScore]
                ?: self::tiebreak($a, $seed) <=> self::tiebreak($b, $seed);
        });

        return $candidates;
    }

    /** Deterministic given the seed, and a different order for a different one. */
    private static function tiebreak(Candidate $candidate, ?int $seed): string
    {
        return $seed === null ? $candidate->productRef : hash('sha256', $seed.':'.$candidate->productRef);
    }
}
