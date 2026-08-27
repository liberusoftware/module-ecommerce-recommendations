<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

use Liberu\Ecommerce\Recommendations\Data\Candidate;

/**
 * A score is meaningless without its scale. Each strategy's raw scores are
 * ratios it can defend on its own terms; comparing them across strategies is
 * only fair against the candidate set actually read, which is here and not at
 * write time.
 */
final class Normalisation
{
    /**
     * @param  list<Candidate>  $candidates
     * @return list<Candidate>
     */
    public static function perStrategy(array $candidates): array
    {
        $peak = [];

        foreach ($candidates as $candidate) {
            $key = $candidate->strategy->value;
            $peak[$key] = max($peak[$key] ?? 0.0, $candidate->rawScore);
        }

        return array_map(static function (Candidate $candidate) use ($peak): Candidate {
            $max = $peak[$candidate->strategy->value] ?? 0.0;

            // A strategy whose whole candidate set scores zero has no scale to
            // normalise against, and zero is the honest answer rather than one.
            return $candidate->normalisedTo($max > 0.0 ? $candidate->rawScore / $max : 0.0);
        }, $candidates);
    }
}
