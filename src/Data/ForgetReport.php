<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

/**
 * Erasure removes the person and keeps the arithmetic: an affinity is a
 * statement about a pair of products, not about whoever bought them.
 */
final readonly class ForgetReport
{
    public function __construct(
        public string $subjectRef,
        public int $signalsDeleted,
        public int $placementsDeleted,
        public int $affinitiesRetained,
    ) {}
}
