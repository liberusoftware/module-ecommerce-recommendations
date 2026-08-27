<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Exceptions;

/**
 * The schema stores a ratio in [0, 1] and the model refuses anything else, so
 * the two agree. The host's column held four decimal places and its own tests
 * wrote 85.5 into it, which only sqlite tolerated.
 */
final class ScoreOutOfRange extends RecommendationsException
{
    public static function for(float $score): self
    {
        return new self("A score must be a ratio in [0, 1]; got {$score}.");
    }
}
