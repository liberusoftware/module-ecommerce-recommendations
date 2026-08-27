<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

/** What a strategy computed, before a run decides whether it may be asserted. */
final readonly class Claim
{
    public function __construct(
        public string $fromRef,
        public string $toRef,
        public float $score,
        public int $evidenceCount,
        public int $subjectCount,
    ) {}
}
