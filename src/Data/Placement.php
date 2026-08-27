<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

use Liberu\Ecommerce\Recommendations\Enums\ExclusionReason;
use Liberu\Ecommerce\Recommendations\Enums\RefusalReason;

/**
 * What a surface asked for and what it was given, with why. An empty placement
 * carries the precondition that failed, so "nothing to say" and "nothing was
 * ever recorded" are not the same answer.
 */
final readonly class Placement
{
    /**
     * @param  list<Candidate>  $shown
     * @param  list<Candidate>  $excluded
     */
    public function __construct(
        public string $tenantId,
        public string $slot,
        public string $subjectRef,
        public string $anchorRef,
        public int $requested,
        public array $shown,
        public array $excluded,
        public int $candidatesExamined,
        public bool $catalogueChecked,
        public bool $cartChecked,
        public ?int $seed = null,
        public ?RefusalReason $refusal = null,
        public ?int $id = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->shown === [];
    }

    /** @return list<string> */
    public function productRefs(): array
    {
        return array_map(static fn (Candidate $candidate): string => $candidate->productRef, $this->shown);
    }

    /**
     * How many candidates each strategy contributed to the shown list.
     *
     * @return array<string, int>
     */
    public function strategyCounts(): array
    {
        $counts = [];

        foreach ($this->shown as $candidate) {
            $counts[$candidate->strategy->value] = ($counts[$candidate->strategy->value] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function exclusionCounts(): array
    {
        $counts = [];

        foreach ($this->excluded as $candidate) {
            $reason = $candidate->excludedFor ?? ExclusionReason::UnresolvableRef;
            $counts[$reason->value] = ($counts[$reason->value] ?? 0) + 1;
        }

        return $counts;
    }

    public function identified(int $id): self
    {
        return new self(
            $this->tenantId, $this->slot, $this->subjectRef, $this->anchorRef, $this->requested,
            $this->shown, $this->excluded, $this->candidatesExamined, $this->catalogueChecked,
            $this->cartChecked, $this->seed, $this->refusal, $id,
        );
    }
}
