<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

use Liberu\Ecommerce\Recommendations\Enums\ExclusionReason;
use Liberu\Ecommerce\Recommendations\Enums\Strategy;

/** One affinity read for one placement, before and after exclusion. */
final readonly class Candidate
{
    public function __construct(
        public string $productRef,
        public Strategy $strategy,
        public float $rawScore,
        public float $normalisedScore,
        public int $evidenceCount,
        public ?ExclusionReason $excludedFor = null,
    ) {}

    public function normalisedTo(float $score): self
    {
        return new self($this->productRef, $this->strategy, $this->rawScore, $score, $this->evidenceCount, $this->excludedFor);
    }

    public function excludedFor(ExclusionReason $reason): self
    {
        return new self($this->productRef, $this->strategy, $this->rawScore, $this->normalisedScore, $this->evidenceCount, $reason);
    }

    public function survives(): bool
    {
        return $this->excludedFor === null;
    }
}
