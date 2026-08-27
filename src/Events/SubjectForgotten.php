<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Events;

final readonly class SubjectForgotten
{
    public function __construct(
        public string $subjectRef,
        public int $signalsDeleted,
        public int $placementsDeleted,
        public int $affinitiesRetained,
    ) {}
}
