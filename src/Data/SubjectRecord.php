<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Data;

/** Everything the module holds about one person, across every tenant. */
final readonly class SubjectRecord
{
    /**
     * @param  list<array<string, mixed>>  $signals
     * @param  list<array<string, mixed>>  $placements
     */
    public function __construct(
        public string $subjectRef,
        public array $signals,
        public array $placements,
    ) {}

    public function isEmpty(): bool
    {
        return $this->signals === [] && $this->placements === [];
    }
}
